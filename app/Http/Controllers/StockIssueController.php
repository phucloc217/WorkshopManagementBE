<?php

namespace App\Http\Controllers;

use App\Models\StockIssue;
use App\Models\StockIssueItem;
use App\Models\WarehouseParts;
use App\Models\JobPart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockIssueController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]);

        $query = StockIssue::with([
            'warehouse:id,name',
            'jobOrder:id,order_no',
            'createdBy:id,name',
        ])
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('issue_no', 'like', "%{$request->search}%"))
            ->orderByRaw("CASE WHEN status = 'Mới Tạo' THEN 0 ELSE 1 END")
            ->latest('created_at');

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    public function show(StockIssue $stockIssue)
    {
        return response()->json(
            $stockIssue->load([
                'warehouse:id,name',
                'jobOrder:id,order_no',
                'createdBy:id,name',
                'items.part:id,part_code,description',
            ])
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id'    => 'required|exists:warehouses,id',
            'job_order_id'    => 'nullable|exists:job_orders,id',
            'note'            => 'nullable|string',
            'items'           => 'required|array|min:1',
            'items.*.part_id' => 'required|exists:parts,id',
            'items.*.qty'     => 'required|integer|min:1',
        ]);

        // Kiểm tra tồn kho trước khi tạo phiếu
        foreach ($request->items as $item) {
            $stock = WarehouseParts::where('warehouse_id', $request->warehouse_id)
                ->where('part_id', $item['part_id'])
                ->value('qty') ?? 0;

            if ($stock < $item['qty']) {
                $part = \App\Models\Part::find($item['part_id']);
                return response()->json([
                    'message' => "Linh kiện {$part->part_code} không đủ tồn kho (còn {$stock})"
                ], 422);
            }
        }

        $issue = DB::transaction(function () use ($request) {
            $issue = StockIssue::create([
                'warehouse_id' => $request->warehouse_id,
                'job_order_id' => $request->job_order_id,
                'issue_no'     => $this->generateIssueNo(),
                'note'         => $request->note,
                'status'       => 'Mới Tạo',
                'created_by'   => auth()->id(),
                'created_at'   => now(),
            ]);

            foreach ($request->items as $item) {
                StockIssueItem::create([
                    'stock_issue_id' => $issue->id,
                    'part_id'        => $item['part_id'],
                    'qty'            => $item['qty'],
                ]);
            }

            return $issue;
        });

        return response()->json($issue->load('items.part'), 201);
    }

    public function confirm(StockIssue $stockIssue)
    {
        if ($stockIssue->status !== 'Mới Tạo') {
            return response()->json(['message' => 'Phiếu đã được xử lý'], 422);
        }

        // Kiểm tra tồn kho lần nữa trước khi xuất
        foreach ($stockIssue->items as $item) {
            $stock = WarehouseParts::where('warehouse_id', $stockIssue->warehouse_id)
                ->where('part_id', $item->part_id)
                ->value('qty') ?? 0;

            if ($stock < $item->qty) {
                return response()->json([
                    'message' => "Linh kiện {$item->part->part_code} không đủ tồn kho (còn {$stock})"
                ], 422);
            }
        }

        DB::transaction(function () use ($stockIssue) {
            foreach ($stockIssue->items as $item) {
                // Trừ tồn kho
                WarehouseParts::where('warehouse_id', $stockIssue->warehouse_id)
                    ->where('part_id', $item->part_id)
                    ->decrement('qty', $item->qty);

                // Nếu gắn với phiếu sửa chữa thì cập nhật qty_issued
                if ($stockIssue->job_order_id) {
                    JobPart::where('job_order_id', $stockIssue->job_order_id)
                        ->where('part_id', $item->part_id)
                        ->increment('qty_issued', $item->qty);
                }
            }

            $stockIssue->update([
                'status'    => 'Hoàn Thành',
                'issued_by' => auth()->id(),
                'issued_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Xác nhận xuất kho thành công']);
    }

    public function destroy(StockIssue $stockIssue)
    {
        if ($stockIssue->status !== 'Mới Tạo') {
            return response()->json(['message' => 'Không thể xóa phiếu đã hoàn thành'], 422);
        }

        $stockIssue->delete();

        return response()->json(['message' => 'Xóa phiếu xuất thành công']);
    }

    // Lấy danh sách linh kiện từ phiếu sửa chữa (chưa xuất đủ)
    public function getJobOrderParts(Request $request)
    {
        $request->validate([
            'job_order_id' => 'required|exists:job_orders,id',
        ]);

        $parts = JobPart::with('part:id,part_code,description')
            ->where('job_order_id', $request->job_order_id)
            ->whereColumn('qty_issued', '<', 'qty')
            ->get()
            ->map(fn($jp) => [
                'part_id'     => $jp->part_id,
                'part'        => $jp->part,
                'qty'         => $jp->qty - $jp->qty_issued, // số lượng còn cần xuất
                'qty_ordered' => $jp->qty,
                'qty_issued'  => $jp->qty_issued,
            ]);

        return response()->json($parts);
    }

    private function generateIssueNo(): string
    {
        $prefix = 'PX-' . now()->format('ym');
        $last = StockIssue::where('issue_no', 'like', "$prefix%")
            ->orderByDesc('issue_no')
            ->value('issue_no');

        $seq = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}

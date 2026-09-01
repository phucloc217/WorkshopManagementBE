<?php

namespace App\Http\Controllers;

use App\Models\StockIssue;
use App\Models\StockIssueItem;
use App\Models\WarehouseParts;
use App\Models\JobPart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Part;
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
    public function import(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'job_order_id' => 'nullable|exists:job_orders,id',
            'note'         => 'nullable|string',
            'rows'         => 'required|array|min:1',
        ]);
 
        if (!auth()->user()->canAccessWarehouse($request->warehouse_id)) {
            abort(403, 'Bạn không có quyền truy cập kho này');
        }
 
        $errors = [];
        $items  = [];
        $seen   = [];
 
        // Nạp sẵn toàn bộ mã hợp lệ trong 1 query
        $inputCodes = collect($request->rows)
            ->map(fn($row) => strtoupper(trim($row['part_code'] ?? '')))
            ->filter()
            ->unique()
            ->values();
 
        $parts = Part::whereIn(DB::raw('UPPER(part_code)'), $inputCodes)
            ->get()
            ->keyBy(fn($part) => strtoupper($part->part_code));
 
        // Nạp sẵn tồn kho của các mã này
        $stocks = WarehouseParts::where('warehouse_id', $request->warehouse_id)
            ->whereIn('part_id', $parts->pluck('id'))
            ->pluck('qty', 'part_id');
 
        foreach ($request->rows as $index => $row) {
            $line = $index + 2;   // dòng 1 là tiêu đề
            $code = strtoupper(trim($row['part_code'] ?? ''));
            $qty  = $row['qty'] ?? null;
 
            if ($code === '') {
                $errors[] = [
                    'row'       => $line,
                    'part_code' => '',
                    'message'   => 'Thiếu mã linh kiện',
                ];
                continue;
            }
 
            if (!is_numeric($qty) || (int) $qty < 1) {
                $errors[] = [
                    'row'       => $line,
                    'part_code' => $code,
                    'message'   => 'Số lượng phải là số nguyên lớn hơn 0',
                ];
                continue;
            }
 
            if (!$parts->has($code)) {
                $errors[] = [
                    'row'       => $line,
                    'part_code' => $code,
                    'message'   => 'Mã linh kiện chưa có trong hệ thống',
                ];
                continue;
            }
 
            $partId = $parts[$code]->id;
            $qty    = (int) $qty;
 
            // Mã lặp trong file → cộng dồn
            if (isset($seen[$partId])) {
                $newQty = $items[$seen[$partId]]['qty'] + $qty;
                $stock  = $stocks[$partId] ?? 0;
 
                if ($newQty > $stock) {
                    $errors[] = [
                        'row'       => $line,
                        'part_code' => $code,
                        'message'   => "Cộng dồn vượt tồn kho (cần {$newQty}, còn {$stock})",
                    ];
                    continue;
                }
 
                $items[$seen[$partId]]['qty'] = $newQty;
 
                $errors[] = [
                    'row'       => $line,
                    'part_code' => $code,
                    'message'   => 'Mã bị lặp trong file — đã cộng dồn số lượng',
                    'type'      => 'warning',
                ];
                continue;
            }
 
            // Kiểm tra tồn kho
            $stock = $stocks[$partId] ?? 0;
 
            if ($stock < $qty) {
                $errors[] = [
                    'row'       => $line,
                    'part_code' => $code,
                    'message'   => $stock === 0
                        ? 'Không còn tồn trong kho'
                        : "Không đủ tồn kho (cần {$qty}, còn {$stock})",
                ];
                continue;
            }
 
            $seen[$partId] = count($items);
            $items[] = [
                'part_id' => $partId,
                'qty'     => $qty,
            ];
        }
 
        if (empty($items)) {
            return response()->json([
                'message'    => 'Không có dòng hợp lệ nào để xuất kho',
                'total_rows' => count($request->rows),
                'success'    => 0,
                'failed'     => count($errors),
                'errors'     => $errors,
            ], 422);
        }
 
        $issue = DB::transaction(function () use ($request, $items) {
            $issue = StockIssue::create([
                'warehouse_id' => $request->warehouse_id,
                'job_order_id' => $request->job_order_id,
                'issue_no'     => $this->generateIssueNo(),
                'note'         => $request->note,
                'status'       => 'Mới Tạo',
                'created_by'   => auth()->id(),
                'created_at'   => now(),
            ]);
 
            foreach ($items as $item) {
                StockIssueItem::create([
                    'stock_issue_id' => $issue->id,
                    'part_id'        => $item['part_id'],
                    'qty'            => $item['qty'],
                ]);
            }
 
            return $issue;
        });
 
        $failed = collect($errors)->where('type', '!=', 'warning')->count();
 
        return response()->json([
            'message'  => $failed > 0
                ? "Đã tạo phiếu {$issue->issue_no} với " . count($items) . " linh kiện, {$failed} dòng bị bỏ qua"
                : "Đã tạo phiếu {$issue->issue_no} với " . count($items) . " linh kiện",
            'issue_no'   => $issue->issue_no,
            'total_rows' => count($request->rows),
            'success'    => count($items),
            'failed'     => $failed,
            'data'       => $issue->load('items.part'),
            'errors'     => $errors,
        ], 201);
    }
}

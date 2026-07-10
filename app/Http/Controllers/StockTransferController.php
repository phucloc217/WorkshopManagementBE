<?php

namespace App\Http\Controllers;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\WarehouseParts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]);

        $query = StockTransfer::accessibleBy(auth()->user())
            ->with([
                'fromWarehouse:id,name',
                'toWarehouse:id,name',
                'createdBy:id,name',
            ])
            ->when($request->warehouse_id, function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('from_warehouse_id', $request->warehouse_id)
                        ->orWhere('to_warehouse_id', $request->warehouse_id);
                });
            })
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('transfer_no', 'like', "%{$request->search}%"))
            ->orderByRaw("CASE WHEN status = 'Mới Tạo' THEN 0 WHEN status = 'Đang Chuyển' THEN 1 ELSE 2 END")
            ->latest('created_at');

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    public function show(StockTransfer $stockTransfer)
    {
        return response()->json(
            $stockTransfer->load([
                'fromWarehouse:id,name',
                'toWarehouse:id,name',
                'createdBy:id,name',
                'transferredBy:id,name',
                'receivedBy:id,name',
                'items.part:id,part_code,description',
            ])
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id'   => 'required|exists:warehouses,id|different:from_warehouse_id',
            'document'          => 'nullable|string',
            'transfer_reason'   => 'nullable|string',
            'note'              => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.part_id'   => 'required|exists:parts,id',
            'items.*.qty'       => 'required|integer|min:1',
        ]);

        // Kiểm tra tồn kho nguồn
        foreach ($request->items as $item) {
            $stock = WarehouseParts::where('warehouse_id', $request->from_warehouse_id)
                ->where('part_id', $item['part_id'])
                ->value('qty') ?? 0;

            if ($stock < $item['qty']) {
                $part = \App\Models\Part::find($item['part_id']);
                return response()->json([
                    'message' => "Linh kiện {$part->part_code} không đủ tồn kho nguồn (còn {$stock})"
                ], 422);
            }
        }

        $transfer = DB::transaction(function () use ($request) {
            $transfer = StockTransfer::create([
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id'   => $request->to_warehouse_id,
                'transfer_no'       => $this->generateTransferNo(),
                'document'          => $request->document,
                'transfer_reason'   => $request->transfer_reason,
                'note'              => $request->note,
                'status'            => 'Mới Tạo',
                'created_by'        => auth()->id(),
                'created_at'        => now(),
            ]);

            foreach ($request->items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'part_id'           => $item['part_id'],
                    'qty'               => $item['qty'],
                    'qty_received'      => 0,
                ]);
            }

            return $transfer;
        });

        return response()->json($transfer->load('items.part'), 201);
    }

    // Bước 1: Xác nhận chuyển đi — trừ kho nguồn
    public function transfer(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status !== 'Mới Tạo') {
            return response()->json(['message' => 'Phiếu đã được xử lý'], 422);
        }

        foreach ($stockTransfer->items as $item) {
            $stock = WarehouseParts::where('warehouse_id', $stockTransfer->from_warehouse_id)
                ->where('part_id', $item->part_id)
                ->value('qty') ?? 0;

            if ($stock < $item->qty) {
                return response()->json([
                    'message' => "Linh kiện {$item->part->part_code} không đủ tồn kho nguồn (còn {$stock})"
                ], 422);
            }
        }

        DB::transaction(function () use ($stockTransfer) {
            foreach ($stockTransfer->items as $item) {
                WarehouseParts::where('warehouse_id', $stockTransfer->from_warehouse_id)
                    ->where('part_id', $item->part_id)
                    ->decrement('qty', $item->qty);
            }

            $stockTransfer->update([
                'status'         => 'Đang Chuyển',
                'transferred_by' => auth()->id(),
                'transferred_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Xác nhận chuyển kho thành công']);
    }

    // Bước 2: Xác nhận nhận hàng — cộng kho đích
    public function receive(Request $request, StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status !== 'Đang Chuyển') {
            return response()->json(['message' => 'Phiếu chưa được chuyển hoặc đã hoàn thành'], 422);
        }

        $request->validate([
            'items'                => 'required|array|min:1',
            'items.*.id'           => 'required|exists:stock_transfer_items,id',
            'items.*.qty_received' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($request, $stockTransfer) {
            foreach ($request->items as $itemData) {
                $item = StockTransferItem::find($itemData['id']);

                if ($itemData['qty_received'] > $item->qty) {
                    abort(422, "Số lượng nhận không được vượt quá số lượng chuyển");
                }

                $item->update(['qty_received' => $itemData['qty_received']]);

                if ($itemData['qty_received'] > 0) {
                    WarehouseParts::updateOrCreate(
                        [
                            'warehouse_id' => $stockTransfer->to_warehouse_id,
                            'part_id'      => $item->part_id,
                        ],
                        ['qty' => 0]
                    );

                    WarehouseParts::where('warehouse_id', $stockTransfer->to_warehouse_id)
                        ->where('part_id', $item->part_id)
                        ->increment('qty', $itemData['qty_received']);
                }
            }

            $stockTransfer->update([
                'status'      => 'Hoàn Thành',
                'received_by' => auth()->id(),
                'received_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Xác nhận nhận hàng thành công']);
    }

    public function destroy(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status !== 'Mới Tạo') {
            return response()->json(['message' => 'Không thể xóa phiếu đã xử lý'], 422);
        }

        $stockTransfer->delete();

        return response()->json(['message' => 'Xóa phiếu luân chuyển thành công']);
    }

    private function generateTransferNo(): string
    {
        $prefix = 'LC-' . now()->format('ym');
        $last = StockTransfer::where('transfer_no', 'like', "$prefix%")
            ->orderByDesc('transfer_no')
            ->value('transfer_no');

        $seq = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}

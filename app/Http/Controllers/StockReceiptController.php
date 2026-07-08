<?php

namespace App\Http\Controllers;

use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\WarehouseParts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockReceiptController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]);

        $query = StockReceipt::with([
            'warehouse:id,name',
            'createdBy:id,name',
        ])
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('receipt_no', 'like', "%{$request->search}%"))
            ->latest('created_at');

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    public function show(StockReceipt $stockReceipt)
    {
        return response()->json(
            $stockReceipt->load([
                'warehouse:id,name',
                'createdBy:id,name,phone',
                'items.part:id,part_code,description',
            ])
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id'    => 'required|exists:warehouses,id',
            'note'            => 'nullable|string',
            'import_from'     => 'nullable|string',
            'document'        => 'nullable|string',
            'received_at'     => 'nullable|date',
            'items'           => 'required|array|min:1',
            'items.*.part_id' => 'required|exists:parts,id',
            'items.*.qty'     => 'required|integer|min:1',
        ]);

        $receipt = DB::transaction(function () use ($request) {
            $receipt = StockReceipt::create([
                'warehouse_id' => $request->warehouse_id,
                'receipt_no'   => $this->generateReceiptNo(),
                'note'         => $request->note,
                'import_from'  => $request->import_from,
                'document'     => $request->document,
                'received_at'  => $request->received_at,
                'status'       => $request->received_at ? 'Hoàn Thành' : 'Mới Tạo',
                'created_by'   => auth()->id(),
                'created_at'   => now(),
            ]);

            foreach ($request->items as $item) {
                StockReceiptItem::create([
                    'stock_receipt_id' => $receipt->id,
                    'part_id'          => $item['part_id'],
                    'qty'              => $item['qty'],
                ]);
            }

            if ($request->received_at) {
                $receipt->update([
                    'received_by' => auth()->id(),
                ]);
                $this->updateInventory($request->warehouse_id, $request->items);
            }

            return $receipt;
        });

        return response()->json($receipt->load('items.part'), 201);
    }

    public function confirm(Request $request, StockReceipt $stockReceipt)
    {
        if ($stockReceipt->status !== 'Mới Tạo') {
            return response()->json(['message' => 'Phiếu đã được xử lý'], 422);
        }

        $request->validate([
            'document'    => 'nullable|string',
            'import_from' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $stockReceipt) {
            $items = $stockReceipt->items->map(fn($item) => [
                'part_id' => $item->part_id,
                'qty'     => $item->qty,
            ])->toArray();

            $this->updateInventory($stockReceipt->warehouse_id, $items);

            $stockReceipt->update([
                'status'      => 'Hoàn Thành',
                'received_by' => auth()->id(),
                'received_at' => now(),
                'document'    => $request->document ?? $stockReceipt->document,
                'import_from' => $request->import_from ?? $stockReceipt->import_from,
            ]);
        });

        return response()->json(['message' => 'Xác nhận nhập kho thành công']);
    }

    private function updateInventory(string $warehouseId, array $items): void
    {
        foreach ($items as $item) {
            WarehouseParts::updateOrCreate(
                [
                    'warehouse_id' => $warehouseId,
                    'part_id'      => $item['part_id'],
                ],
                ['qty' => 0]
            );

            WarehouseParts::where('warehouse_id', $warehouseId)
                ->where('part_id', $item['part_id'])
                ->increment('qty', $item['qty']);
        }
    }

    public function destroy(StockReceipt $stockReceipt)
    {
        if ($stockReceipt->status !== 'Mới Tạo') {
            return response()->json(['message' => 'Không thể xóa phiếu đã hoàn thành'], 422);
        }

        $stockReceipt->delete();

        return response()->json(['message' => 'Xóa phiếu nhập thành công']);
    }

    private function generateReceiptNo(): string
    {
        $prefix = 'PN-' . now()->format('ym');
        $last = StockReceipt::where('receipt_no', 'like', "$prefix%")
            ->orderByDesc('receipt_no')
            ->value('receipt_no');

        $seq = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}

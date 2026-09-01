<?php

namespace App\Http\Controllers;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\WarehouseParts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Part;

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
    /**
     * Import danh sách linh kiện từ Excel để tạo phiếu luân chuyển.
     *
     * Kiểm tra tồn kho của KHO NGUỒN.
     */
    public function import(Request $request)
    {
        $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id'   => 'required|exists:warehouses,id|different:from_warehouse_id',
            'document'          => 'nullable|string',
            'transfer_reason'   => 'nullable|string',
            'note'              => 'nullable|string',
            'rows'              => 'required|array|min:1',
        ]);

        $user = auth()->user();

        // Chỉ kho nguồn mới được lập phiếu chuyển
        if (!$user->canAccessWarehouse($request->from_warehouse_id)) {
            abort(403, 'Bạn không có quyền truy cập kho nguồn');
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

        // Nạp sẵn tồn kho của kho nguồn
        $stocks = WarehouseParts::where('warehouse_id', $request->from_warehouse_id)
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
            $stock  = $stocks[$partId] ?? 0;

            // Mã lặp trong file → cộng dồn
            if (isset($seen[$partId])) {
                $newQty = $items[$seen[$partId]]['qty'] + $qty;

                if ($newQty > $stock) {
                    $errors[] = [
                        'row'       => $line,
                        'part_code' => $code,
                        'message'   => "Cộng dồn vượt tồn kho nguồn (cần {$newQty}, còn {$stock})",
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

            if ($stock < $qty) {
                $errors[] = [
                    'row'       => $line,
                    'part_code' => $code,
                    'message'   => $stock === 0
                        ? 'Không còn tồn trong kho nguồn'
                        : "Không đủ tồn kho nguồn (cần {$qty}, còn {$stock})",
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
                'message'    => 'Không có dòng hợp lệ nào để luân chuyển',
                'total_rows' => count($request->rows),
                'success'    => 0,
                'failed'     => count($errors),
                'errors'     => $errors,
            ], 422);
        }

        $transfer = DB::transaction(function () use ($request, $items) {
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

            foreach ($items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'part_id'           => $item['part_id'],
                    'qty'               => $item['qty'],
                    'qty_received'      => 0,
                ]);
            }

            return $transfer;
        });

        $failed = collect($errors)->where('type', '!=', 'warning')->count();

        return response()->json([
            'message'     => $failed > 0
                ? "Đã tạo phiếu {$transfer->transfer_no} với " . count($items) . " linh kiện, {$failed} dòng bị bỏ qua"
                : "Đã tạo phiếu {$transfer->transfer_no} với " . count($items) . " linh kiện",
            'transfer_no' => $transfer->transfer_no,
            'total_rows'  => count($request->rows),
            'success'     => count($items),
            'failed'      => $failed,
            'data'        => $transfer->load('items.part'),
            'errors'      => $errors,
        ], 201);
    }
}

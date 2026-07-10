<?php

namespace App\Http\Controllers;

use App\Models\WarehouseParts;
use App\Models\Part;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseInventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'search'       => 'nullable|string',
            'low_stock'    => 'nullable|boolean',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]);
        if (!auth()->user()->canAccessWarehouse($request->warehouse_id)) {
            return response()->json(['message' => 'Bạn không có quyền truy cập kho này'], 403);
        }
        $warehouseId = $request->warehouse_id;

        $query = Part::query()
            ->select('parts.*')
            ->addSelect(\DB::raw("COALESCE(warehouse_parts.qty, 0) as qty"))
            ->leftJoin('warehouse_parts', function ($join) use ($warehouseId) {
                $join->on('warehouse_parts.part_id', '=', 'parts.id')
                    ->where('warehouse_parts.warehouse_id', '=', $warehouseId);
            })
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('parts.part_code', 'like', "%{$request->search}%")
                        ->orWhere('parts.description', 'like', "%{$request->search}%");
                });
            })
            ->when($request->low_stock, function ($q) {
                $q->where(\DB::raw("COALESCE(warehouse_parts.qty, 0)"), '<=', \DB::raw('parts.min_qty'));
            })
            ->orderByRaw("CASE WHEN COALESCE(warehouse_parts.qty, 0) = 0 THEN 2
                   WHEN COALESCE(warehouse_parts.qty, 0) <= parts.min_qty THEN 1
                   ELSE 0 END")
            ->orderByDesc('warehouse_parts.qty');

        return response()->json($query->paginate($request->per_page ?? 20));
    }
    public function importExportHistory(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]);

        $warehouseId = $request->warehouse_id;

        // Lịch sử nhập
        $receipts = DB::table('stock_receipt_items')
            ->join('stock_receipts', 'stock_receipts.id', '=', 'stock_receipt_items.stock_receipt_id')
            ->join('parts', 'parts.id', '=', 'stock_receipt_items.part_id')
            ->where('stock_receipts.warehouse_id', $warehouseId)
            ->select([
                'stock_receipt_items.id',
                'stock_receipts.created_at as date',
                'stock_receipts.receipt_no as ref_no',
                'parts.part_code',
                'parts.description',
                DB::raw("'Nhập' as type"),
                'stock_receipt_items.qty',
                'stock_receipts.status',
            ]);

        // Lịch sử xuất
        $issues = DB::table('stock_issue_items')
            ->join('stock_issues', 'stock_issues.id', '=', 'stock_issue_items.stock_issue_id')
            ->join('parts', 'parts.id', '=', 'stock_issue_items.part_id')
            ->where('stock_issues.warehouse_id', $warehouseId)
            ->select([
                'stock_issue_items.id',
                'stock_issues.created_at as date',
                'stock_issues.issue_no as ref_no',
                'parts.part_code',
                'parts.description',
                DB::raw("'Xuất' as type"),
                'stock_issue_items.qty',
                'stock_issues.status',
            ]);

        // Gộp 2 query bằng union rồi sort theo ngày
        $query = $receipts->unionAll($issues);

        $result = DB::query()
            ->fromSub($query, 'history')
            ->orderByDesc('date')
            ->paginate($request->per_page ?? 20);

        // Format ngày
        $result->getCollection()->transform(function ($item) {
            $item->date = \Carbon\Carbon::parse($item->date)->format('d/m/Y H:i');
            return $item;
        });

        return response()->json($result);
    }

    public function partsToImport(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]);

        $warehouseId = $request->warehouse_id;

        // Lấy workshop của kho để filter phiếu sửa đúng xưởng
        $workshopId = \App\Models\Warehouse::find($warehouseId)->workshop_id;

        $query = DB::table('job_parts')
            ->join('job_orders', 'job_orders.id', '=', 'job_parts.job_order_id')
            ->join('parts', 'parts.id', '=', 'job_parts.part_id')
            ->leftJoin('warehouse_parts', function ($join) use ($warehouseId) {
                $join->on('warehouse_parts.part_id', '=', 'parts.id')
                    ->where('warehouse_parts.warehouse_id', '=', $warehouseId);
            })
            ->where('job_orders.workshop_id', $workshopId)
            ->whereIn('job_orders.overall_status', ['Mới Tiếp Nhận', 'Đang Thực Hiện'])
            ->whereColumn('job_parts.qty_issued', '<', 'job_parts.qty')
            ->groupBy('parts.id', 'parts.part_code', 'parts.description', 'warehouse_parts.qty')
            ->select([
                'parts.id',
                'parts.part_code',
                'parts.description',
                DB::raw('SUM(job_parts.qty - job_parts.qty_issued) as qty_needed'),
                DB::raw('COALESCE(warehouse_parts.qty, 0) as qty_in_stock'),
                DB::raw('GREATEST(SUM(job_parts.qty - job_parts.qty_issued) - COALESCE(warehouse_parts.qty, 0), 0) as qty_to_import'),
            ])
            ->havingRaw('SUM(job_parts.qty - job_parts.qty_issued) > COALESCE(warehouse_parts.qty, 0)')
            ->orderByDesc('qty_to_import');

        return response()->json($query->paginate($request->per_page ?? 20));
    }
}

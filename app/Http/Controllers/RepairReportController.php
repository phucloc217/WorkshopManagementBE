<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RepairReportController extends Controller
{
    // Lịch sử sửa chữa theo thời gian + trạng thái
    public function history(Request $request)
    {
        $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = JobOrder::query()
            ->select([
                'id',
                'order_no',
                'customer_id',
                'vehicle_id',
                'workshop_id',
                'overall_status',
                'type',
                'created_at',
                'delivered_date'
            ])
            ->with([
                'customer:id,name',
                'vehicle:id,motor_number,model',
                'workshop:id,name'
            ])
            ->when($request->workshop_id, fn($q) => $q->where('workshop_id', $request->workshop_id))
            ->when($request->status, fn($q) => $q->where('overall_status', $request->status))
            ->when($request->from && $request->to, function ($q) use ($request) {
                $q->whereBetween('created_at', [
                    $request->from . ' 00:00:00',
                    $request->to . ' 23:59:59'
                ]);
            })
            ->latest();

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    // Thống kê số lượng xe theo ngày hoặc tháng
    public function statistics(Request $request)
    {
        $request->validate([
            'group_by' => 'required|in:day,month',
            'from'     => 'required|date',
            'to'       => 'required|date',
        ]);

        $dateFormat = $request->group_by === 'day'
            ? "TO_CHAR(created_at, 'DD/MM/YYYY')"
            : "TO_CHAR(created_at, 'MM/YYYY')";

        $orderFormat = $request->group_by === 'day'
            ? "TO_CHAR(created_at, 'YYYY-MM-DD')"
            : "TO_CHAR(created_at, 'YYYY-MM')";

        $query = JobOrder::query()
            ->when($request->workshop_id, fn($q) => $q->where('workshop_id', $request->workshop_id))
            ->whereBetween('created_at', [
                $request->from . ' 00:00:00',
                $request->to . ' 23:59:59'
            ])
            ->select([
                DB::raw("$dateFormat as period"),
                DB::raw("$orderFormat as sort_key"),
                DB::raw("COUNT(*) as total"),
                DB::raw("COUNT(*) FILTER (WHERE overall_status = 'Đã Giao Xe') as delivered"),
                DB::raw("COUNT(*) FILTER (WHERE overall_status = 'Đã Hoàn Thành') as completed"),
                DB::raw("COUNT(*) FILTER (WHERE overall_status IN ('Mới Tiếp Nhận', 'Đang Sửa Chữa', 'Chờ Phụ Tùng')) as in_progress"),
            ])
            ->groupBy(DB::raw($dateFormat), DB::raw($orderFormat))
            ->orderBy('sort_key');

        $data = $query->get();

        // Tổng quan
        $summary = [
            'total'       => $data->sum('total'),
            'delivered'   => $data->sum('delivered'),
            'completed'   => $data->sum('completed'),
            'in_progress' => $data->sum('in_progress'),
        ];

        return response()->json([
            'summary' => $summary,
            'data'    => $data
        ]);
    }
}

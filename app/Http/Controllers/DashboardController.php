<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobOrder;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // DashboardController.php
    public function index(Request $request)
    {
        $user = auth()->user();

        // Base query đã filter theo quyền workshop
        $base = fn() => JobOrder::accessibleBy($user);

        $today = now()->startOfDay();

        // 4 card số liệu
        $cards = [
            'today_received' => $base()->where('created_at', '>=', $today)->count(),
            'in_progress'    => $base()->whereIn('overall_status', ['Mới Tiếp Nhận', 'Đang Sửa Chữa', 'Chờ Phụ Tùng'])->count(),
            'wait_delivery'  => $base()->where('overall_status', 'Đã Hoàn Thành')->count(),
            'low_stock'      => DB::table('warehouse_parts')
                ->join('parts', 'parts.id', '=', 'warehouse_parts.part_id')
                ->when(
                    !$user->hasRole('admin'),
                    fn($q) =>
                    $q->whereIn('warehouse_parts.warehouse_id', $user->warehouseIds())
                )
                ->whereColumn('warehouse_parts.qty', '<=', 'parts.min_qty')
                ->count(),
        ];

        // Chart 7 ngày: tiếp nhận theo ngày (cho sparkline card 1)
        $last7days = $base()
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->select(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD') as d"), DB::raw('COUNT(*) as total'))
            ->groupBy('d')->orderBy('d')->pluck('total', 'd');

        // Bar chart theo tuần (7 ngày) — tiếp nhận vs giao
        $received = [];
        $delivered = [];
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $days[] = $day->format('d/m');
            $received[] = $base()->whereDate('created_at', $day)->count();
            $delivered[] = $base()->whereDate('delivered_date', $day)->count();
        }

        // Pie: phân bố trạng thái
        $statusDist = $base()
            ->select('overall_status', DB::raw('COUNT(*) as total'))
            ->groupBy('overall_status')
            ->get();

        // Phiếu mới nhất
        $latest = $base()
            ->with(['customer:id,name', 'vehicle:id,motor_number', 'workshop:id,name'])
            ->latest()->limit(8)->get();

        return response()->json([
            'cards'       => $cards,
            'week_chart'  => ['days' => $days, 'received' => $received, 'delivered' => $delivered],
            'status_dist' => $statusDist,
            'latest'      => $latest,
        ]);
    }
}

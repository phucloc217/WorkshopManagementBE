<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use App\Models\Vehicle;
use App\Models\Customer;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobOrderController extends Controller
{
    /**
     * Danh sách phiếu sửa chữa
     */
    public function index(Request $request)
    {
        try {
            $query = JobOrder::query()
                ->accessibleBy(auth()->user())
                ->select([
                    'id',
                    'order_no',
                    'customer_id',
                    'vehicle_id',
                    'workshop_id',
                    'overall_status',
                    'type',
                    'issue_description',
                    'created_at'
                ])
                ->with([
                    'customer:id,name',
                    'vehicle:id,motor_number,vin,model',
                    'workshop:id,name'
                ])
                ->withCount('tasks')
                ->when($request->workshop_id, fn($q) => $q->where('workshop_id', $request->workshop_id))
                ->when($request->statuses, fn($q) => $q->whereIn('overall_status', $request->statuses))
                ->when($request->has_parts, function ($q) {
                    $q->whereHas('parts', function ($q) {
                        $q->whereColumn('qty_issued', '<', 'qty');
                    });
                })
                ->when($request->search, function ($q) use ($request) {
                    $search = $request->search;
                    $q->where(function ($q) use ($search) {
                        $q->where('order_no', 'ILIKE', "%{$search}%")
                            ->orWhereHas('customer', fn($q) => $q->where('name', 'ILIKE', "%{$search}%"))
                            ->orWhereHas('vehicle', function ($q) use ($search) {
                                $q->where('motor_number', 'ILIKE', "%{$search}%")
                                    ->orWhere('vin', 'ILIKE', "%{$search}%");
                            });
                    });
                })
                ->latest();

            return response()->json(
                $query->paginate($request->per_page ?? 20)
            );
        } catch (\Throwable $e) {
            \Log::error('JobOrder index error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Không thể tải danh sách phiếu sửa chữa',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function create()
    {
        //
    }

    /**
     * Tạo phiếu sửa chữa mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'phone'        => 'required',
            'name'         => 'required',
            'motor_number' => 'required',
            'workshop_id'  => 'required|exists:workshops,id',
            'type'         => 'required',
            'vin'          => 'nullable|string',
            'odo'          => 'nullable|integer',
            'description'  => 'nullable|string'
        ]);

        if (!auth()->user()->canAccessWorkshop($request->workshop_id)) {
            abort(403, 'Bạn không có quyền tạo phiếu cho xưởng này');
        }

        $jobOrder = DB::transaction(function () use ($request) {
            $customer = Customer::firstOrCreate(
                ['phone' => $request->phone],
                ['name'  => $request->name]
            );

            $vehicle = Vehicle::firstOrCreate(
                ['motor_number' => $request->motor_number],
                ['vin'          => $request->vin]
            );

            $issueDescription = $request->type === 'Xe GSM'
                ? implode(PHP_EOL, $request->issue_description ?? [])
                : implode('; ', $request->issue_description ?? []);

            return JobOrder::create([
                'vehicle_id'        => $vehicle->id,
                'customer_id'       => $customer->id,
                'workshop_id'       => $request->workshop_id,
                'type'              => $request->type,
                'issue_description' => $issueDescription,
                'order_no'          => $this->generateOrderNo(),
                'created_by'        => auth()->id(),
                'odo'               => $request->odo,
            ]);
        });

        return response()->json($jobOrder, 201);
    }

    /**
     * Chi tiết phiếu sửa chữa (kèm nhật ký)
     */
    public function show(string $id)
    {
        $jobOrder = JobOrder::with([
            'customer:id,name,phone',
            'vehicle:id,motor_number,vin,model',
            'workshop:id,name',
            'tasks' => fn($q) => $q->orderBy('created_at'),
            'createdBy:id,name'
        ])->findOrFail($id);

        if (!auth()->user()->canAccessWorkshop($jobOrder->workshop_id)) {
            abort(403, 'Bạn không có quyền truy cập xưởng này');
        }

        // Tạo log từ các trường có sẵn
        $logs = collect();

        // Log tạo phiếu
        $logs->push([
            'time'       => $jobOrder->created_at?->format('d/m/Y H:i'),
            'created_by' => $jobOrder->createdBy?->name,
            'content'    => 'Tạo phiếu sửa chữa'
        ]);

        // Log từng task
        foreach ($jobOrder->tasks as $task) {
            $logs->push([
                'time'       => $task->created_at?->format('d/m/Y H:i'),
                'created_by' => $task->createdBy?->name,
                'content'    => "Tạo công việc: {$task->task_name}"
            ]);

            if ($task->started_at) {
                $logs->push([
                    'time'       => $task->started_at?->format('d/m/Y H:i'),
                    'created_by' => $task->startedBy?->name,
                    'content'    => "Bắt đầu: {$task->task_name}"
                ]);
            }

            if ($task->completed_at) {
                $logs->push([
                    'time'       => $task->completed_at?->format('d/m/Y H:i'),
                    'created_by' => $task->completedBy?->name,
                    'content'    => "Hoàn thành: {$task->task_name}"
                ]);
            }
        }

        // Log hoàn thành sửa chữa
        if ($jobOrder->completed_at) {
            $logs->push([
                'time'       => $jobOrder->completed_at?->format('d/m/Y H:i'),
                'created_by' => $jobOrder->completedBy?->name,
                'content'    => 'Hoàn thành sửa chữa'
            ]);
        }

        // Log giao xe
        if ($jobOrder->delivered_date) {
            $logs->push([
                'time'       => $jobOrder->delivered_date?->format('d/m/Y H:i'),
                'created_by' => $jobOrder->deliveredBy?->name,
                'content'    => 'Giao xe cho khách'
            ]);
        }

        return response()->json([
            ...$jobOrder->toArray(),
            'logs' => $logs->sortByDesc('time')->values()
        ]);
    }

    public function edit(JobOrder $jobOrder)
    {
        //
    }

    /**
     * Cập nhật thông tin phiếu và xe
     */
    public function update(Request $request, JobOrder $jobOrder)
    {
        if (!auth()->user()->canAccessWorkshop($jobOrder->workshop_id)) {
            abort(403, 'Bạn không có quyền truy cập xưởng này');
        }

        $request->validate([
            'vehicle_id'        => 'required|exists:vehicles,id',
            'vehicle.vin'       => 'nullable|string|max:100',
            'vehicle.model'     => 'nullable|string|max:255',
            'issue_description' => 'nullable|string'
        ]);

        DB::transaction(function () use ($request, $jobOrder) {
            $vehicle = Vehicle::findOrFail($request->vehicle_id);

            $vehicle->update([
                'vin'   => $request->input('vehicle.vin'),
                'model' => $request->input('vehicle.model')
            ]);

            $jobOrder->update([
                'issue_description' => $request->issue_description
            ]);
        });

        return $jobOrder->fresh();
    }

    public function destroy(JobOrder $jobOrder)
    {
        //
    }

    /**
     * Kết thúc sửa chữa
     */
    public function finish(JobOrder $jobOrder)
    {
        if (!auth()->user()->canAccessWorkshop($jobOrder->workshop_id)) {
            abort(403, 'Bạn không có quyền truy cập xưởng này');
        }

        // Kiểm tra còn công việc chưa hoàn thành không
        $hasUnfinishedTask = $jobOrder->tasks()
            ->where('status', '!=', 'Hoàn Thành')
            ->exists();

        if ($hasUnfinishedTask) {
            return response()->json([
                'message' => 'Vẫn còn công việc chưa hoàn thành.'
            ], 422);
        }

        if ($jobOrder->overall_status === 'Hoàn Thành') {
            return response()->json([
                'message' => 'Phiếu sửa chữa đã được hoàn thành.'
            ], 422);
        }

        $jobOrder->update([
            'overall_status' => 'Hoàn Thành',
            'completed_at'   => now(),
            'completed_by'   => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Kết thúc sửa chữa thành công.'
        ]);
    }

    /**
     * Giao xe 1 phiếu
     */
    public function deliver(JobOrder $jobOrder)
    {
        if (!auth()->user()->canAccessWorkshop($jobOrder->workshop_id)) {
            abort(403, 'Bạn không có quyền truy cập xưởng này');
        }

        if ($jobOrder->overall_status !== 'Hoàn Thành') {
            return response()->json([
                'message' => 'Chỉ giao được xe đã hoàn thành sửa chữa'
            ], 422);
        }

        $jobOrder->update([
            'overall_status' => 'Đã Giao Xe',
            'delivered_date' => now(),
            'delivered_by'   => auth()->id(),
        ]);

        return response()->json(['message' => 'Giao xe thành công']);
    }

    /**
     * Import danh sách xe đã giao từ Excel (theo biển số hoặc mã phiếu)
     */
    public function importDelivered(Request $request)
    {
        $request->validate([
            'rows'              => 'required|array|min:1',
            'rows.*.identifier' => 'required|string',
        ]);

        $user    = auth()->user();
        $errors  = [];
        $success = 0;

        foreach ($request->rows as $index => $row) {
            $identifier = trim($row['identifier']);

            $jobOrder = JobOrder::query()
                ->where('overall_status', 'Hoàn Thành')
                ->where(function ($q) use ($identifier) {
                    $q->where('order_no', $identifier)
                        ->orWhereHas('vehicle', fn($q) => $q->where('motor_number', $identifier));
                })
                ->latest('created_at')
                ->first();

            if (!$jobOrder) {
                $errors[] = [
                    'row'        => $index + 2,
                    'identifier' => $identifier,
                    'message'    => "Không tìm thấy phiếu hoàn thành cho '{$identifier}'"
                ];
                continue;
            }

            if (!$user->canAccessWorkshop($jobOrder->workshop_id)) {
                $errors[] = [
                    'row'        => $index + 2,
                    'identifier' => $identifier,
                    'message'    => "Không có quyền giao xe của xưởng này"
                ];
                continue;
            }

            $jobOrder->update([
                'overall_status' => 'Đã Giao Xe',
                'delivered_date' => now(),
                'delivered_by'   => $user->id,
            ]);
            $success++;
        }

        return response()->json([
            'message' => "Đã giao {$success} xe",
            'success' => $success,
            'errors'  => $errors
        ]);
    }

    /**
     * Danh sách phiếu có công việc bảo hành
     */
    public function warrantyOrders(Request $request)
    {
        try {
            $query = JobOrder::query()
                ->accessibleBy(auth()->user())
                ->select([
                    'id',
                    'order_no',
                    'customer_id',
                    'vehicle_id',
                    'workshop_id',
                    'overall_status',
                    'created_at'
                ])
                ->with([
                    'customer:id,name,phone',
                    'vehicle:id,motor_number,model',
                    'workshop:id,name',
                    // Chỉ load task bảo hành
                    'tasks' => fn($q) => $q->where('is_warranty', true)
                        ->with([
                            'createdBy:id,name',
                            'startedBy:id,name',
                            'completedBy:id,name'
                        ])
                        ->orderBy('created_at'),
                ])
                // Chỉ lấy phiếu có ít nhất 1 task bảo hành
                ->whereHas('tasks', fn($q) => $q->where('is_warranty', true))
                // Loại phiếu đã giao xe, còn lại đều theo dõi
                ->whereNotIn('overall_status', ['Đã Giao Xe'])
                ->when($request->workshop_id, fn($q) => $q->where('workshop_id', $request->workshop_id))
                ->when($request->search, function ($q) use ($request) {
                    $search = $request->search;
                    $q->where(function ($q) use ($search) {
                        $q->where('order_no', 'ILIKE', "%{$search}%")
                            ->orWhereHas('vehicle', fn($q) => $q->where('motor_number', 'ILIKE', "%{$search}%"))
                            ->orWhereHas('customer', fn($q) => $q->where('name', 'ILIKE', "%{$search}%"));
                    });
                })
                // Phiếu còn task bảo hành chưa xong lên đầu
                ->orderByRaw("(SELECT COUNT(*) FROM job_tasks WHERE job_tasks.job_order_id = job_orders.id AND job_tasks.is_warranty = true AND job_tasks.status != 'Hoàn Thành') DESC")
                ->latest();

            return response()->json($query->paginate($request->per_page ?? 20));
        } catch (\Throwable $e) {
            \Log::error('Warranty orders error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Không thể tải danh sách bảo hành',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Sinh mã phiếu: SC/ddmmyy/0001
     */
    private function generateOrderNo(): string
    {
        $today  = Carbon::now()->format('dmy');
        $prefix = "SC/{$today}/";

        $lastOrder = JobOrder::where('order_no', 'like', "{$prefix}%")
            ->orderByDesc('order_no')
            ->first();

        $sequence = $lastOrder
            ? (int) substr($lastOrder->order_no, -4) + 1
            : 1;

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\JobTask;
use App\Models\JobOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\TaskRequest;
use Illuminate\Http\Request;

class JobTaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TaskRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['status'] = "Mới Tạo";
        return JobTask::create($data);
    }

    /**
     * Display the specified resource.
     */
    public function show(JobTask $jobTask)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(JobTask $jobTask)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, JobTask $jobTask) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JobTask $jobTask)
    {
        if ($jobTask->status !== 'Mới Tạo') {
            return response()->json([
                'message' => 'Chỉ được xóa công việc ở trạng thái Mới Tạo.'
            ], 422);
        }

        $jobOrder = $jobTask->jobOrder;

        $jobTask->delete();

        // Nếu cần thì cập nhật lại trạng thái phiếu
        // $this->updateOverallStatus($jobOrder);

        return response()->json([
            'message' => 'Xóa công việc thành công.'
        ]);
    }

    public function getTasksByOrderId($jobOrderId)
    {
        $jobOrder = JobOrder::findOrFail($jobOrderId);

        return response()->json($jobOrder->tasks);
    }
    public function startTask(JobTask $jobTask)
    {
        if ($jobTask->status === 'Đang Thực Hiện') {
            return response()->json([
                'message' => 'Công việc đã được bắt đầu'
            ], 422);
        }

        $jobTask->update([
            'status' => 'Đang Thực Hiện',
            'started_at' => now(),
            'started_by' => auth()->id()
        ]);

        $jobTask->jobOrder()->update([
            'overall_status' => 'Đang Thực Hiện'
        ]);

        return response()->json([
            'message' => 'Bắt đầu công việc thành công'
        ]);
    }
    public function completeTask(JobTask $jobTask)
    {
        if ($jobTask->status === 'Hoàn Thành') {
            return response()->json([
                'message' => 'Công việc đã được hoàn thành'
            ], 422);
        }

        $jobTask->update([
            'status' => 'Hoàn Thành',
            'completed_at' => now(),
            'completed_by' => auth()->id()
        ]);

        return response()->json([
            'message' => 'Đã Hoàn Thành Công Việc'
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->has('pageSize')) {
            return response()->json(Service::orderBy('service_name')->get());
        }
        $query = Service::query()
            ->when(
                $request->filled('service_name'),
                fn($q) =>
                $q->where('service_name', 'ILIKE', "%{$request->service_name}%")
            )
            ->orderBy('service_name');

        $pageSize = $request->input('pageSize', 10);
        $currentPage = $request->input('currentPage', 1);

        $result = $query->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json([
            'code' => 200,
            'data' => [
                'list' => $result->items(),
                'total' => $result->total(),
                'pageSize' => $result->perPage(),
                'currentPage' => $result->currentPage()
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'service_name'   => 'required|string|unique:services,service_name',
            'estimated_days' => 'nullable|numeric|min:0',
        ]);

        $service = Service::create($request->only('service_name', 'estimated_days'));

        return response()->json([
            'code' => 200,
            'message' => 'Tạo dịch vụ thành công',
            'data' => $service
        ], 201);
    }

    public function update(Request $request, Service $service)
    {
        $request->validate([
            'service_name'   => "required|string|unique:services,service_name,{$service->id}",
            'estimated_days' => 'nullable|numeric|min:0',
        ]);

        $service->update($request->only('service_name', 'estimated_days'));

        return response()->json(['code' => 200, 'message' => 'Cập nhật dịch vụ thành công']);
    }

    public function destroy(Service $service)
    {
        // Chặn xóa nếu đã được dùng trong task
        $inUse = \App\Models\JobTask::where('task_name', $service->service_name)->exists();
        if ($inUse) {
            return response()->json([
                'message' => 'Dịch vụ đã được sử dụng trong phiếu sửa chữa, không thể xóa'
            ], 422);
        }

        $service->delete();

        return response()->json(['code' => 200, 'message' => 'Xóa dịch vụ thành công']);
    }
}

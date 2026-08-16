<?php

namespace App\Http\Controllers;

use App\Models\VehicleType;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleTypeController extends Controller
{
    public function index(Request $request)
    {
        // Không truyền pageSize → trả toàn bộ loại xe đang dùng (cho form tiếp nhận)
        if (!$request->has('pageSize')) {
            return response()->json(
                VehicleType::where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
            );
        }

        $query = VehicleType::query()
            ->when($request->filled('name'), fn($q) =>
                $q->where('name', 'ILIKE', "%{$request->name}%")
            )
            ->orderBy('sort_order')
            ->orderBy('name');

        $result = $query->paginate(
            $request->input('pageSize', 10),
            ['*'],
            'page',
            $request->input('currentPage', 1)
        );

        return response()->json([
            'code' => 200,
            'data' => [
                'list'        => $result->items(),
                'total'       => $result->total(),
                'pageSize'    => $result->perPage(),
                'currentPage' => $result->currentPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|unique:vehicle_types,name',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'boolean',
        ]);

        $vehicleType = VehicleType::create(
            $request->only('name', 'sort_order', 'is_active')
        );

        return response()->json([
            'code'    => 200,
            'message' => 'Thêm loại xe thành công',
            'data'    => $vehicleType,
        ], 201);
    }

    public function update(Request $request, VehicleType $vehicleType)
    {
        $request->validate([
            'name'       => "required|string|unique:vehicle_types,name,{$vehicleType->id}",
            'sort_order' => 'nullable|integer',
            'is_active'  => 'boolean',
        ]);

        $oldName = $vehicleType->name;

        $vehicleType->update($request->only('name', 'sort_order', 'is_active'));

        // Đồng bộ tên loại xe sang các xe đang dùng tên cũ
        if ($oldName !== $vehicleType->name) {
            Vehicle::where('model', $oldName)->update(['model' => $vehicleType->name]);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Cập nhật loại xe thành công',
        ]);
    }

    public function destroy(VehicleType $vehicleType)
    {
        // vehicles.model lưu tên loại xe dạng text
        if (Vehicle::where('model', $vehicleType->name)->exists()) {
            return response()->json([
                'message' => 'Loại xe đang được sử dụng, không thể xóa. Hãy chuyển sang Ngưng dùng.'
            ], 422);
        }

        $vehicleType->delete();

        return response()->json([
            'code'    => 200,
            'message' => 'Xóa loại xe thành công',
        ]);
    }
}
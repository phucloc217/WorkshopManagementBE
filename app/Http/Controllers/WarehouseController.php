<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Warehouse::accessibleBy(auth()->user())
            ->with('workshop:id,name')
            ->select(['id', 'name', 'description', 'is_active', 'workshop_id'])
            ->latest()
            ->get();
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
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|unique:warehouses,name',
            'description' => 'nullable|string',
            'workshop_id' => 'required|exists:workshops,id',
            'is_active'   => 'boolean',
        ]);

        $warehouse = Warehouse::create($request->only('name', 'description', 'workshop_id', 'is_active'));

        return response()->json(['code' => 200, 'message' => 'Tạo kho thành công', 'data' => $warehouse], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Warehouse $warehouse)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Warehouse $warehouse)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        $request->validate([
            'name'        => "required|string|unique:warehouses,name,{$warehouse->id}",
            'description' => 'nullable|string',
            'workshop_id' => 'required|exists:workshops,id',
            'is_active'   => 'boolean',
        ]);

        $warehouse->update($request->only('name', 'description', 'workshop_id', 'is_active'));

        return response()->json(['code' => 200, 'message' => 'Cập nhật kho thành công']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Warehouse $warehouse)
    {
        $inUse = \App\Models\WarehouseParts::where('warehouse_id', $warehouse->id)->where('qty', '>', 0)->exists()
            || \App\Models\StockReceipt::where('warehouse_id', $warehouse->id)->exists();

        if ($inUse) {
            return response()->json(['message' => 'Kho đã có dữ liệu tồn/phiếu nhập, không thể xóa'], 422);
        }

        $warehouse->delete();

        return response()->json(['code' => 200, 'message' => 'Xóa kho thành công']);
    }
}

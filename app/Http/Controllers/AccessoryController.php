<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use Illuminate\Http\Request;

class AccessoryController extends Controller
{
    public function index(Request $request)
    {
        // Không có pageSize → trả hết (dùng cho dropdown form tiếp nhận)
        if (!$request->has('pageSize')) {
            return response()->json(
                Accessory::where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
            );
        }

        $query = Accessory::query()
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
            'name'       => 'required|string|unique:accessories,name',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'boolean',
        ]);

        $accessory = Accessory::create($request->only('name', 'sort_order', 'is_active'));

        return response()->json([
            'code' => 200,
            'message' => 'Thêm phụ kiện thành công',
            'data' => $accessory
        ], 201);
    }

    public function update(Request $request, Accessory $accessory)
    {
        $request->validate([
            'name'       => "required|string|unique:accessories,name,{$accessory->id}",
            'sort_order' => 'nullable|integer',
            'is_active'  => 'boolean',
        ]);

        $accessory->update($request->only('name', 'sort_order', 'is_active'));

        return response()->json(['code' => 200, 'message' => 'Cập nhật phụ kiện thành công']);
    }

    public function destroy(Accessory $accessory)
    {
        $accessory->delete();

        return response()->json(['code' => 200, 'message' => 'Xóa phụ kiện thành công']);
    }
}
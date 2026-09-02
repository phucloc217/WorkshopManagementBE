<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkshopRequest;
use Illuminate\Http\Request;

class WorkshopController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    //hàm index để lấy danh sách các chi nhánh (workshop) dựa trên các điều kiện lọc từ request. Nó kiểm tra quyền truy cập của người dùng và áp dụng các bộ lọc như tên chi nhánh, trạng thái hoạt động và khả năng sửa chữa. Kết quả được sắp xếp theo tên và trả về dưới dạng JSON.
    public function index(Request $request)
    {
        $user = auth()->user();

        // Trang quản trị: bỏ qua lọc quyền dữ liệu, cần quyền workshop.manage
        $bypassFilter = $request->boolean('all') && $user->can('workshop.manage');

        $query = $bypassFilter
            ? Workshop::query()
            : Workshop::accessibleBy($user);

        if ($request->filled('name')) {
            $query->where('name', 'ILIKE', '%' . $request->name . '%');
        }

        if (!is_null($request->is_active)) {
            $query->where('is_active', $request->is_active);
        }

        // Chỉ chi nhánh tham gia sửa chữa — dùng cho form tiếp nhận
        if ($request->boolean('for_repair')) {
            $query->where('is_repair_enabled', true)->where('is_active', true);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(WorkshopRequest $request)
    {
        $data = $request->validated();

        $data['workshop_code'] = strtoupper($data['workshop_code']);
        $workshop = Workshop::create($data);
        return response()->json($workshop, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Workshop $workshop)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Workshop $workshop)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(WorkshopRequest $request, Workshop $workshop)
    {
        $validated = $request->validated();
        $validated['workshop_code'] = strtoupper($validated['workshop_code']);
        $workshop->update($validated);

        return response()->json([
            'message' => 'Câp nhật chi nhánh thành công',
            'data' => $workshop
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Workshop $workshop)
    {
        if (!$workshop) {
            return response()->json([
                'message' => 'Chi nhánh không tồn tại'
            ], 404);
        }

        $workshop->delete();

        return response()->json([
            'message' => 'Chi nhánh đã được xóa thành công'
        ]);
    }
}

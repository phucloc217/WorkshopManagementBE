<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\ChangeUserStatusRequest;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $query = User::with('workshop:id,name');

        if ($request->filled('name')) {
            $query->where('name', 'ILIKE', '%' . $request->name . '%');
        }

        if (!is_null($request->is_active)) {
            $query->where('is_active', $request->is_active);
        }

        $pageSize = $request->input('pageSize', 10);
        $currentPage = $request->input('currentPage', 1);

        $result = $query->paginate(
            $pageSize,
            ['*'],
            'page',
            $currentPage
        );

        return response()->json([
            'code' => 200,
            'message' => 'Success',
            'data' => [
                'list' => $result->items(),
                'total' => $result->total(),
                'pageSize' => $result->perPage(),
                'currentPage' => $result->currentPage()
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = bcrypt($data['password']);
        // $user = User::create($data);
        return User::create($data);;
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserRequest $request, User $user)
    {
        $validated = $request->validated();
        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }
        $user->update($validated);

        return response()->json([
            'message' => 'Câp nhật người dùng thành công',
            'data' => $user
        ]);
    }
    public function changePassword(UpdatePasswordRequest $request, User $user)
    {
        $validated = $request->validated();
        $validated['password'] = bcrypt($validated['password']);
        $user->update($validated);

        return response()->json([
            'message' => 'Câp nhật mật khẩu thành công',
            'data' => $user
        ]);
    }
    public function changeStatus(ChangeUserStatusRequest $request, User $user)
    {
        $validated = $request->validated();
        $user->update($validated);

        return response()->json([
            'message' => 'Câp nhật trạng thái người dùng thành công',
            'data' => $user
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        if (!$user) {
            return response()->json([
                'message' => 'Người dùng không tồn tại'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'message' => 'Người dùng đã được xóa thành công'
        ]);
    }

    // Lấy roles hiện tại của user
    public function userRoles(User $user)
    {
        return response()->json([
            'code' => 200,
            'data' => $user->roles->pluck('id')
        ]);
    }

    // Gán roles cho user
    public function syncRoles(Request $request, User $user)
    {
        $request->validate([
            'role_ids'   => 'array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $roles = \Spatie\Permission\Models\Role::whereIn('id', $request->role_ids ?? [])->get();
        $user->syncRoles($roles);

        return response()->json(['code' => 200, 'message' => 'Cập nhật vai trò thành công']);
    }
    // UserController
    public function userDataAccess(User $user)
    {
        return response()->json([
            'code' => 200,
            'data' => [
                'workshop_ids'  => $user->workshops()->pluck('workshops.id'),
                'warehouse_ids' => $user->warehouses()->pluck('warehouses.id'),
            ]
        ]);
    }

    public function syncDataAccess(Request $request, User $user)
    {
        $request->validate([
            'workshop_ids'    => 'array',
            'workshop_ids.*'  => 'exists:workshops,id',
            'warehouse_ids'   => 'array',
            'warehouse_ids.*' => 'exists:warehouses,id',
        ]);

        $user->workshops()->sync($request->workshop_ids ?? []);
        $user->warehouses()->sync($request->warehouse_ids ?? []);

        return response()->json(['code' => 200, 'message' => 'Cập nhật quyền dữ liệu thành công']);
    }
}

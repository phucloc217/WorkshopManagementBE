<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\ChangeUserStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

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
    /**
     * Thông tin tài khoản đang đăng nhập
     */
    public function me()
    {
        $user = auth()->user()->load('workshop:id,name');

        return response()->json([
            'code' => 200,
            'data' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'phone'    => $user->phone,
                'workshop' => $user->workshop,
                'roles'    => $user->getRoleNames(),
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Cập nhật thông tin cá nhân
     *
     * Chỉ cho sửa họ tên. Số điện thoại là tài khoản đăng nhập nên
     * phải do quản trị viên đổi để tránh mất quyền truy cập.
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = auth()->user();
        $user->update(['name' => $request->name]);

        return response()->json([
            'code'    => 200,
            'message' => 'Cập nhật thông tin thành công',
            'data'    => [
                'name'  => $user->name,
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * Tự đổi mật khẩu — bắt buộc nhập mật khẩu hiện tại
     */
    public function changeMyPassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'string', 'confirmed', Password::min(6)],
        ], [
            'password.confirmed' => 'Xác nhận mật khẩu không khớp',
            'password.min'       => 'Mật khẩu phải có ít nhất 6 ký tự',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Mật khẩu hiện tại không đúng',
            ], 422);
        }

        if (Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Mật khẩu mới phải khác mật khẩu hiện tại',
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Thu hồi toàn bộ token cũ, buộc đăng nhập lại trên mọi thiết bị
        $user->tokens()->delete();

        return response()->json([
            'code'    => 200,
            'message' => 'Đổi mật khẩu thành công, vui lòng đăng nhập lại',
        ]);
    }
}

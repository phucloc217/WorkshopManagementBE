<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $query = Role::query()
            ->when($request->filled('name'), fn($q) => $q->where('name', 'ILIKE', "%{$request->name}%"));

        $pageSize = $request->input('pageSize', 10);
        $currentPage = $request->input('currentPage', 1);

        $result = $query->paginate($pageSize, ['*'], 'page', $currentPage);

        // Đếm users theo role qua bảng model_has_roles
        $roleIds = collect($result->items())->pluck('id');
        $counts = \DB::table('model_has_roles')
            ->whereIn('role_id', $roleIds)
            ->select('role_id', \DB::raw('count(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $list = collect($result->items())->map(function ($role) use ($counts) {
            $role->users_count = $counts[$role->id] ?? 0;
            return $role;
        });

        return response()->json([
            'code' => 200,
            'data' => [
                'list' => $list,
                'total' => $result->total(),
                'pageSize' => $result->perPage(),
                'currentPage' => $result->currentPage()
            ]
        ]);
    }

    // Danh sách tất cả permissions (cho cây bên phải)
    public function permissions()
    {
        return response()->json([
            'code' => 200,
            'data' => Permission::all(['id', 'name'])
        ]);
    }

    // Permissions hiện tại của 1 role
    public function rolePermissions(Role $role)
    {
        return response()->json([
            'code' => 200,
            'data' => $role->permissions->pluck('id')
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|unique:roles,name']);

        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);

        return response()->json(['code' => 200, 'message' => 'Tạo vai trò thành công', 'data' => $role], 201);
    }

    public function update(Request $request, Role $role)
    {
        $request->validate(['name' => "required|unique:roles,name,{$role->id}"]);

        $role->update(['name' => $request->name]);

        return response()->json(['code' => 200, 'message' => 'Cập nhật vai trò thành công']);
    }

    // Gán permissions cho role
    public function syncPermissions(Request $request, Role $role)
    {
        $request->validate([
            'permission_ids'   => 'array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $permissions = Permission::whereIn('id', $request->permission_ids ?? [])->get();
        $role->syncPermissions($permissions);

        return response()->json(['code' => 200, 'message' => 'Cập nhật quyền thành công']);
    }

    public function destroy(Role $role)
    {
        if ($role->name === 'admin') {
            return response()->json(['message' => 'Không thể xóa vai trò admin'], 422);
        }
        if ($role->users()->count() > 0) {
            return response()->json(['message' => 'Vai trò đang được gán cho người dùng, không thể xóa'], 422);
        }

        $role->delete();

        return response()->json(['code' => 200, 'message' => 'Xóa vai trò thành công']);
    }
}

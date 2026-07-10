<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Xóa cache quyền cũ
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Sửa chữa
            'repair.view',
            'repair.create',
            'repair.update',
            'repair.delete',
            'repair.finish',
            'repair.deliver',

            // Nhập kho
            'stock-receipt.view',
            'stock-receipt.create',
            'stock-receipt.confirm',
            'stock-receipt.delete',

            // Xuất kho
            'stock-issue.view',
            'stock-issue.create',
            'stock-issue.confirm',
            'stock-issue.delete',

            // Luân chuyển
            'stock-transfer.view',
            'stock-transfer.create',
            'stock-transfer.transfer',
            'stock-transfer.receive',
            'stock-transfer.delete',

            // Báo cáo
            'report.inventory',
            'report.history',
            'report.parts-to-import',

            // Quản lý
            'warehouse.manage',
            'workshop.manage',
            'user.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        $warehouseKeeper = Role::firstOrCreate(['name' => 'warehouse-keeper']);
        $warehouseKeeper->givePermissionTo([
            'stock-receipt.view',
            'stock-receipt.create',
            'stock-receipt.confirm',
            'stock-issue.view',
            'stock-issue.create',
            'stock-issue.confirm',
            'stock-transfer.view',
            'stock-transfer.create',
            'stock-transfer.transfer',
            'stock-transfer.receive',
            'report.inventory',
            'report.history',
        ]);

        $technician = Role::firstOrCreate(['name' => 'technician']);
        $technician->givePermissionTo([
            'repair.view',
            'repair.update',
        ]);
    }
}

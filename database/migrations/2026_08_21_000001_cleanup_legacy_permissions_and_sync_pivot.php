<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Daftar permission legacy/orphan yang tidak lagi dipakai di codebase
        $legacyPermissions = [
            'sarpras.manage',
            'pengadaan.manage',
        ];

        foreach ($legacyPermissions as $permName) {
            $permission = Permission::where('name', $permName)->first();
            if ($permission) {
                // Spatie otomatis membersihkan pivot model_has_permissions & role_has_permissions
                $permission->delete();
            }
        }

        // Reset cache permission Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Recreate legacy permissions jika rollback
        $legacyPermissions = [
            'sarpras.manage',
            'pengadaan.manage',
        ];

        foreach ($legacyPermissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

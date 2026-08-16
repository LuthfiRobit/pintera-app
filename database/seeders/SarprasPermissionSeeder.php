<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class SarprasPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'sarpras.gedung.view',
            'sarpras.gedung.manage',
            'sarpras.ruangan.view',
            'sarpras.ruangan.manage',
            'sarpras.kategori.view',
            'sarpras.kategori.manage',
            'sarpras.aset.view',
            'sarpras.aset.manage',
            'sarpras.mutasi.create',
            'sarpras.mutasi.view',
            'sarpras.kir.export',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}

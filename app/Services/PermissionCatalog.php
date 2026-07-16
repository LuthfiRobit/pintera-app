<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;

class PermissionCatalog
{
    private const MODULE_LABELS = [
        'roles' => 'Roles',
        'users' => 'Pengguna',
        'lembaga' => 'Lembaga',
        'guru' => 'Guru',
        'tahun-ajaran' => 'Tahun Ajaran',
        'semester' => 'Semester',
        'jenis-tes' => 'Jenis Tes',
        'gelombang-ppdb' => 'Gelombang PPDB',
        'jalur-ppdb' => 'Jalur PPDB',
        'formulir-field' => 'Formulir Field',
        'dokumen-syarat' => 'Dokumen Syarat',
        'seleksi' => 'Seleksi',
        'spmb-konfigurasi' => 'Konfigurasi SPMB',
        'spmb-pendaftaran' => 'Verifikasi & Keputusan SPMB',
        'audit-log' => 'Log Aktivitas',
        'jenis-tagihan' => 'Jenis Tagihan',
        'tagihan' => 'Tagihan',
    ];

    private const ACTION_LABELS = [
        'view' => 'Lihat',
        'create' => 'Tambah',
        'edit' => 'Ubah',
        'delete' => 'Hapus',
        'activate' => 'Aktifkan',
        'toggle-active' => 'Aktif/Nonaktifkan',
        'duplikasi' => 'Duplikasi',
        'verifikasi-dokumen' => 'Verifikasi Dokumen',
        'nilai-seleksi' => 'Input Nilai',
        'tetapkan-keputusan' => 'Tetapkan Keputusan',
        'terbitkan-sk' => 'Terbitkan SK',
        'buat-susulan' => 'Buat Tagihan Susulan',
    ];

    /**
     * @return array<int, array{module: string, label: string, permissions: array<int, array{id: int, name: string, action: string, label: string}>}>
     */
    public static function grouped(): array
    {
        return Permission::orderBy('name')->get()
            ->groupBy(fn (Permission $permission) => explode('.', $permission->name)[0])
            ->map(function ($permissions, string $module) {
                return [
                    'module' => $module,
                    'label' => self::MODULE_LABELS[$module] ?? ucfirst($module),
                    'permissions' => $permissions->map(function (Permission $permission) {
                        $action = explode('.', $permission->name)[1] ?? $permission->name;

                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'action' => $action,
                            'label' => self::ACTION_LABELS[$action] ?? ucfirst($action),
                        ];
                    })->values()->all(),
                ];
            })
            ->sortBy('module')
            ->values()
            ->all();
    }
}

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
        'pembayaran' => 'Pembayaran',
        'cicilan' => 'Cicilan',
        'asesmen' => 'Asesmen',
        'jabatan-tambahan-master' => 'Jabatan Tambahan',
        'jadwal-pelajaran' => 'Jadwal Pelajaran',
        'jam-pelajaran' => 'Jam Pelajaran',
        'jenis-karyawan-master' => 'Jenis Karyawan',
        'kalender-akademik' => 'Kalender Akademik',
        'karyawan' => 'Karyawan',
        'kasus' => 'Manajemen Kasus Siswa',
        'kelas' => 'Kelas',
        'kenaikan-kelas' => 'Kenaikan Kelas',
        'keuangan' => 'Keuangan',
        'komponen-penilaian' => 'Komponen Penilaian (TP)',
        'mata-pelajaran' => 'Mata Pelajaran',
        'orang-tua' => 'Orang Tua',
        'pengadaan' => 'Pengadaan Sarpras',
        'pengaturan-akademik' => 'Pengaturan Akademik',
        'pola-jam' => 'Pola Jam',
        'presensi' => 'Presensi & Jurnal KBM',
        'rapor' => 'Rapor',
        'rpp' => 'Perangkat Ajar (RPP)',
        'sarpras' => 'Sarana & Prasarana',
        'whatsapp-template' => 'Template WhatsApp',
        'workflow' => 'Konfigurasi Alur Kerja',
        'yayasan' => 'Yayasan',
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
        'verifikasi' => 'Verifikasi',
        'catat-manual' => 'Catat Manual',
        'kelola' => 'Kelola',
        'catat' => 'Catat',
        'kelola-konfigurasi' => 'Kelola Konfigurasi',
        'lihat-qr-sendiri' => 'Lihat QR Sendiri',
        'manage' => 'Kelola',
        'ajukan' => 'Ajukan',
        'approve' => 'Setujui',
        'lihat-sendiri' => 'Lihat Sendiri',
        'internal' => 'Internal',
        'yayasan' => 'Yayasan',
        'submit' => 'Ajukan',
        'verify' => 'Verifikasi',
        'export' => 'Ekspor',
    ];

    private const NOUN_LABELS = [
        'gedung' => 'Gedung',
        'ruangan' => 'Ruangan',
        'kategori' => 'Kategori',
        'aset' => 'Aset',
        'mutasi' => 'Mutasi',
        'kir' => 'KIR',
        'proposal' => 'Proposal',
        'approval' => 'Approval',
        'disbursement' => 'Disbursement',
        'lpj' => 'LPJ',
        'config' => 'Konfigurasi',
        'izin' => 'Izin',
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
                        $segments = explode('.', $permission->name);
                        $action = $segments[1] ?? $permission->name;

                        if (count($segments) >= 3) {
                            [, $noun, $verb] = $segments;
                            $action = $verb;
                            $label = (self::NOUN_LABELS[$noun] ?? ucfirst($noun)).' · '.(self::ACTION_LABELS[$verb] ?? ucfirst($verb));
                        } else {
                            $label = self::ACTION_LABELS[$action] ?? ucfirst($action);
                        }

                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'action' => $action,
                            'label' => $label,
                        ];
                    })->values()->all(),
                ];
            })
            ->sortBy('module')
            ->values()
            ->all();
    }
}

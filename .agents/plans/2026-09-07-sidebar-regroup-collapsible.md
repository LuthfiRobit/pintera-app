# Sidebar — Regroup Menu & Grup Collapsible Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Susun ulang pengelompokan menu sidebar (rename, split, dedup "Kasus Pendampingan") dan jadikan tiap grup collapsible, tanpa mengubah palet warna/tipografi.

**Architecture:** Edit array `$navGroups` di `resources/views/layouts/sidebar.blade.php` (satu-satunya sumber struktur menu), bungkus tiap grup dengan `x-data` Alpine lokal untuk state buka/tutup, TANPA dependency baru (tidak pakai `@alpinejs/collapse`, sudah dikonfirmasi belum terpasang).

**Tech Stack:** Laravel 12 Blade, Alpine.js (nested `x-data`), Tailwind CSS, Pest (4 file test sidebar existing).

## Global Constraints

- **TIDAK mengubah palet warna/tipografi/token desain** — TailAdmin-style existing (`brand-500`, font Outfit, `rounded-2xl`/`shadow-card` dst.) TETAP. Ini perbaikan STRUKTUR saja.
- **`@alpinejs/collapse` TIDAK dipasang** (dikonfirmasi saat spec ditulis) — pakai `x-show` + `x-transition` biasa, JANGAN tambah dependency baru.
- **Ikon chevron collapse pakai `<x-dynamic-component :component="'lucide-chevron-down'">`** — SAMA POLA dengan seluruh ikon lain di file ini. JANGAN pakai `<x-icon>` (komponen BEDA, skema nama BEDA total — Material-Symbols-style, TIDAK punya `chevron-down`, akan salah render kalau dipaksa).
- **4 file test sidebar SUDAH ADA**: `tests/Feature/Admin/KasusPendampinganSidebarTest.php`, `tests/Feature/SidebarPengelompokanTest.php`, `tests/Feature/SidebarOrangTuaAkademikTest.php`, `tests/Feature/SidebarStubMenuHiddenTest.php`. **1 test SPESIFIK akan PECAH oleh rename** (`SidebarPengelompokanTest.php` baris 147-171, `assertSeeInOrder(['Kehadiran Saya', 'Kasus Pendampingan'])`) — WAJIB diperbarui sebagai bagian Task 1, BUKAN dianggap regresi tak terduga.
- Tidak pakai worktree, kerja langsung di branch `refactor-view-v2`.

---

## Task 1: Regroup `$navGroups` — Rename, Split, Dedup Kasus Pendampingan

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php` (baris 1-171, array `$navGroups` SAJA — bagian render baris 172 ke bawah TIDAK disentuh task ini, itu Task 2)
- Modify: `tests/Feature/SidebarPengelompokanTest.php` (1 test perlu update, lihat Step 1)

**Interfaces:** Tidak ada — murni data array Blade, tidak ada function/class baru.

### Step 1: Perbaiki test yang akan pecah (SEBELUM ubah array, verifikasi merah dengan alasan yang benar)

Baca `tests/Feature/SidebarPengelompokanTest.php` baris 147-171 (`'shows Kasus Pendampingan under Kehadiran Saya (not Pendampingan) for a pool konselor karyawan without kasus.view'`). Update SATU baris assertion, dan (opsional tapi disarankan) perbarui nama test supaya tetap akurat:

```php
it('shows Kasus Pendampingan under Pendampingan Saya (not the admin Pendampingan group) for a pool konselor karyawan without kasus.view', function () {
    (new RoleSeeder)->run();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('pegawai_yayasan');
    $jenis = JenisKaryawanMaster::factory()->create(['is_konselor' => true]);
    $karyawan = Karyawan::factory()->create([
        'user_id' => $user->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Karyawan Pool',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);
    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => StatusKasus::Ditugaskan, 'konselor_karyawan_id' => $karyawan->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSeeInOrder(['Pendampingan Saya', 'Kasus Pendampingan']);
});
```
(Isi test SELAIN baris `assertSeeInOrder` terakhir TIDAK berubah — cuma nama grup yang dicari & judul test disesuaikan dengan struktur baru.)

Run: `php artisan test --filter="shows Kasus Pendampingan under" --compact`
Expected: FAIL — grup "Pendampingan Saya" belum ada di array `$navGroups` saat ini (masih "Kehadiran Saya").

### Step 2: Jalankan SEMUA 4 file test sidebar, catat baseline SEBELUM ubah array

Run: `php artisan test tests/Feature/Admin/KasusPendampinganSidebarTest.php tests/Feature/SidebarPengelompokanTest.php tests/Feature/SidebarOrangTuaAkademikTest.php tests/Feature/SidebarStubMenuHiddenTest.php --compact`
Expected: SEMUA test PASS KECUALI 1 yang baru diubah di Step 1 (FAIL, sesuai ekspektasi). Ini baseline — kalau ada test LAIN yang sudah FAIL di titik ini (sebelum array diubah sama sekali), itu bukan urusan task ini, STOP dan laporkan sebagai temuan terpisah.

### Step 3: Edit array `$navGroups` — terapkan SEMUA perubahan spec sekaligus

Edit `resources/views/layouts/sidebar.blade.php` baris 1-171. Ganti ISI KESELURUHAN array `$navGroups` (dari `[` pembuka baris 2 sampai `]` penutup baris 171) — kode LENGKAP hasil akhir:

```php
@php
    $navGroups = [
        [
            'label' => 'Ringkasan',
            'group_icon' => 'layout-dashboard',
            'items' => [
                ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
            ],
        ],
        [
            'label' => 'Ruang Guru',
            'group_icon' => 'graduation-cap',
            'items' => array_filter([
                Auth::user()->hasRole('guru') && Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.index', 'pattern' => 'guru.jurnal-kbm.index', 'label' => 'Jurnal & Presensi', 'icon' => 'file-pen'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.rekap', 'pattern' => 'guru.jurnal-kbm.rekap', 'label' => 'Rekap Kehadiran', 'icon' => 'chart-bar'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('komponen-penilaian.kelola-sendiri') ? ['route' => 'guru.komponen-penilaian.index', 'pattern' => 'guru.komponen-penilaian.*', 'label' => 'Komponen Penilaian (TP)', 'icon' => 'list-todo'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('asesmen.kelola') ? ['route' => 'guru.asesmen.index', 'pattern' => 'guru.asesmen.*', 'label' => 'Asesmen & Nilai', 'icon' => 'bar-chart-3'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('rapor.input-wali') ? ['route' => 'guru.rapor.catatan.index', 'pattern' => 'guru.rapor.*', 'label' => 'Rapor Wali Kelas', 'icon' => 'book-text'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('rpp.view') ? ['route' => 'admin.rpp.index', 'pattern' => 'admin.rpp.*', 'label' => 'Perangkat Ajar (RPP)', 'icon' => 'file-text'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.lihat-qr-sendiri') ? ['route' => 'sdm.qr-saya', 'pattern' => 'sdm.qr-saya', 'label' => 'QR Kehadiran Saya', 'icon' => 'qr-code'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.izin.lihat-sendiri') ? ['route' => 'sdm.izin-cuti.index', 'pattern' => 'sdm.izin-cuti.*', 'label' => 'Izin/Cuti Saya', 'icon' => 'calendar-days'] : null,
            ]),
        ],
        [
            'label' => 'Ruang Siswa',
            'group_icon' => 'backpack',
            'items' => array_filter([
                Auth::user()->hasRole('siswa') ? ['route' => 'admin.nilai-rapor-saya.index', 'pattern' => 'admin.nilai-rapor-saya.*', 'label' => 'Nilai & Rapor', 'icon' => 'award'] : null,
                Auth::user()->hasRole('siswa') ? ['route' => 'admin.jadwal-pelajaran-saya.index', 'pattern' => 'admin.jadwal-pelajaran-saya.*', 'label' => 'Jadwal Pelajaran', 'icon' => 'calendar-clock'] : null,
                Auth::user()->hasRole('siswa') ? ['route' => 'admin.presensi-saya.index', 'pattern' => 'admin.presensi-saya.*', 'label' => 'Presensi Saya', 'icon' => 'clipboard-check'] : null,
                Auth::user()->hasRole('siswa') ? ['route' => 'admin.kartu-saya.index', 'pattern' => 'admin.kartu-saya.*', 'label' => 'Kartu Digital Saya', 'icon' => 'qr-code'] : null,
            ]),
        ],
        [
            'label' => 'Ruang Orang Tua',
            'group_icon' => 'users',
            'items' => array_filter([
                Auth::user()->orangTua !== null ? ['route' => 'admin.nilai-anak.index', 'pattern' => 'admin.nilai-anak.*', 'label' => 'Nilai & Rapor Anak', 'icon' => 'award'] : null,
                Auth::user()->orangTua !== null ? ['route' => 'admin.jadwal-anak.index', 'pattern' => 'admin.jadwal-anak.*', 'label' => 'Jadwal Anak', 'icon' => 'calendar-clock'] : null,
                Auth::user()->orangTua !== null ? ['route' => 'admin.riwayat-izin-sakit-anak.index', 'pattern' => 'admin.riwayat-izin-sakit-anak.*', 'label' => 'Riwayat Izin/Sakit Anak', 'icon' => 'clipboard-check'] : null,
                Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.dashboard', 'pattern' => 'keuangan.dashboard', 'label' => 'Dompet & Tagihan Saya', 'icon' => 'wallet'] : null,
                Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.tagihan.index', 'pattern' => 'keuangan.tagihan.*', 'label' => 'Tagihan', 'icon' => 'receipt'] : null,
                Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.riwayat.index', 'pattern' => 'keuangan.riwayat.*', 'label' => 'Riwayat', 'icon' => 'history'] : null,
            ]),
        ],
        [
            'label' => 'Ruang Karyawan',
            'group_icon' => 'clock',
            'items' => array_filter([
                ! Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.lihat-qr-sendiri') ? ['route' => 'sdm.qr-saya', 'pattern' => 'sdm.qr-saya', 'label' => 'QR Kehadiran Saya', 'icon' => 'qr-code'] : null,
                ! Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.izin.lihat-sendiri') ? ['route' => 'sdm.izin-cuti.index', 'pattern' => 'sdm.izin-cuti.*', 'label' => 'Izin/Cuti Saya', 'icon' => 'calendar-days'] : null,
            ]),
        ],
        [
            'label' => 'Pendampingan Saya',
            'group_icon' => 'stethoscope',
            'items' => array_filter([
                Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class) ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
            ]),
        ],
        [
            'label' => 'Akademik',
            'group_icon' => 'book-open',
            'items' => array_filter([
                Auth::user()->can('kelas.view') ? ['route' => 'admin.kelas.index', 'pattern' => 'admin.kelas.*', 'label' => 'Kelas', 'icon' => 'door-open'] : null,
                Auth::user()->can('mata-pelajaran.view') ? ['route' => 'admin.mata-pelajaran.index', 'pattern' => 'admin.mata-pelajaran.*', 'label' => 'Mata Pelajaran', 'icon' => 'book'] : null,
                Auth::user()->can('kalender-akademik.view') ? ['route' => 'admin.pengaturan.akademik.index', 'pattern' => 'admin.pengaturan.akademik.*', 'label' => 'Pengaturan Akademik', 'icon' => 'calendar-clock'] : null,
                Auth::user()->can('kurikulum-assignment.view') ? ['route' => 'admin.kurikulum-assignment.index', 'pattern' => 'admin.kurikulum-assignment.*', 'label' => 'Kurikulum Assignment', 'icon' => 'layers'] : null,
                Auth::user()->can('pola-jam.view') ? ['route' => 'admin.pola-jam.index', 'pattern' => 'admin.pola-jam.*', 'label' => 'Pola Jam', 'icon' => 'clock'] : null,
                Auth::user()->can('jadwal-pelajaran.kelola') ? ['route' => 'admin.jadwal-pelajaran.index', 'pattern' => 'admin.jadwal-pelajaran.*', 'label' => 'Jadwal Pelajaran', 'icon' => 'clipboard-check'] : null,
                ! Auth::user()->hasRole('guru') && Auth::user()->can('rpp.view') ? ['route' => 'admin.rpp.index', 'pattern' => 'admin.rpp.*', 'label' => 'Perangkat Ajar (RPP)', 'icon' => 'file-text'] : null,
                Auth::user()->can('komponen-penilaian.kelola') ? ['route' => 'admin.komponen-penilaian.index', 'pattern' => 'admin.komponen-penilaian.*', 'label' => 'Komponen Penilaian (TP)', 'icon' => 'list-todo'] : null,
                Auth::user()->can('rapor.view') ? ['route' => 'admin.rapor.index', 'pattern' => 'admin.rapor.*', 'label' => 'Rekap Rapor', 'icon' => 'book-text'] : null,
                Auth::user()->canAny(['rapor.verify', 'rapor.approve']) ? ['route' => 'admin.rapor.persetujuan.index', 'pattern' => 'admin.rapor.persetujuan.*', 'label' => 'Persetujuan Rapor', 'icon' => 'check-square'] : null,
                Auth::user()->can('kenaikan-kelas.kelola') ? ['route' => 'admin.kenaikan-kelas.index', 'pattern' => 'admin.kenaikan-kelas.*', 'label' => 'Kenaikan Kelas', 'icon' => 'trending-up'] : null,
                Auth::user()->can('piket.kelola') ? ['route' => 'admin.piket-guru.index', 'pattern' => 'admin.piket-guru.*', 'label' => 'Jadwal Piket Guru', 'icon' => 'shield'] : null,
            ]),
        ],
        [
            'label' => 'Pendampingan',
            'group_icon' => 'brain',
            'items' => array_filter([
                Auth::user()->can('kasus.triase') ? ['route' => 'admin.kasus.index', 'pattern' => ['admin.kasus.index', 'admin.kasus.triase', 'admin.kasus.assign-konselor'], 'label' => 'Triase Kasus', 'icon' => 'heart-pulse'] : null,
                Auth::user()->can('kasus.lihat-log-akses') ? ['route' => 'admin.kasus.log-akses', 'pattern' => 'admin.kasus.log-akses', 'label' => 'Log Akses Klinis', 'icon' => 'file-lock-2'] : null,
                Auth::user()->can('kasus.lihat-log-akses') ? ['route' => 'admin.kasus.terhapus', 'pattern' => 'admin.kasus.terhapus', 'label' => 'Kasus Terhapus', 'icon' => 'ban'] : null,
            ]),
        ],
        [
            'label' => 'Kehadiran SDM',
            'group_icon' => 'calendar-check',
            'items' => array_filter([
                Auth::user()->can('kehadiran-sdm.view') ? ['route' => 'admin.kehadiran-sdm.index', 'pattern' => 'admin.kehadiran-sdm.index', 'label' => 'Daftar Kehadiran', 'icon' => 'clipboard-list'] : null,
                Auth::user()->can('kehadiran-sdm.catat') && (Auth::user()->widestScopeLevel() !== 'yayasan' || session('active_lembaga_id') !== null) ? ['route' => 'admin.kehadiran-sdm.scan.index', 'pattern' => 'admin.kehadiran-sdm.scan.*', 'label' => 'Scan QR', 'icon' => 'qr-code'] : null,
                Auth::user()->can('kehadiran-sdm.izin.approve') ? ['route' => 'admin.kehadiran-sdm.izin-cuti.index', 'pattern' => 'admin.kehadiran-sdm.izin-cuti.*', 'label' => 'Persetujuan Izin/Cuti', 'icon' => 'check-square'] : null,
                Auth::user()->can('kehadiran-sdm.view') ? ['route' => 'admin.kehadiran-sdm.konfigurasi.index', 'pattern' => 'admin.kehadiran-sdm.konfigurasi.*', 'label' => 'Konfigurasi', 'icon' => 'settings'] : null,
            ]),
        ],
        [
            'label' => 'Keuangan',
            'group_icon' => 'landmark',
            'items' => array_filter([
                Auth::user()->can('pembayaran.virtual-account') ? ['route' => 'admin.virtual-account.index', 'pattern' => 'admin.virtual-account.*', 'label' => 'Virtual Account', 'icon' => 'credit-card'] : null,
                Auth::user()->can('jenis-tagihan.view') ? ['route' => 'admin.jenis-tagihan.index', 'pattern' => 'admin.jenis-tagihan.*', 'label' => 'Jenis Tagihan', 'icon' => 'wallet'] : null,
                Auth::user()->can('pembayaran.verifikasi') ? ['route' => 'admin.manual-payment.index', 'pattern' => 'admin.manual-payment.*', 'label' => 'Verifikasi Transfer Manual', 'icon' => 'file-check'] : null,
            ]),
        ],
        [
            'label' => 'Sarana & Prasarana',
            'group_icon' => 'building',
            'items' => array_filter([
                Auth::user()->can('sarpras.gedung.view') ? ['route' => 'admin.sarpras.gedung.index', 'pattern' => 'admin.sarpras.gedung.*', 'label' => 'Gedung & Bangunan', 'icon' => 'building-2'] : null,
                Auth::user()->can('sarpras.ruangan.view') ? ['route' => 'admin.sarpras.ruangan.index', 'pattern' => 'admin.sarpras.ruangan.*', 'label' => 'Ruangan & Fasilitas', 'icon' => 'door-open'] : null,
                Auth::user()->can('sarpras.kategori.view') ? ['route' => 'admin.sarpras.kategori.index', 'pattern' => 'admin.sarpras.kategori.*', 'label' => 'Kategori Aset', 'icon' => 'tags'] : null,
                Auth::user()->can('sarpras.aset.view') ? ['route' => 'admin.sarpras.aset.index', 'pattern' => 'admin.sarpras.aset.*', 'label' => 'Aset & Inventaris', 'icon' => 'package'] : null,
                Auth::user()->can('sarpras.mutasi.view') ? ['route' => 'admin.sarpras.mutasi.index', 'pattern' => 'admin.sarpras.mutasi.*', 'label' => 'Riwayat Mutasi', 'icon' => 'arrow-left-right'] : null,
                Auth::user()->can('pengadaan.proposal.view') ? ['route' => 'admin.pengadaan.proposal.index', 'pattern' => 'admin.pengadaan.proposal.*', 'label' => 'Usulan Pengadaan', 'icon' => 'shopping-bag'] : null,
                Auth::user()->can('pengadaan.approval.yayasan') ? ['route' => 'admin.pengadaan.inbox.index', 'pattern' => 'admin.pengadaan.inbox.*', 'label' => 'Approval Pengadaan', 'icon' => 'check-square'] : null,
                Auth::user()->can('pengadaan.disbursement.manage') ? ['route' => 'admin.pengadaan.disbursement.index', 'pattern' => 'admin.pengadaan.disbursement.*', 'label' => 'Pencairan Kas Pengadaan', 'icon' => 'banknote'] : null,
                Auth::user()->can('pengadaan.lpj.verify') ? ['route' => 'admin.pengadaan.audit-lpj.index', 'pattern' => 'admin.pengadaan.audit-lpj.*', 'label' => 'Audit LPJ Belanja', 'icon' => 'file-check-2'] : null,
                Auth::user()->can('sarpras.aset.view') && Auth::user()->widestScopeLevel() === 'yayasan' ? ['route' => 'admin.sarpras.rekap-global', 'pattern' => 'admin.sarpras.rekap-global', 'label' => 'Rekap Aset Yayasan', 'icon' => 'pie-chart'] : null,
            ]),
        ],
        [
            'label' => 'Yayasan & Lembaga',
            'group_icon' => 'building-2',
            'items' => array_filter([
                Auth::user()->can('yayasan.kelola') ? ['route' => 'admin.yayasan.edit', 'pattern' => 'admin.yayasan.*', 'label' => 'Pengaturan Yayasan', 'icon' => 'landmark'] : null,
                Auth::user()->can('lembaga.view') ? ['route' => 'admin.lembaga.index', 'pattern' => 'admin.lembaga.*', 'label' => 'Lembaga', 'icon' => 'building-2'] : null,
                Auth::user()->can('tahun-ajaran.view') ? ['route' => 'admin.tahun-ajaran.index', 'pattern' => 'admin.tahun-ajaran.*', 'label' => 'Tahun Ajaran', 'icon' => 'calendar-days'] : null,
            ]),
        ],
        [
            'label' => 'Data Guru & Karyawan',
            'group_icon' => 'briefcase',
            'items' => array_filter([
                Auth::user()->can('guru.view') ? ['route' => 'admin.guru.index', 'pattern' => 'admin.guru.*', 'label' => 'Guru', 'icon' => 'graduation-cap'] : null,
                Auth::user()->can('karyawan.view') ? ['route' => 'admin.karyawan.index', 'pattern' => 'admin.karyawan.*', 'label' => 'Karyawan', 'icon' => 'briefcase'] : null,
                Auth::user()->can('jenis-karyawan-master.view') ? ['route' => 'admin.jenis-karyawan-master.index', 'pattern' => 'admin.jenis-karyawan-master.*', 'label' => 'Jenis Karyawan', 'icon' => 'tags'] : null,
                Auth::user()->can('jabatan-tambahan-master.view') ? ['route' => 'admin.jabatan-tambahan-master.index', 'pattern' => 'admin.jabatan-tambahan-master.*', 'label' => 'Jabatan Tambahan', 'icon' => 'medal'] : null,
            ]),
        ],
        [
            'label' => 'Data Siswa & Orang Tua',
            'group_icon' => 'contact',
            'items' => array_filter([
                Auth::user()->can('siswa.view') ? ['route' => 'admin.siswa.index', 'pattern' => 'admin.siswa.*', 'label' => 'Siswa', 'icon' => 'users'] : null,
                Auth::user()->can('orang-tua.view') ? ['route' => 'admin.orang-tua.index', 'pattern' => 'admin.orang-tua.*', 'label' => 'Orang Tua', 'icon' => 'users'] : null,
            ]),
        ],
        [
            'label' => 'Pengaturan Sistem',
            'group_icon' => 'shield-check',
            'items' => array_filter([
                Auth::user()->can('users.view') ? ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Pengguna', 'icon' => 'users-round'] : null,
                Auth::user()->can('roles.view') ? ['route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'label' => 'Peran', 'icon' => 'user-cog'] : null,
                Auth::user()->can('whatsapp-template.edit') ? ['route' => 'admin.whatsapp-template.index', 'pattern' => 'admin.whatsapp-template.*', 'label' => 'Template WhatsApp', 'icon' => 'message-square'] : null,
            ]),
        ],
    ];
@endphp
```

**Catatan penting soal blok komentar SPMB (baris 112-128 di file asli)**: blok komentar besar yang menonaktifkan grup "SPMB" TETAP DIPERTAHANKAN APA ADANYA (salin persis posisinya, taruh di antara grup manapun sesuai urutan array baru — TIDAK ada aturan khusus soal di mana persisnya selama tetap ada di file, karena itu blok comment murni dokumentasi, tidak mempengaruhi render). Begitu juga komentar penjelasan "Tagihan"/"Verifikasi Pembayaran" yang di-comment-out di grup Keuangan (baris 99-108 asli) — TETAP DIPERTAHANKAN persis, JANGAN dihapus.

### Step 4: Jalankan test yang diperbaiki Step 1, verifikasi LULUS

Run: `php artisan test --filter="shows Kasus Pendampingan under" --compact`
Expected: PASS.

### Step 5: Jalankan SEMUA 4 file test sidebar, verifikasi 0 regresi

Run: `php artisan test tests/Feature/Admin/KasusPendampinganSidebarTest.php tests/Feature/SidebarPengelompokanTest.php tests/Feature/SidebarOrangTuaAkademikTest.php tests/Feature/SidebarStubMenuHiddenTest.php --compact`
Expected: SEMUA PASS. Bandingkan dengan baseline Step 2 — TIDAK ADA test lain yang berubah status (pass→fail) selain yang memang sengaja diperbaiki di Step 1.

### Step 6: Verifikasi manual di browser — SEMUA persona

Login bergantian sebagai: `yayasan_super_admin` (lihat semua grup baru muncul dengan isi benar), guru (Ruang Guru tetap normal, Pendampingan Saya muncul kalau py akses), orang tua (Ruang Orang Tua tetap normal, Kasus Pendampingan TIDAK lagi di situ tapi di grup terpisah), karyawan non-guru (grup "Ruang Karyawan" — bukan lagi "Kehadiran Saya" — tampil benar). Laporkan hasil jujur.

### Step 7: Pint & Commit

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/layouts/sidebar.blade.php tests/Feature/SidebarPengelompokanTest.php
git commit -m "refactor(sidebar): regroup menu -- rename, split Data Induk, dedup Kasus Pendampingan

Data Induk (12 item campur aduk) dipecah 3: Yayasan & Lembaga, Data
Guru & Karyawan, Data Siswa & Orang Tua. Kelas & Mata Pelajaran pindah
ke Akademik. Template WhatsApp pindah ke Pengaturan Sistem (rename dari
Akses & Peran). 'Kehadiran Saya' di-rename 'Ruang Karyawan' (konsisten
pola Ruang Guru/Siswa/Orang Tua). 'Kasus Pendampingan' yang sebelumnya
ditempel manual 4x di 4 grup persona berbeda disatukan jadi 1 grup
'Pendampingan Saya' dengan 1 kondisi tunggal (viewAny Kasus). Test
SidebarPengelompokanTest.php disesuaikan untuk 1 assertion yang
terpengaruh rename.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Grup Jadi Collapsible (Accordion)

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php` (baris render, SETELAH `@endphp` — bagian `@foreach ($navGroups as $group)` s.d. penutup `</nav>`)

**Interfaces:** Tidak ada.

### Step 1: Terapkan perubahan render (tidak ada TDD server-side murni — interaksi Alpine cuma bisa diverifikasi browser, lihat Step 2)

Ganti blok `@foreach ($navGroups as $group)` sampai penutupnya (baris asli 206-232 SEBELUM Task 1 dijalankan — cek ulang nomor baris SETELAH Task 1 selesai karena array di atasnya sudah berubah panjang) dengan:

```blade
@foreach ($navGroups as $group)
    @if (count($group['items']))
        @php
            $groupHasActiveItem = collect($group['items'])->contains(fn ($item) => request()->routeIs($item['pattern']));
        @endphp
        <div class="mb-3" x-data="{ open: {{ $groupHasActiveItem ? 'true' : 'false' }} }">
            <button
                type="button"
                @click="open = !open"
                class="mb-2 flex w-full items-center justify-between gap-1.5 rounded-lg px-2 py-1.5 text-left transition hover:bg-gray-50"
            >
                <span class="flex items-center gap-1.5 font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">
                    @if (isset($group['group_icon']))
                        <x-dynamic-component :component="'lucide-' . $group['group_icon']" class="h-[14px] w-[14px] opacity-70" />
                    @endif
                    {{ $group['label'] }}
                </span>
                <x-dynamic-component :component="'lucide-chevron-down'" class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-200" ::class="{ '-rotate-90': !open }" />
            </button>
            <ul
                class="space-y-0.5"
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
            >
                @foreach ($group['items'] as $item)
                    @php $active = request()->routeIs($item['pattern']); @endphp
                    <li>
                        <a
                            href="{{ route($item['route'], $item['params'] ?? []) }}"
                            class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition
                                {{ $active ? 'bg-brand-50 font-semibold text-brand-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                        >
                            <x-dynamic-component :component="'lucide-' . $item['icon']" class="h-[18px] w-[18px] shrink-0 {{ $active ? 'text-brand-500' : 'text-gray-400 group-hover:text-gray-500' }}" />
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endforeach
```

### Step 2: Jalankan SEMUA 4 test sidebar, verifikasi TETAP hijau

Run: `php artisan test tests/Feature/Admin/KasusPendampinganSidebarTest.php tests/Feature/SidebarPengelompokanTest.php tests/Feature/SidebarOrangTuaAkademikTest.php tests/Feature/SidebarStubMenuHiddenTest.php --compact`
Expected: SEMUA PASS — perubahan Task 2 murni markup Alpine di SEKITAR item, teks label/href tiap item TIDAK berubah, jadi assertion `assertSee`/`assertSeeInOrder` (yang cuma cek keberadaan teks di HTML, bukan visibility CSS) SEHARUSNYA tetap lulus tanpa perubahan. Kalau ada yang gagal, investigasi — kemungkinan besar karena `x-show="open"` di initial HTML tetap me-render `<ul>` (Alpine `x-show` pakai `style="display:none"` runtime via JS, BUKAN menghapus elemen dari HTML awal), jadi harusnya aman; TAPI VERIFIKASI, jangan asumsikan.

### Step 3: Verifikasi manual di browser — WAJIB, ini fitur interaktif JS murni

Buka halaman dashboard, untuk SETIAP grup: klik header grup → cek isi collapse/expand dengan transisi halus, klik chevron ikon berputar. Reload halaman di route yang aktifnya ada di grup TERTENTU (mis. buka halaman Karyawan) → cek grup "Data Guru & Karyawan" otomatis TERBUKA saat load, grup lain TERTUTUP. Resize ke mobile width → cek drawer sidebar (buka via hamburger) + accordion tetap berfungsi normal bareng. Kalau ada akses Playwright/browser automation di environment ini, GUNAKAN untuk verifikasi lebih genuine (skrip sekali pakai, boleh dihapus setelah dipakai — pola yang sama dipakai Task 5 plan Karyawan/Guru sebelumnya untuk verifikasi NIK search). Laporkan hasil verifikasi JUJUR di laporan task, JANGAN diasumsikan lolos karena kode "terlihat benar".

### Step 4: Pint & Commit

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/layouts/sidebar.blade.php
git commit -m "feat(sidebar): grup jadi collapsible/accordion

Setiap grup sidebar sekarang bisa dibuka/tutup (Alpine x-data lokal per
grup, tanpa dependency baru -- x-show+x-transition, bukan @alpinejs/
collapse yang belum terpasang). Grup berisi halaman yang sedang aktif
otomatis terbuka saat load, grup lain default tertutup.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Penutup — Full Suite, Pint, Handoff Log, Roadmap

**Files:**
- Create: `.agents/logs/2026-09-07-sidebar-regroup-collapsible.md`
- Modify: `PETA_PENGEMBANGAN.md`

### Step 1: Cek proses PHP lain sebelum full suite

Run (PowerShell): `Get-CimInstance Win32_Process -Filter "Name='php.exe'"` — tunggu kalau ada `php artisan test` lain berjalan.

### Step 2: Full Test Suite

Run: `php artisan test --compact`
Expected: SEMUA lulus KECUALI 4 kegagalan pre-existing yang sudah berulang kali terdokumentasi (`M3DemoDataSeederTest` x2, `PresensiSeederTest`, `SesiPembelajaranSeederTest`). Kegagalan LAIN = regresi nyata, STOP dan investigasi — PERHATIKAN KHUSUS test lain di luar 4 file sidebar yang mungkin menyinggung label menu lama (grep dulu `'Kehadiran Saya'|'Data Induk'|'Akses & Peran'` di `tests/` sebelum full suite kalau mau lebih yakin, karena rename bisa berdampak ke test yang tidak terduga di luar 4 file yang sudah diketahui).

### Step 3: Pint

Run: `vendor/bin/pint --dirty --format agent`

### Step 4: Handoff Log

Buat `.agents/logs/2026-09-07-sidebar-regroup-collapsible.md`, ikuti struktur referensi (`.agents/logs/2026-09-07-karyawan-guru-lintas-yayasan-audit.md`): rangkuman Task 1-2 dengan commit hash, keputusan penting (kenapa tidak pakai `@alpinejs/collapse`, kenapa `<x-icon>` tidak dipakai), hasil full suite, "Di Luar Scope" (restyle visual, TIDAK dikerjakan spec ini).

### Step 5: Update Roadmap

Tambahkan entri baru ke `PETA_PENGEMBANGAN.md`.

### Step 6: Commit dokumentasi

```bash
git add .agents/logs/2026-09-07-sidebar-regroup-collapsible.md PETA_PENGEMBANGAN.md
git commit -m "docs(sidebar): handoff log & update roadmap -- regroup & collapsible sidebar selesai

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage**: Dedup Kasus Pendampingan → Task 1. Rename Kehadiran Saya → Ruang Karyawan → Task 1. Split Data Induk jadi 3 → Task 1. Kelas/Mapel pindah Akademik → Task 1. Template WhatsApp pindah + rename Akses & Peran → Task 1. Collapsible per grup → Task 2. "Di Luar Scope" (restyle visual) → tidak ada task untuk itu, dicatat Task 3.

**2. Placeholder scan**: Kode array `$navGroups` lengkap 1:1 disalin dari struktur asli + perubahan yang di-spec-kan, bukan disingkat "...dst". Kode render Task 2 lengkap.

**3. Type consistency**: Struktur array item (`route`/`pattern`/`label`/`icon`) TIDAK berubah sama sekali dari sebelumnya — cuma REORGANISASI grup, bukan ubah bentuk data. Task 2 murni menambah wrapper `x-data`/`<button>` di SEKITAR struktur yang sama, tidak menyentuh isi `@foreach ($group['items'] as $item)`.

**4. Test-awareness ekstra (di luar template self-review standar)**: 4 file test sidebar existing SUDAH dibaca penuh sebelum plan ditulis — 1 assertion pecah teridentifikasi & diperbaiki eksplisit di Task 1 Step 1 (bukan ditemukan implementer secara tak terduga saat eksekusi). 3 file test lain dikonfirmasi TIDAK terpengaruh (dibaca & dianalisis assertion-nya satu per satu terhadap perubahan yang direncanakan).

@php
    $navGroups = [
        [
            'label' => 'Ringkasan',
            'show_label' => false,
            'divider_after' => true,
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
                // "Tagihan" dan "Verifikasi Pembayaran" di bawah ini sengaja disembunyikan (2026-09-02)
                // -- keduanya PPDB-only by design (TagihanController/PembayaranController cuma pernah
                // menangani Tagihan bertype Pendaftaran, lihat komentar developer di kedua file itu),
                // dan modul SPMB/PPDB sendiri sudah dibekukan dari sidebar sejak 24 Agustus 2026 sambil
                // menunggu rombakan. Membiarkan menu ini terbuka tanpa jalur ke halaman Pendaftaran
                // (yang juga sudah disembunyikan) cuma bikin bingung staff. Route & controller TIDAK
                // disentuh, murni navigasi. Billing reguler (SPP dll) TIDAK terpengaruh -- itu lewat
                // "Jenis Tagihan" + "Verifikasi Transfer Manual" di bawah, keduanya tetap tampil.
                // Auth::user()->can('tagihan.view') && Route::has('admin.tagihan.index') ? ['route' => 'admin.tagihan.index', 'pattern' => 'admin.tagihan.*', 'label' => 'Tagihan', 'icon' => 'receipt'] : null,
                // Auth::user()->can('pembayaran.view') ? ['route' => 'admin.pembayaran.index', 'pattern' => 'admin.pembayaran.*', 'label' => 'Verifikasi Pembayaran', 'icon' => 'banknote'] : null,
                Auth::user()->can('pembayaran.verifikasi') ? ['route' => 'admin.manual-payment.index', 'pattern' => 'admin.manual-payment.*', 'label' => 'Verifikasi Transfer Manual', 'icon' => 'file-check'] : null,
            ]),
        ],
        /*
         * Menu SPMB sengaja disembunyikan sementara dari sidebar admin (24 Agustus 2026) - modul ini
         * akan dirombak ulang, jadi navigasinya ditutup dulu supaya tidak dipakai staf di tengah masa
         * tunggu rombakan. Route & controller TIDAK disentuh sama sekali (tetap hidup, portal publik
         * pendaftaran juga TIDAK terpengaruh) - ini murni menyembunyikan link navigasi internal.
         * Hapus komentar ini begitu rombakan modul SPMB siap/dimulai.
        [
            'label' => 'SPMB',
            'group_icon' => 'user-check',
            'items' => array_filter([
                Auth::user()->can('gelombang-ppdb.view') ? ['route' => 'admin.gelombang-ppdb.index', 'pattern' => 'admin.gelombang-ppdb.*', 'label' => 'Gelombang PPDB', 'icon' => 'waves'] : null,
                Auth::user()->can('jalur-ppdb.view') ? ['route' => 'admin.jalur-ppdb.index', 'pattern' => 'admin.jalur-ppdb.*', 'label' => 'Jalur PPDB', 'icon' => 'signpost'] : null,
                Auth::user()->can('jenis-tes.view') ? ['route' => 'admin.jenis-tes.index', 'pattern' => 'admin.jenis-tes.*', 'label' => 'Jenis Tes', 'icon' => 'help-circle'] : null,
                Auth::user()->can('spmb-pendaftaran.view') ? ['route' => 'admin.spmb-pendaftaran.index', 'pattern' => 'admin.spmb-pendaftaran.*', 'label' => 'Verifikasi & Keputusan', 'icon' => 'clipboard-check'] : null,
            ]),
        ],
        */
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

<!-- Mobile scrim -->
<div
    x-show="sidebarOpen"
    x-transition:enter="transition-opacity ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="sidebarOpen = false"
    class="fixed inset-0 z-40 bg-gray-950/70 backdrop-blur-sm lg:hidden"
    style="display: none;"
></div>

<aside
    class="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] shrink-0 -translate-x-full flex-col overflow-hidden border-r border-gray-800 bg-gray-900 shadow-2xl transition-all duration-300 ease-out lg:sticky lg:top-0 lg:z-40 lg:h-screen lg:max-w-none lg:translate-x-0 lg:shadow-none"
    :class="{ 'translate-x-0': sidebarOpen, 'lg:w-0 lg:border-r-0': sidebarCollapsed, 'lg:w-72': !sidebarCollapsed }"
>
    <div class="flex h-20 shrink-0 items-center justify-between border-b border-gray-800/80 px-6">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 font-display text-lg font-bold text-white shadow-lg shadow-brand-500/30 ring-1 ring-white/10">
                {{ Str::of(config('app.name', 'P'))->substr(0, 1) }}
            </span>
            <div class="leading-tight">
                <p class="font-display text-base font-bold tracking-wide text-white">{{ config('app.name', 'Pintera') }}</p>
                <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-gray-400">Sistem Administrasi</p>
            </div>
        </div>

        <!-- Mobile drawer close button -->
        <button
            type="button"
            @click="sidebarOpen = false"
            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-white/10 hover:text-white lg:hidden"
            aria-label="Tutup sidebar"
        >
            <x-dynamic-component :component="'lucide-x'" class="h-5 w-5" />
        </button>
    </div>

    <nav 
        class="scrollbar-none flex-1 overflow-y-auto px-4 py-4"
        x-init="$nextTick(() => { 
            const activeItem = $el.querySelector('[aria-current]');
            if (activeItem) {
                activeItem.scrollIntoView({ block: 'center' });
            }
        })"
    >
        @foreach ($navGroups as $group)
            @if (count($group['items']))
                @php
                    $groupHasActiveItem = collect($group['items'])->contains(fn ($item) => request()->routeIs($item['pattern']));
                    $isCollapsible = ($group['collapsible'] ?? true) && count($group['items']) > 1;
                    $groupSlug = Str::slug($group['label']);
                @endphp

                @if ($isCollapsible)
                    <div
                        class="mb-2.5"
                        x-data="{
                            open: {{ $groupHasActiveItem ? 'true' : "localStorage.getItem('sidebar_group_{$groupSlug}') === 'true'" }}
                        }"
                        x-init="$watch('open', value => localStorage.setItem('sidebar_group_{{ $groupSlug }}', value))"
                    >
                        <button
                            type="button"
                            @click="open = !open"
                            :aria-expanded="open.toString()"
                            aria-controls="nav-group-{{ $groupSlug }}"
                            class="group/header mb-1 flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-left transition-colors duration-150 hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40"
                        >
                            <span
                                class="flex items-center gap-2 font-display text-[11px] uppercase tracking-[0.16em] transition-colors duration-150"
                                :class="open ? 'font-bold text-white' : 'font-semibold text-gray-400 group-hover/header:text-gray-200'"
                            >
                                @if (isset($group['group_icon']))
                                    <x-dynamic-component
                                        :component="'lucide-' . $group['group_icon']"
                                        class="h-[14px] w-[14px] transition-all duration-150"
                                        ::class="open ? 'text-brand-400 opacity-100' : 'text-gray-400 opacity-70 group-hover/header:text-gray-300 group-hover/header:opacity-100'"
                                    />
                                @endif
                                {{ $group['label'] }}
                            </span>
                            <x-dynamic-component
                                :component="'lucide-chevron-down'"
                                class="h-3.5 w-3.5 shrink-0 transition-transform duration-300 ease-in-out"
                                ::class="{ '-rotate-90 text-gray-500': !open, 'text-gray-300': open }"
                            />
                        </button>
                        <div
                            id="nav-group-{{ $groupSlug }}"
                            class="grid transition-all duration-300 ease-in-out"
                            :class="open ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                            :aria-hidden="(!open).toString()"
                        >
                            <div class="overflow-hidden">
                                <!-- Indented child container (1 spacing offset + subtle dark tree guide line) -->
                                <ul class="ml-3.5 space-y-0.5 border-l-2 border-gray-800 pl-2.5 pb-1 pt-0.5">
                                    @foreach ($group['items'] as $item)
                                        @php $active = request()->routeIs($item['pattern']); @endphp
                                        <li>
                                            <a
                                                href="{{ route($item['route'], $item['params'] ?? []) }}"
                                                @if ($active) aria-current="page" @endif
                                                class="group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition-all duration-150
                                                    {{ $active ? 'bg-brand-500 font-semibold text-white shadow-md shadow-brand-500/25' : 'text-gray-300 hover:bg-white/[0.07] hover:text-white' }}"
                                            >
                                                <x-dynamic-component :component="'lucide-' . $item['icon']" class="h-[17px] w-[17px] shrink-0 transition-colors duration-150 {{ $active ? 'text-white' : 'text-gray-400 group-hover:text-gray-200' }}" />
                                                <span class="truncate">{{ $item['label'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="mb-2.5">
                        @if (!empty($group['show_label'] ?? true))
                            <div class="mb-1 flex items-center gap-2 px-2.5 py-1 font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">
                                @if (isset($group['group_icon']))
                                    <x-dynamic-component :component="'lucide-' . $group['group_icon']" class="h-[14px] w-[14px] opacity-70 text-gray-400" />
                                @endif
                                {{ $group['label'] }}
                            </div>
                            <!-- Indented child container for single items -->
                            <ul class="ml-3.5 space-y-0.5 border-l-2 border-gray-800 pl-2.5 pb-1 pt-0.5">
                                @foreach ($group['items'] as $item)
                                    @php $active = request()->routeIs($item['pattern']); @endphp
                                    <li>
                                        <a
                                            href="{{ route($item['route'], $item['params'] ?? []) }}"
                                            @if ($active) aria-current="page" @endif
                                            class="group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition-all duration-150
                                                {{ $active ? 'bg-brand-500 font-semibold text-white shadow-md shadow-brand-500/25' : 'text-gray-300 hover:bg-white/[0.07] hover:text-white' }}"
                                        >
                                            <x-dynamic-component :component="'lucide-' . $item['icon']" class="h-[17px] w-[17px] shrink-0 transition-colors duration-150 {{ $active ? 'text-white' : 'text-gray-400 group-hover:text-gray-200' }}" />
                                            <span class="truncate">{{ $item['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <!-- Standalone item (e.g. Dashboard) flush without indent -->
                            <ul class="space-y-0.5">
                                @foreach ($group['items'] as $item)
                                    @php $active = request()->routeIs($item['pattern']); @endphp
                                    <li>
                                        <a
                                            href="{{ route($item['route'], $item['params'] ?? []) }}"
                                            @if ($active) aria-current="page" @endif
                                            class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-all duration-150
                                                {{ $active ? 'bg-brand-500 font-semibold text-white shadow-md shadow-brand-500/25' : 'text-gray-300 hover:bg-white/[0.07] hover:text-white' }}"
                                        >
                                            <x-dynamic-component :component="'lucide-' . $item['icon']" class="h-[18px] w-[18px] shrink-0 transition-colors duration-150 {{ $active ? 'text-white' : 'text-brand-400 group-hover:text-white' }}" />
                                            <span class="truncate">{{ $item['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                @if (!empty($group['divider_after']))
                    <hr class="my-2.5 border-t border-gray-800" />
                @endif
            @endif
        @endforeach
    </nav>

    <div class="border-t border-gray-800/80 px-6 py-4">
        <p class="text-[11px] leading-relaxed text-gray-500">
            &copy; {{ now()->year }} {{ config('app.name') }}. Sistem administrasi internal.
        </p>
    </div>
</aside>

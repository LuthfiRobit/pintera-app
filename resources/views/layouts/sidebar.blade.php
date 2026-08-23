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
                Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.index', 'pattern' => 'guru.jurnal-kbm.index', 'label' => 'Jurnal & Presensi', 'icon' => 'file-pen'] : null,
                Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.rekap', 'pattern' => 'guru.jurnal-kbm.rekap', 'label' => 'Rekap Kehadiran', 'icon' => 'chart-bar'] : null,
                Auth::user()->can('komponen-penilaian.kelola-sendiri') ? ['route' => 'guru.komponen-penilaian.index', 'pattern' => 'guru.komponen-penilaian.*', 'label' => 'Komponen Penilaian (TP)', 'icon' => 'list-todo'] : null,
                Auth::user()->can('asesmen.kelola') ? ['route' => 'guru.asesmen.index', 'pattern' => 'guru.asesmen.*', 'label' => 'Asesmen & Nilai', 'icon' => 'bar-chart-3'] : null,
                Auth::user()->can('rapor.input-wali') ? ['route' => 'guru.rapor.catatan.index', 'pattern' => 'guru.rapor.*', 'label' => 'Rapor Wali Kelas', 'icon' => 'book-text'] : null,
            ]),
        ],
        [
            'label' => 'Kehadiran Saya',
            'group_icon' => 'clock',
            'items' => array_filter([
                Auth::user()->can('kehadiran-sdm.lihat-qr-sendiri') ? ['route' => 'sdm.qr-saya', 'pattern' => 'sdm.qr-saya', 'label' => 'QR Kehadiran Saya', 'icon' => 'qr-code'] : null,
                Auth::user()->can('kehadiran-sdm.izin.lihat-sendiri') ? ['route' => 'sdm.izin-cuti.index', 'pattern' => 'sdm.izin-cuti.*', 'label' => 'Izin/Cuti Saya', 'icon' => 'calendar-days'] : null,
            ]),
        ],
        [
            'label' => 'Akademik',
            'group_icon' => 'book-open',
            'items' => array_filter([
                Auth::user()->can('kalender-akademik.view') ? ['route' => 'admin.pengaturan.akademik.index', 'pattern' => 'admin.pengaturan.akademik.*', 'label' => 'Pengaturan Akademik', 'icon' => 'calendar-clock'] : null,
                Auth::user()->can('pola-jam.view') ? ['route' => 'admin.pola-jam.index', 'pattern' => 'admin.pola-jam.*', 'label' => 'Pola Jam', 'icon' => 'clock'] : null,
                Auth::user()->can('jadwal-pelajaran.kelola') ? ['route' => 'admin.jadwal-pelajaran.index', 'pattern' => 'admin.jadwal-pelajaran.*', 'label' => 'Jadwal Pelajaran', 'icon' => 'clipboard-check'] : null,
                Auth::user()->can('rpp.view') ? ['route' => 'admin.rpp.index', 'pattern' => 'admin.rpp.*', 'label' => 'Perangkat Ajar (RPP)', 'icon' => 'file-text'] : null,
                Auth::user()->can('komponen-penilaian.kelola') ? ['route' => 'admin.komponen-penilaian.index', 'pattern' => 'admin.komponen-penilaian.*', 'label' => 'Komponen Penilaian (TP)', 'icon' => 'list-todo'] : null,
                Auth::user()->can('rapor.view') ? ['route' => 'admin.rapor.index', 'pattern' => 'admin.rapor.*', 'label' => 'Rekap Rapor', 'icon' => 'book-text'] : null,
                Auth::user()->canAny(['rapor.verify', 'rapor.approve']) ? ['route' => 'admin.rapor.persetujuan.index', 'pattern' => 'admin.rapor.persetujuan.*', 'label' => 'Persetujuan Rapor', 'icon' => 'check-square'] : null,
                Auth::user()->can('kenaikan-kelas.kelola') ? ['route' => 'admin.kenaikan-kelas.index', 'pattern' => 'admin.kenaikan-kelas.*', 'label' => 'Kenaikan Kelas', 'icon' => 'trending-up'] : null,
            ]),
        ],
        [
            'label' => 'Pendampingan',
            'group_icon' => 'brain',
            'items' => array_filter([
                Auth::user()->can('kasus.triase') ? ['route' => 'admin.kasus.index', 'pattern' => ['admin.kasus.index', 'admin.kasus.triase', 'admin.kasus.assign-konselor'], 'label' => 'Triase Kasus', 'icon' => 'heart-pulse'] : null,
                Auth::user()->can('kasus.view') ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
                Auth::user()->can('kasus.lihat-log-akses') ? ['route' => 'admin.kasus.log-akses', 'pattern' => 'admin.kasus.log-akses', 'label' => 'Log Akses Klinis', 'icon' => 'file-lock-2'] : null,
                Auth::user()->can('kasus.lihat-log-akses') ? ['route' => 'admin.kasus.terhapus', 'pattern' => 'admin.kasus.terhapus', 'label' => 'Kasus Terhapus', 'icon' => 'ban'] : null,
            ]),
        ],
        [
            'label' => 'Kehadiran SDM',
            'group_icon' => 'calendar-check',
            'items' => array_filter([
                Auth::user()->can('kehadiran-sdm.view') ? ['route' => 'admin.kehadiran-sdm.index', 'pattern' => 'admin.kehadiran-sdm.index', 'label' => 'Daftar Kehadiran', 'icon' => 'clipboard-list'] : null,
                Auth::user()->can('kehadiran-sdm.catat') ? ['route' => 'admin.kehadiran-sdm.scan.index', 'pattern' => 'admin.kehadiran-sdm.scan.*', 'label' => 'Scan QR', 'icon' => 'qr-code'] : null,
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
                Auth::user()->can('tagihan.view') && Route::has('admin.tagihan.index') ? ['route' => 'admin.tagihan.index', 'pattern' => 'admin.tagihan.*', 'label' => 'Tagihan', 'icon' => 'receipt'] : null,
                Auth::user()->can('pembayaran.view') ? ['route' => 'admin.pembayaran.index', 'pattern' => 'admin.pembayaran.*', 'label' => 'Verifikasi Pembayaran', 'icon' => 'banknote'] : null,
                Auth::user()->can('pembayaran.verifikasi') ? ['route' => 'admin.manual-payment.index', 'pattern' => 'admin.manual-payment.*', 'label' => 'Verifikasi Transfer Manual', 'icon' => 'file-check'] : null,
                Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.dashboard', 'pattern' => 'keuangan.dashboard', 'label' => 'Dompet & Tagihan Saya', 'icon' => 'wallet'] : null,
                Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.tagihan.index', 'pattern' => 'keuangan.tagihan.*', 'label' => 'Tagihan', 'icon' => 'receipt'] : null,
                Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.riwayat.index', 'pattern' => 'keuangan.riwayat.*', 'label' => 'Riwayat', 'icon' => 'history'] : null,
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
            'label' => 'Data Induk',
            'group_icon' => 'database',
            'items' => array_filter([
                Auth::user()->can('lembaga.view') ? ['route' => 'admin.lembaga.index', 'pattern' => 'admin.lembaga.*', 'label' => 'Lembaga', 'icon' => 'building-2'] : null,
                Auth::user()->can('yayasan.kelola') ? ['route' => 'admin.yayasan.edit', 'pattern' => 'admin.yayasan.*', 'label' => 'Pengaturan Yayasan', 'icon' => 'landmark'] : null,
                Auth::user()->can('tahun-ajaran.view') ? ['route' => 'admin.tahun-ajaran.index', 'pattern' => 'admin.tahun-ajaran.*', 'label' => 'Tahun Ajaran', 'icon' => 'calendar-days'] : null,
                Auth::user()->can('kelas.view') ? ['route' => 'admin.kelas.index', 'pattern' => 'admin.kelas.*', 'label' => 'Kelas', 'icon' => 'door-open'] : null,
                Auth::user()->can('mata-pelajaran.view') ? ['route' => 'admin.mata-pelajaran.index', 'pattern' => 'admin.mata-pelajaran.*', 'label' => 'Mata Pelajaran', 'icon' => 'book'] : null,
                Auth::user()->can('guru.view') ? ['route' => 'admin.guru.index', 'pattern' => 'admin.guru.*', 'label' => 'Guru', 'icon' => 'graduation-cap'] : null,
                Auth::user()->can('karyawan.view') ? ['route' => 'admin.karyawan.index', 'pattern' => 'admin.karyawan.*', 'label' => 'Karyawan', 'icon' => 'briefcase'] : null,
                Auth::user()->can('jenis-karyawan-master.view') ? ['route' => 'admin.jenis-karyawan-master.index', 'pattern' => 'admin.jenis-karyawan-master.*', 'label' => 'Jenis Karyawan', 'icon' => 'tags'] : null,
                Auth::user()->can('jabatan-tambahan-master.view') ? ['route' => 'admin.jabatan-tambahan-master.index', 'pattern' => 'admin.jabatan-tambahan-master.*', 'label' => 'Jabatan Tambahan', 'icon' => 'medal'] : null,
                Auth::user()->can('siswa.view') ? ['route' => 'admin.siswa.index', 'pattern' => 'admin.siswa.*', 'label' => 'Siswa', 'icon' => 'users'] : null,
                Auth::user()->can('orang-tua.view') ? ['route' => 'admin.orang-tua.index', 'pattern' => 'admin.orang-tua.*', 'label' => 'Orang Tua', 'icon' => 'users'] : null,
                Auth::user()->can('whatsapp-template.edit') ? ['route' => 'admin.whatsapp-template.index', 'pattern' => 'admin.whatsapp-template.*', 'label' => 'Template WhatsApp', 'icon' => 'message-square'] : null,
            ]),
        ],
        [
            'label' => 'Akses & Peran',
            'group_icon' => 'shield-check',
            'items' => array_filter([
                Auth::user()->can('users.view') ? ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Pengguna', 'icon' => 'users-round'] : null,
                Auth::user()->can('roles.view') ? ['route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'label' => 'Peran', 'icon' => 'user-cog'] : null,
            ]),
        ],
    ];
@endphp

<!-- Mobile scrim -->
<div
    x-show="sidebarOpen"
    x-transition.opacity
    @click="sidebarOpen = false"
    class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden"
    style="display: none;"
></div>

<aside
    class="fixed inset-y-0 left-0 z-40 flex w-72 shrink-0 -translate-x-full flex-col overflow-hidden border-r border-gray-300 bg-white transition-all duration-200 ease-out lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
    :class="{ 'translate-x-0': sidebarOpen, 'lg:w-0 lg:border-r-0': sidebarCollapsed, 'lg:w-72': !sidebarCollapsed }"
>
    <div class="flex h-20 shrink-0 items-center gap-3 px-6">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 font-display text-lg font-bold text-white">
            {{ Str::of(config('app.name', 'P'))->substr(0, 1) }}
        </span>
        <div class="leading-tight">
            <p class="font-display text-base font-bold text-gray-900">{{ config('app.name', 'Pintera') }}</p>
            <p class="text-[11px] uppercase tracking-[0.14em] text-gray-400">Sistem Administrasi</p>
        </div>
    </div>

    <nav 
        class="scrollbar-none flex-1 overflow-y-auto px-4 py-6"
        x-init="$nextTick(() => { 
            const activeItem = $el.querySelector('.bg-brand-50');
            if (activeItem) {
                activeItem.scrollIntoView({ block: 'center' });
            }
        })"
    >
        @foreach ($navGroups as $group)
            @if (count($group['items']))
                <div class="mb-7">
                    <p class="mb-2 px-2 flex items-center gap-1.5 font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">
                        @if (isset($group['group_icon']))
                            <x-dynamic-component :component="'lucide-' . $group['group_icon']" class="h-[14px] w-[14px] opacity-70" />
                        @endif
                        {{ $group['label'] }}
                    </p>
                    <ul class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            @php $active = request()->routeIs($item['pattern']); @endphp
                            <li>
                                <a
                                    href="{{ route($item['route']) }}"
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
    </nav>

    <div class="border-t border-gray-200 px-6 py-4">
        <p class="text-[11px] leading-relaxed text-gray-400">
            &copy; {{ now()->year }} {{ config('app.name') }}. Sistem administrasi internal.
        </p>
    </div>
</aside>

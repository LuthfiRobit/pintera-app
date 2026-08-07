@php
    $navGroups = [
        [
            'label' => 'Ringkasan',
            'group_icon' => 'space_dashboard',
            'items' => [
                ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ],
        ],
        [
            'label' => 'Ruang Guru',
            'group_icon' => 'school',
            'items' => array_filter([
                Auth::user()->can('presensi.isi') ? ['route' => 'guru.sesi.index', 'pattern' => 'guru.sesi.*', 'label' => 'Jurnal & Presensi', 'icon' => 'edit_note'] : null,
                Auth::user()->can('komponen-penilaian.kelola-sendiri') ? ['route' => 'guru.komponen-penilaian.index', 'pattern' => 'guru.komponen-penilaian.*', 'label' => 'Komponen Penilaian (TP)', 'icon' => 'checklist'] : null,
                Auth::user()->can('asesmen.kelola') ? ['route' => 'guru.asesmen.index', 'pattern' => 'guru.asesmen.*', 'label' => 'Asesmen & Nilai', 'icon' => 'assessment'] : null,
            ]),
        ],
        [
            'label' => 'Akademik',
            'group_icon' => 'menu_book',
            'items' => array_filter([
                Auth::user()->can('kalender-akademik.view') ? ['route' => 'admin.pengaturan.akademik.index', 'pattern' => 'admin.pengaturan.akademik.*', 'label' => 'Pengaturan Akademik', 'icon' => 'schedule'] : null,
                Auth::user()->can('pola-jam.view') ? ['route' => 'admin.pola-jam.index', 'pattern' => 'admin.pola-jam.*', 'label' => 'Pola Jam', 'icon' => 'schedule'] : null,
                Auth::user()->can('jadwal-pelajaran.kelola') ? ['route' => 'admin.jadwal-pelajaran.index', 'pattern' => 'admin.jadwal-pelajaran.*', 'label' => 'Jadwal Pelajaran', 'icon' => 'fact_check'] : null,
                Auth::user()->can('komponen-penilaian.kelola') ? ['route' => 'admin.komponen-penilaian.index', 'pattern' => 'admin.komponen-penilaian.*', 'label' => 'Komponen Penilaian (TP)', 'icon' => 'checklist'] : null,
                Auth::user()->can('rapor.view') ? ['route' => 'admin.rapor.index', 'pattern' => 'admin.rapor.*', 'label' => 'Rekap Rapor', 'icon' => 'assessment'] : null,
                Auth::user()->can('kenaikan-kelas.kelola') ? ['route' => 'admin.kenaikan-kelas.index', 'pattern' => 'admin.kenaikan-kelas.*', 'label' => 'Kenaikan Kelas', 'icon' => 'trending_up'] : null,
            ]),
        ],
        [
            'label' => 'Pendampingan',
            'group_icon' => 'psychology',
            'items' => array_filter([
                Auth::user()->can('kasus.triase') ? ['route' => 'admin.kasus.index', 'pattern' => ['admin.kasus.index', 'admin.kasus.triase', 'admin.kasus.assign-konselor'], 'label' => 'Triase Kasus', 'icon' => 'assessment'] : null,
                Auth::user()->can('kasus.view') ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'fact_check'] : null,
                Auth::user()->can('kasus.lihat-log-akses') ? ['route' => 'admin.kasus.log-akses', 'pattern' => 'admin.kasus.log-akses', 'label' => 'Log Akses Klinis', 'icon' => 'description'] : null,
                Auth::user()->can('kasus.lihat-log-akses') ? ['route' => 'admin.kasus.terhapus', 'pattern' => 'admin.kasus.terhapus', 'label' => 'Kasus Terhapus', 'icon' => 'cancel'] : null,
            ]),
        ],
        [
            'label' => 'Keuangan',
            'group_icon' => 'account_balance',
            'items' => array_filter([
                Auth::user()->can('jenis-tagihan.view') ? ['route' => 'admin.jenis-tagihan.index', 'pattern' => 'admin.jenis-tagihan.*', 'label' => 'Jenis Tagihan', 'icon' => 'payments'] : null,
                Auth::user()->can('tagihan.view') && Route::has('admin.tagihan.index') ? ['route' => 'admin.tagihan.index', 'pattern' => 'admin.tagihan.*', 'label' => 'Tagihan', 'icon' => 'receipt_long'] : null,
                Auth::user()->can('pembayaran.view') ? ['route' => 'admin.pembayaran.index', 'pattern' => 'admin.pembayaran.*', 'label' => 'Verifikasi Pembayaran', 'icon' => 'fact_check'] : null,
            ]),
        ],
        [
            'label' => 'SPMB',
            'group_icon' => 'how_to_reg',
            'items' => array_filter([
                Auth::user()->can('gelombang-ppdb.view') ? ['route' => 'admin.gelombang-ppdb.index', 'pattern' => 'admin.gelombang-ppdb.*', 'label' => 'Gelombang PPDB', 'icon' => 'waves'] : null,
                Auth::user()->can('jalur-ppdb.view') ? ['route' => 'admin.jalur-ppdb.index', 'pattern' => 'admin.jalur-ppdb.*', 'label' => 'Jalur PPDB', 'icon' => 'signpost'] : null,
                Auth::user()->can('jenis-tes.view') ? ['route' => 'admin.jenis-tes.index', 'pattern' => 'admin.jenis-tes.*', 'label' => 'Jenis Tes', 'icon' => 'quiz'] : null,
                Auth::user()->can('spmb-pendaftaran.view') ? ['route' => 'admin.spmb-pendaftaran.index', 'pattern' => 'admin.spmb-pendaftaran.*', 'label' => 'Verifikasi & Keputusan', 'icon' => 'fact_check'] : null,
            ]),
        ],
        [
            'label' => 'Data Induk',
            'group_icon' => 'database',
            'items' => array_filter([
                Auth::user()->can('lembaga.view') ? ['route' => 'admin.lembaga.index', 'pattern' => 'admin.lembaga.*', 'label' => 'Lembaga', 'icon' => 'apartment'] : null,
                Auth::user()->can('tahun-ajaran.view') ? ['route' => 'admin.tahun-ajaran.index', 'pattern' => 'admin.tahun-ajaran.*', 'label' => 'Tahun Ajaran', 'icon' => 'calendar_month'] : null,
                Auth::user()->can('kelas.view') ? ['route' => 'admin.kelas.index', 'pattern' => 'admin.kelas.*', 'label' => 'Kelas', 'icon' => 'group'] : null,
                Auth::user()->can('mata-pelajaran.view') ? ['route' => 'admin.mata-pelajaran.index', 'pattern' => 'admin.mata-pelajaran.*', 'label' => 'Mata Pelajaran', 'icon' => 'description'] : null,
                Auth::user()->can('guru.view') ? ['route' => 'admin.guru.index', 'pattern' => 'admin.guru.*', 'label' => 'Guru', 'icon' => 'school'] : null,
                Auth::user()->can('karyawan.view') ? ['route' => 'admin.karyawan.index', 'pattern' => 'admin.karyawan.*', 'label' => 'Karyawan', 'icon' => 'badge'] : null,
                Auth::user()->can('jenis-karyawan-master.view') ? ['route' => 'admin.jenis-karyawan-master.index', 'pattern' => 'admin.jenis-karyawan-master.*', 'label' => 'Jenis Karyawan', 'icon' => 'badge'] : null,
                Auth::user()->can('jabatan-tambahan-master.view') ? ['route' => 'admin.jabatan-tambahan-master.index', 'pattern' => 'admin.jabatan-tambahan-master.*', 'label' => 'Jabatan Tambahan', 'icon' => 'badge'] : null,
                Auth::user()->can('siswa.view') ? ['route' => 'admin.siswa.index', 'pattern' => 'admin.siswa.*', 'label' => 'Siswa', 'icon' => 'groups'] : null,
                Auth::user()->can('orang-tua.view') ? ['route' => 'admin.orang-tua.index', 'pattern' => 'admin.orang-tua.*', 'label' => 'Orang Tua', 'icon' => 'family_restroom'] : null,
                Auth::user()->can('whatsapp-template.edit') ? ['route' => 'admin.whatsapp-template.index', 'pattern' => 'admin.whatsapp-template.*', 'label' => 'Template WhatsApp', 'icon' => 'chat'] : null,
            ]),
        ],
        [
            'label' => 'Akses & Peran',
            'group_icon' => 'admin_panel_settings',
            'items' => array_filter([
                Auth::user()->can('users.view') ? ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Pengguna', 'icon' => 'group'] : null,
                Auth::user()->can('roles.view') ? ['route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'label' => 'Peran', 'icon' => 'shield_person'] : null,
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
                            <x-icon :name="$group['group_icon']" class="h-[14px] w-[14px] opacity-70" />
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
                                    <x-icon :name="$item['icon']" class="h-[18px] w-[18px] shrink-0 {{ $active ? 'text-brand-500' : 'text-gray-400 group-hover:text-gray-500' }}" />
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

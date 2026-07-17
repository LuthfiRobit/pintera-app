@php
    $navGroups = [
        [
            'label' => 'I. Ringkasan',
            'items' => [
                ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ],
        ],
        [
            'label' => 'II. Data Induk',
            'items' => array_filter([
                Auth::user()->can('lembaga.view') ? ['route' => 'admin.lembaga.index', 'pattern' => 'admin.lembaga.*', 'label' => 'Lembaga', 'icon' => 'apartment'] : null,
                Auth::user()->can('guru.view') ? ['route' => 'admin.guru.index', 'pattern' => 'admin.guru.*', 'label' => 'Guru', 'icon' => 'school'] : null,
                Auth::user()->can('tahun-ajaran.view') ? ['route' => 'admin.tahun-ajaran.index', 'pattern' => 'admin.tahun-ajaran.*', 'label' => 'Tahun Ajaran', 'icon' => 'calendar_month'] : null,
            ]),
        ],
        [
            'label' => 'III. SPMB',
            'items' => array_filter([
                Auth::user()->can('gelombang-ppdb.view') ? ['route' => 'admin.gelombang-ppdb.index', 'pattern' => 'admin.gelombang-ppdb.*', 'label' => 'Gelombang PPDB', 'icon' => 'waves'] : null,
                Auth::user()->can('jalur-ppdb.view') ? ['route' => 'admin.jalur-ppdb.index', 'pattern' => 'admin.jalur-ppdb.*', 'label' => 'Jalur PPDB', 'icon' => 'signpost'] : null,
                Auth::user()->can('jenis-tes.view') ? ['route' => 'admin.jenis-tes.index', 'pattern' => 'admin.jenis-tes.*', 'label' => 'Jenis Tes', 'icon' => 'quiz'] : null,
                Auth::user()->can('spmb-pendaftaran.view') ? ['route' => 'admin.spmb-pendaftaran.index', 'pattern' => 'admin.spmb-pendaftaran.*', 'label' => 'Verifikasi & Keputusan', 'icon' => 'fact_check'] : null,
            ]),
        ],
        [
            'label' => 'IV. Keuangan',
            'items' => array_filter([
                Auth::user()->can('jenis-tagihan.view') ? ['route' => 'admin.jenis-tagihan.index', 'pattern' => 'admin.jenis-tagihan.*', 'label' => 'Jenis Tagihan', 'icon' => 'payments'] : null,
                Auth::user()->can('tagihan.view') && Route::has('admin.tagihan.index') ? ['route' => 'admin.tagihan.index', 'pattern' => 'admin.tagihan.*', 'label' => 'Tagihan', 'icon' => 'receipt_long'] : null,
                Auth::user()->can('pembayaran.view') ? ['route' => 'admin.pembayaran.index', 'pattern' => 'admin.pembayaran.*', 'label' => 'Verifikasi Pembayaran', 'icon' => 'fact_check'] : null,
            ]),
        ],
        [
            'label' => 'V. Akses & Peran',
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

    <nav class="scrollbar-none flex-1 overflow-y-auto px-4 py-6">
        @foreach ($navGroups as $group)
            @if (count($group['items']))
                <div class="mb-7">
                    <p class="mb-2 px-2 font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">
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

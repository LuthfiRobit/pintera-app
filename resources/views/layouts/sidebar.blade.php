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
                Auth::user()->can('manage-lembaga') ? ['route' => 'admin.lembaga.index', 'pattern' => 'admin.lembaga.*', 'label' => 'Lembaga', 'icon' => 'apartment'] : null,
                Auth::user()->can('manage-guru') ? ['route' => 'admin.guru.index', 'pattern' => 'admin.guru.*', 'label' => 'Guru', 'icon' => 'school'] : null,
                Auth::user()->can('manage-tahun-ajaran') ? ['route' => 'admin.tahun-ajaran.index', 'pattern' => 'admin.tahun-ajaran.*', 'label' => 'Tahun Ajaran', 'icon' => 'calendar_month'] : null,
            ]),
        ],
        [
            'label' => 'III. Akses & Peran',
            'items' => array_filter([
                Auth::user()->can('manage-users') ? ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Pengguna', 'icon' => 'group'] : null,
                Auth::user()->can('manage-roles') ? ['route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'label' => 'Peran', 'icon' => 'shield_person'] : null,
            ]),
        ],
    ];
@endphp

<!-- Mobile scrim -->
<div
    x-show="sidebarOpen"
    x-transition.opacity
    @click="sidebarOpen = false"
    class="fixed inset-0 z-30 bg-ink/40 lg:hidden"
    style="display: none;"
></div>

<aside
    class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-ink transition-transform duration-200 ease-out lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
    :class="{ 'translate-x-0': sidebarOpen }"
>
    <div class="flex h-20 shrink-0 items-center gap-3 border-b border-paper/10 px-6">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brass/15 font-display text-lg font-bold text-brass">
            {{ Str::of(config('app.name', 'Y'))->substr(0, 1) }}
        </span>
        <div class="leading-tight">
            <p class="font-display text-base font-bold text-paper">{{ config('app.name', 'Yayasan') }}</p>
            <p class="text-[11px] uppercase tracking-[0.14em] text-paper/40">Sistem Administrasi</p>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-4 py-6">
        @foreach ($navGroups as $group)
            @if (count($group['items']))
                <div class="mb-7">
                    <p class="mb-2 px-2 font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-paper/35">
                        {{ $group['label'] }}
                    </p>
                    <ul class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            @php $active = request()->routeIs($item['pattern']); @endphp
                            <li>
                                <a
                                    href="{{ route($item['route']) }}"
                                    class="group relative flex items-center gap-3 rounded-r-xl py-2.5 pl-3 pr-3 text-sm transition
                                        {{ $active ? 'bg-paper/[0.08] font-semibold text-paper' : 'text-paper/60 hover:bg-paper/5 hover:text-paper/90' }}"
                                >
                                    <span
                                        class="absolute inset-y-1 -left-4 w-[3px] rounded-full bg-brass transition-opacity {{ $active ? 'opacity-100' : 'opacity-0' }}"
                                    ></span>
                                    <span class="material-symbols-outlined shrink-0 {{ $active ? 'text-brass' : 'text-paper/40 group-hover:text-paper/70' }}" style="font-variation-settings: 'FILL' {{ $active ? 1 : 0 }}, 'wght' 400;">{{ $item['icon'] }}</span>
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-paper/10 px-6 py-4">
        <p class="text-[11px] leading-relaxed text-paper/30">
            &copy; {{ now()->year }} {{ config('app.name') }}. Sistem administrasi internal.
        </p>
    </div>
</aside>

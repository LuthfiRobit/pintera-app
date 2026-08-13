@php
    $isYayasan = Auth::user()->widestScopeLevel() === 'yayasan';
    $activeLembagaId = session('active_lembaga_id');
    $lembagaOptions = $isYayasan ? once(fn () => \App\Models\Lembaga::query()->select('id', 'nama')->orderBy('nama')->get()) : collect();
    $activeLembaga = $activeLembagaId ? $lembagaOptions->firstWhere('id', $activeLembagaId) : null;
    $sealLabel = $activeLembaga ? Str::of($activeLembaga->nama)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') : 'YY';
    
    $notificationFeed = app(\App\Services\Notifications\NotificationFeedResolver::class)->resolve(Auth::user());
    $unreadCount = $notificationFeed->whereNull('read_at')->count();

    $orangTua = Auth::user()->orangTua;
    $childOptions = $orangTua !== null
        ? $orangTua->siswa()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->select('siswa.id', 'siswa.nama_lengkap')->orderBy('siswa.nama_lengkap')->get()
        : collect();
    $activeSiswaId = session('active_siswa_id');
    $activeSiswaId = $activeSiswaId ?? (isset($resolvedActiveSiswaId) ? $resolvedActiveSiswaId : null);
@endphp

<header class="sticky top-0 z-20 flex h-20 shrink-0 items-center gap-4 border-b border-gray-300 bg-white/70 px-4 backdrop-blur-md sm:px-6 lg:px-10">
    <button
        @click="window.innerWidth >= 1024 ? sidebarCollapsed = !sidebarCollapsed : sidebarOpen = true"
        class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50"
        aria-label="Buka/tutup sidebar"
    >
        <x-icon name="menu" class="h-5 w-5" />
    </button>

    <div class="hidden min-w-0 flex-1 max-w-[320px] items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-400 sm:flex">
        <x-icon name="search" class="h-[15px] w-[15px] shrink-0" />
        <span class="truncate">Cari menu atau perintah&hellip;</span>
        <kbd class="ml-auto shrink-0 rounded-md border border-gray-200 bg-white px-1.5 py-0.5 font-sans text-[10.5px] text-gray-400">&#8984;K</kbd>
    </div>

    <div class="min-w-0 flex-1 sm:hidden"></div>

    <div class="ml-auto flex items-center gap-3 sm:gap-5">
        <button type="button" class="flex h-[38px] w-[38px] items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:bg-gray-50" aria-label="Mode gelap" title="Mode gelap belum tersedia">
            <x-icon name="dark_mode" class="h-4 w-4" />
        </button>

        <div x-data="{
                unreadCount: {{ $unreadCount }},
                readIds: [],
                async tandaiSatu(id) {
                    if (this.readIds.includes(id)) return;
                    const response = await fetch(`{{ url('/notifikasi') }}/${id}/baca`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    });
                    if (!response.ok) return;
                    this.readIds.push(id);
                    const data = await response.json();
                    this.unreadCount = data.unread_count;
                },
                async tandaiSemua() {
                    const response = await fetch('{{ route('notifikasi.baca-semua') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    });
                    if (!response.ok) return;
                    this.readIds = @js($notificationFeed->pluck('id')->all());
                    this.unreadCount = 0;
                }
            }">
            <x-dropdown align="right" width="w-80">
                <x-slot name="trigger">
                    <button type="button" class="relative flex h-[38px] w-[38px] items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:bg-gray-50" aria-label="Notifikasi">
                        <x-icon name="notifications" class="h-4 w-4" />
                        <span x-show="unreadCount > 0" x-text="unreadCount > 9 ? '9+' : unreadCount" class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white" style="display: none;"></span>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 pb-3 pt-1">
                        <p class="font-display text-sm font-bold text-gray-900">Notifikasi</p>
                        <button type="button" x-show="unreadCount > 0" @click="tandaiSemua()" class="text-xs font-semibold text-brand-600 hover:text-brand-700" style="display: none;">Tandai semua terbaca</button>
                    </div>
                    @if ($notificationFeed->isEmpty())
                        <div class="flex flex-col items-center gap-2 px-4 py-8 text-center">
                            <x-icon name="notifications" class="h-6 w-6 text-gray-300" />
                            <p class="text-sm text-gray-500">Belum ada notifikasi.</p>
                        </div>
                    @else
                        <div class="max-h-80 divide-y divide-gray-100 overflow-y-auto">
                            @foreach ($notificationFeed as $notification)
                                <button
                                    type="button"
                                    @click="tandaiSatu('{{ $notification->id }}')"
                                    :class="readIds.includes('{{ $notification->id }}') ? 'bg-white' : '{{ $notification->read_at === null ? 'bg-brand-50/40' : 'bg-white' }}'"
                                    class="block w-full px-4 py-3 text-left transition hover:bg-gray-50"
                                >
                                    <p class="text-sm text-gray-800">{{ $notification->data['message'] ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </x-slot>
            </x-dropdown>
        </div>

        @if ($isYayasan)
            <div x-data="{ open: false }" class="relative">
                <button
                    @click="open = !open"
                    class="flex items-center gap-2.5 rounded-full border border-brand-100 bg-brand-50 py-1 pl-1 pr-3 transition hover:bg-brand-100"
                >
                    <span class="flex h-8 w-8 items-center justify-center rounded-full border border-brand-300 font-display text-xs font-bold uppercase text-brand-600">
                        {{ $sealLabel }}
                    </span>
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block text-[10px] uppercase tracking-[0.12em] text-gray-400">Meninjau</span>
                        <span class="block max-w-[10rem] truncate text-sm font-medium text-gray-900">{{ $activeLembaga->nama ?? 'Semua Lembaga' }}</span>
                    </span>
                    <x-icon name="expand_more" class="h-[18px] w-[18px] text-gray-500" />
                </button>

                <div
                    x-show="open" @click.outside="open = false" x-transition
                    class="absolute right-0 z-30 mt-2 w-64 rounded-2xl border border-gray-200 bg-white py-2 shadow-elevated"
                    style="display: none;"
                >
                    <p class="px-4 pb-2 pt-1 font-display text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-400">Registrasi Lembaga</p>
                    <a
                        href="{{ request()->fullUrlWithQuery(['switch_lembaga' => 'all']) }}"
                        class="flex items-center justify-between px-4 py-2 text-sm {{ ! $activeLembaga ? 'font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}"
                    >
                        Semua Lembaga
                        @if (! $activeLembaga)<span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>@endif
                    </a>
                    <div class="my-1 border-t border-gray-200"></div>
                    @foreach ($lembagaOptions as $option)
                        <a
                            href="{{ request()->fullUrlWithQuery(['switch_lembaga' => $option->id]) }}"
                            class="flex items-center justify-between px-4 py-2 text-sm {{ $activeLembagaId === $option->id ? 'font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}"
                        >
                            {{ $option->nama }}
                            @if ($activeLembagaId === $option->id)<span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>@endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($orangTua !== null && $childOptions->count() > 1)
            <div x-data="{ open: false }" class="relative">
                <button
                    @click="open = !open"
                    class="flex items-center gap-2.5 rounded-full border border-brand-100 bg-brand-50 py-1 pl-1 pr-3 transition hover:bg-brand-100"
                >
                    <x-icon name="family_restroom" class="h-4 w-4 text-brand-600" />
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block text-[10px] uppercase tracking-[0.12em] text-gray-400">Pilih Profil Anak</span>
                        <span class="block max-w-[10rem] truncate text-sm font-medium text-gray-900">{{ $childOptions->firstWhere('id', $activeSiswaId)?->nama_lengkap ?? $childOptions->first()->nama_lengkap }}</span>
                    </span>
                    <x-icon name="expand_more" class="h-[18px] w-[18px] text-gray-500" />
                </button>

                <div
                    x-show="open" @click.outside="open = false" x-transition
                    class="absolute right-0 z-30 mt-2 w-64 rounded-2xl border border-gray-200 bg-white py-2 shadow-elevated"
                    style="display: none;"
                >
                    @foreach ($childOptions as $option)
                        <a
                            href="{{ route('keuangan.dashboard', ['switch_siswa' => $option->id]) }}"
                            class="flex items-center justify-between px-4 py-2 text-sm {{ $activeSiswaId === $option->id ? 'font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}"
                        >
                            {{ $option->nama_lengkap }}
                            @if ($activeSiswaId === $option->id)<span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>@endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 text-sm text-gray-900 transition hover:bg-gray-50">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-500 font-display text-xs font-semibold text-white">
                        {{ Str::of(Auth::user()->name)->substr(0, 1) }}
                    </span>
                    <span class="hidden max-w-[8rem] truncate font-medium sm:block">{{ Auth::user()->name }}</span>
                    <x-icon name="expand_more" class="h-[18px] w-[18px] text-gray-500" />
                </button>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link :href="route('profile.edit')">{{ __('Profil') }}</x-dropdown-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Keluar') }}
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</header>

@php
    $isYayasan = Auth::user()->widestScopeLevel() === 'yayasan';
    $activeLembagaId = session('active_lembaga_id');
    $lembagaOptions = $isYayasan ? once(fn () => \App\Models\Lembaga::query()->select('id', 'nama')->orderBy('nama')->get()) : collect();
    $activeLembaga = $activeLembagaId ? $lembagaOptions->firstWhere('id', $activeLembagaId) : null;
    $sealLabel = $activeLembaga ? Str::of($activeLembaga->nama)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') : 'YY';
@endphp

<header class="sticky top-0 z-20 flex h-20 shrink-0 items-center gap-4 border-b border-ink/10 bg-white/70 px-4 backdrop-blur-md sm:px-6 lg:px-10">
    <button @click="sidebarOpen = true" class="text-ink/60 hover:text-ink lg:hidden" aria-label="Buka menu">
        <span class="material-symbols-outlined">menu</span>
    </button>

    <div class="min-w-0 flex-1"></div>

    <div class="flex items-center gap-3 sm:gap-5">
        @if ($isYayasan)
            <div x-data="{ open: false }" class="relative">
                <button
                    @click="open = !open"
                    class="flex items-center gap-2.5 rounded-full border border-brass/40 bg-brass/[0.06] py-1 pl-1 pr-3 transition hover:bg-brass/10"
                >
                    <span class="flex h-8 w-8 items-center justify-center rounded-full border border-brass/70 font-display text-xs font-bold uppercase text-brass">
                        {{ $sealLabel }}
                    </span>
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block text-[10px] uppercase tracking-[0.12em] text-slate">Meninjau</span>
                        <span class="block max-w-[10rem] truncate text-sm font-medium text-ink">{{ $activeLembaga->nama ?? 'Semua Lembaga' }}</span>
                    </span>
                    <span class="material-symbols-outlined text-slate" style="font-size: 18px;">expand_more</span>
                </button>

                <div
                    x-show="open" @click.outside="open = false" x-transition
                    class="absolute right-0 z-30 mt-2 w-64 rounded-xl border border-ink/10 bg-white py-2 shadow-elevated"
                    style="display: none;"
                >
                    <p class="px-4 pb-2 pt-1 font-display text-[11px] font-semibold uppercase tracking-[0.14em] text-slate/70">Registrasi Lembaga</p>
                    <a
                        href="{{ request()->fullUrlWithQuery(['switch_lembaga' => 'all']) }}"
                        class="flex items-center justify-between px-4 py-2 text-sm {{ ! $activeLembaga ? 'font-medium text-ink' : 'text-slate hover:bg-paper' }}"
                    >
                        Semua Lembaga
                        @if (! $activeLembaga)<span class="h-1.5 w-1.5 rounded-full bg-brass"></span>@endif
                    </a>
                    <div class="my-1 border-t border-ink/10"></div>
                    @foreach ($lembagaOptions as $option)
                        <a
                            href="{{ request()->fullUrlWithQuery(['switch_lembaga' => $option->id]) }}"
                            class="flex items-center justify-between px-4 py-2 text-sm {{ $activeLembagaId === $option->id ? 'font-medium text-ink' : 'text-slate hover:bg-paper' }}"
                        >
                            {{ $option->nama }}
                            @if ($activeLembagaId === $option->id)<span class="h-1.5 w-1.5 rounded-full bg-brass"></span>@endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 text-sm text-ink transition hover:bg-ink/5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-ink font-display text-xs font-semibold text-paper">
                        {{ Str::of(Auth::user()->name)->substr(0, 1) }}
                    </span>
                    <span class="hidden max-w-[8rem] truncate font-medium sm:block">{{ Auth::user()->name }}</span>
                    <span class="material-symbols-outlined text-slate" style="font-size: 18px;">expand_more</span>
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

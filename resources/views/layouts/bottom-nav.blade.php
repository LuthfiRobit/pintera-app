@php
    $user = Auth::user();
    if (! $user) {
        return;
    }

    if (! $user->hasBottomNav()) {
        return;
    }

    $isGuru = $user->hasRole('guru');
    $isSiswa = ! $isGuru && $user->hasRole('siswa');
    $isOrangTua = ! $isGuru && ! $isSiswa;

    // Active state checks
    $isBerandaActive = request()->routeIs('dashboard');
    $isJurnalActive = request()->routeIs('guru.jurnal-kbm.*');
    $isQrActive = request()->routeIs('sdm.qr-saya*');
    $isNilaiGuruActive = request()->routeIs('guru.asesmen.*');
    $isTagihanActive = request()->routeIs('keuangan.*');

    $fiturParam = request()->query('fitur');
    $isNilaiOrtuActive = request()->routeIs('dalam-pengembangan') && $fiturParam === 'nilai-anak';
    $isPresensiOrtuActive = request()->routeIs('dalam-pengembangan') && $fiturParam === 'riwayat-izin-sakit-anak';
    $isJadwalSiswaActive = request()->routeIs('dalam-pengembangan') && $fiturParam === 'jadwal-pelajaran';
    $isPresensiSiswaActive = request()->routeIs('dalam-pengembangan') && $fiturParam === 'presensi-saya';
    $isNilaiSiswaActive = request()->routeIs('dalam-pengembangan') && $fiturParam === 'nilai-rapor';
@endphp

<nav
    id="bottom-nav"
    class="fixed bottom-3 inset-x-3 z-20 mx-auto h-16 w-[calc(100%-24px)] max-w-3xl rounded-full border border-gray-200 bg-white/95 shadow-elevated backdrop-blur-sm lg:hidden"
    style="bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));"
    aria-label="Navigasi Bawah"
>
    <div class="grid h-full grid-cols-5 items-center">
        @if ($isGuru)
            {{-- Slot 1: Beranda --}}
            <a
                href="{{ route('dashboard') }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Beranda"
                @if($isBerandaActive) data-active="beranda" @endif
            >
                <x-dynamic-component :component="'lucide-layout-dashboard'" class="h-6 w-6 {{ $isBerandaActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Beranda</span>
                @if ($isBerandaActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 2: Jurnal --}}
            @can('presensi.isi')
                <a
                    href="{{ route('guru.jurnal-kbm.index') }}"
                    class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                    aria-label="Jurnal"
                    @if($isJurnalActive) data-active="jurnal" @endif
                >
                    <x-dynamic-component :component="'lucide-file-pen'" class="h-6 w-6 {{ $isJurnalActive ? 'text-brand-600' : 'text-gray-500' }}" />
                    <span class="sr-only">Jurnal</span>
                    @if ($isJurnalActive)
                        <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                    @endif
                </a>
            @else
                <div></div>
            @endcan

            {{-- Slot 3: QR Saya (FAB) --}}
            <div class="flex items-center justify-center">
                @can('kehadiran-sdm.lihat-qr-sendiri')
                    <a
                        href="{{ route('sdm.qr-saya') }}"
                        class="relative -translate-y-2 flex h-12 w-12 items-center justify-center rounded-full bg-brand-500 text-white shadow-md transition duration-150 ease-out hover:bg-brand-600 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30"
                        aria-label="QR Saya"
                        @if($isQrActive) data-active="qr-saya" @endif
                    >
                        <x-dynamic-component :component="'lucide-qr-code'" class="h-6 w-6 text-white" />
                        <span class="sr-only">QR Saya</span>
                    </a>
                @endcan
            </div>

            {{-- Slot 4: Nilai --}}
            @can('asesmen.kelola')
                <a
                    href="{{ route('guru.asesmen.index') }}"
                    class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                    aria-label="Nilai"
                    @if($isNilaiGuruActive) data-active="nilai" @endif
                >
                    <x-dynamic-component :component="'lucide-award'" class="h-6 w-6 {{ $isNilaiGuruActive ? 'text-brand-600' : 'text-gray-500' }}" />
                    <span class="sr-only">Nilai</span>
                    @if ($isNilaiGuruActive)
                        <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                    @endif
                </a>
            @else
                <div></div>
            @endcan
        @elseif ($isOrangTua)
            {{-- Slot 1: Beranda --}}
            <a
                href="{{ route('dashboard') }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Beranda"
                @if($isBerandaActive) data-active="beranda" @endif
            >
                <x-dynamic-component :component="'lucide-layout-dashboard'" class="h-6 w-6 {{ $isBerandaActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Beranda</span>
                @if ($isBerandaActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 2: Nilai Anak --}}
            <a
                href="{{ route('dalam-pengembangan', ['fitur' => 'nilai-anak']) }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Nilai Anak"
                @if($isNilaiOrtuActive) data-active="nilai-anak" @endif
            >
                <x-dynamic-component :component="'lucide-award'" class="h-6 w-6 {{ $isNilaiOrtuActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Nilai Anak</span>
                @if ($isNilaiOrtuActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 3: Tagihan (Flat) --}}
            <a
                href="{{ route('keuangan.dashboard') }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Tagihan"
                @if($isTagihanActive) data-active="tagihan" @endif
            >
                <x-dynamic-component :component="'lucide-wallet'" class="h-6 w-6 {{ $isTagihanActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Tagihan</span>
                @if ($isTagihanActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 4: Presensi Anak --}}
            <a
                href="{{ route('dalam-pengembangan', ['fitur' => 'riwayat-izin-sakit-anak']) }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Presensi Anak"
                @if($isPresensiOrtuActive) data-active="riwayat-izin-sakit-anak" @endif
            >
                <x-dynamic-component :component="'lucide-clipboard-check'" class="h-6 w-6 {{ $isPresensiOrtuActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Presensi Anak</span>
                @if ($isPresensiOrtuActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>
        @elseif ($isSiswa)
            {{-- Slot 1: Beranda --}}
            <a
                href="{{ route('dashboard') }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Beranda"
                @if($isBerandaActive) data-active="beranda" @endif
            >
                <x-dynamic-component :component="'lucide-layout-dashboard'" class="h-6 w-6 {{ $isBerandaActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Beranda</span>
                @if ($isBerandaActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 2: Jadwal Pelajaran --}}
            <a
                href="{{ route('dalam-pengembangan', ['fitur' => 'jadwal-pelajaran']) }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Jadwal Pelajaran"
                @if($isJadwalSiswaActive) data-active="jadwal-pelajaran" @endif
            >
                <x-dynamic-component :component="'lucide-calendar-clock'" class="h-6 w-6 {{ $isJadwalSiswaActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Jadwal Pelajaran</span>
                @if ($isJadwalSiswaActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 3: Presensi Saya (Flat) --}}
            <a
                href="{{ route('dalam-pengembangan', ['fitur' => 'presensi-saya']) }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Presensi Saya"
                @if($isPresensiSiswaActive) data-active="presensi-saya" @endif
            >
                <x-dynamic-component :component="'lucide-clipboard-check'" class="h-6 w-6 {{ $isPresensiSiswaActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Presensi Saya</span>
                @if ($isPresensiSiswaActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 4: Nilai & Rapor --}}
            <a
                href="{{ route('dalam-pengembangan', ['fitur' => 'nilai-rapor']) }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Nilai &amp; Rapor"
                @if($isNilaiSiswaActive) data-active="nilai-rapor" @endif
            >
                <x-dynamic-component :component="'lucide-award'" class="h-6 w-6 {{ $isNilaiSiswaActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Nilai &amp; Rapor</span>
                @if ($isNilaiSiswaActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>
        @endif

        {{-- Slot 5: Menu (Buka Sidebar Off-Canvas) --}}
        <button
            type="button"
            @click="sidebarOpen = true"
            class="flex flex-col items-center justify-center p-2 text-gray-500 transition duration-150 ease-out active:scale-[0.96] hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
            aria-label="Buka menu"
        >
            <x-dynamic-component :component="'lucide-menu'" class="h-6 w-6 text-gray-500" />
            <span class="sr-only">Buka menu</span>
        </button>
    </div>
</nav>

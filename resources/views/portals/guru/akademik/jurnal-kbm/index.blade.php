<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Jurnal &amp; Presensi Siswa</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola pencatatan jurnal mengajar harian dan presensi kehadiran siswa di kelas Anda.</p>
            </div>
            <p class="text-sm text-gray-500">
                Ruang Guru <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jurnal &amp; Presensi</b>
            </p>
        </div>

        {{-- Stats Grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Stat 1: Total Sesi --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Sesi Hari Ini</p>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $sesiList->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-blue-50 p-3 text-blue-600">
                        <x-icon name="description" class="h-6 w-6" />
                    </div>
                </div>
            </div>

            {{-- Stat 2: Jurnal Terisi --}}
            @php
                $terlaksanaCount = $sesiList->filter(fn($s) => $s->status === \App\Domains\Akademik\Enums\StatusSesiPembelajaran::Terlaksana && $s->materi !== null)->count();
                $pendingCount = $sesiList->count() - $terlaksanaCount;
            @endphp
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Jurnal Terisi</p>
                        <p class="mt-1 font-display text-2xl font-bold text-success-700">{{ $terlaksanaCount }}</p>
                    </div>
                    <div class="rounded-xl bg-success-50 p-3 text-success-700">
                        <x-icon name="check_circle" class="h-6 w-6" />
                    </div>
                </div>
            </div>

            {{-- Stat 3: Belum Terisi --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Belum Terisi</p>
                        <p class="mt-1 font-display text-2xl font-bold text-warning-700">{{ $pendingCount }}</p>
                    </div>
                    <div class="rounded-xl bg-warning-50 p-3 text-warning-700">
                        <x-icon name="pending_actions" class="h-6 w-6" />
                    </div>
                </div>
            </div>

            {{-- Stat 4: Tanggal --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Hari &amp; Tanggal</p>
                        <p class="mt-1 text-sm font-bold text-gray-800 leading-tight">
                            {{ now()->locale('id')->isoFormat('dddd') }}<br>
                            <span class="text-xs font-normal text-gray-500">{{ now()->locale('id')->isoFormat('D MMMM YYYY') }}</span>
                        </p>
                    </div>
                    <div class="rounded-xl bg-purple-50 p-3 text-purple-600">
                        <x-icon name="calendar_month" class="h-6 w-6" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Sesi List Section --}}
        <div class="space-y-4">
            <h2 class="font-display text-sm font-bold text-gray-700 flex items-center gap-2">
                <x-icon name="schedule" class="h-4 w-4 text-brand-500" />
                Jadwal Kelas Hari Ini
            </h2>

            @forelse ($sesiList as $sesi)
                @php
                    $isDone = $sesi->status === \App\Domains\Akademik\Enums\StatusSesiPembelajaran::Terlaksana && $sesi->materi !== null;
                @endphp
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition-all duration-200 hover:shadow-md flex flex-col md:flex-row md:items-center justify-between gap-5">
                    <div class="space-y-3 flex-1">
                        {{-- Badges & Time --}}
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge tone="brass" class="gap-1">
                                <x-icon name="school" class="h-3 w-3" />
                                Kelas {{ $sesi->kelas->nama }}
                            </x-badge>
                            
                            @if ($sesi->mataPelajaran)
                                <x-badge tone="blue" class="gap-1">
                                    <x-icon name="description" class="h-3 w-3" />
                                    {{ $sesi->mataPelajaran->nama }}
                                </x-badge>
                            @else
                                <x-badge tone="slate">
                                    (tanpa mapel)
                                </x-badge>
                            @endif

                            <span class="inline-flex items-center gap-1 text-xs text-gray-500 font-mono bg-gray-50 px-2 py-0.5 rounded-lg border border-gray-150">
                                <x-icon name="schedule" class="h-3 w-3 text-gray-400" />
                                {{ Carbon\Carbon::parse($sesi->jam_mulai)->format('H:i') }} – {{ Carbon\Carbon::parse($sesi->jam_selesai)->format('H:i') }}
                            </span>

                            @if($isDone)
                                <x-badge tone="green" class="gap-1">
                                    <x-icon name="check_circle" class="h-3 w-3" />
                                    Jurnal Terisi
                                </x-badge>
                            @else
                                <x-badge tone="amber" class="gap-1">
                                    <x-icon name="pending_actions" class="h-3 w-3" />
                                    Belum Diisi
                                </x-badge>
                            @endif
                        </div>

                        {{-- Materi/Jurnal Preview --}}
                        <div class="mt-1">
                            @if ($sesi->materi)
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Materi / Jurnal Pembelajaran:</p>
                                <p class="text-sm text-gray-700 mt-1 font-medium bg-gray-50 p-3 rounded-xl border border-gray-100">
                                    {{ $sesi->materi }}
                                </p>
                            @else
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Materi / Jurnal Pembelajaran:</p>
                                <p class="text-sm text-gray-450 italic mt-0.5">Jurnal mengajar dan presensi belum diisi untuk sesi ini.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="shrink-0 w-full md:w-auto">
                        @if ($isDone)
                            <x-link-button variant="ghost" href="{{ route('guru.jurnal-kbm.show', $sesi) }}" class="w-full md:w-auto justify-center">
                                <x-icon name="edit" class="h-4 w-4" />
                                Edit Jurnal &amp; Presensi
                            </x-link-button>
                        @else
                            <x-link-button href="{{ route('guru.jurnal-kbm.show', $sesi) }}" class="w-full md:w-auto justify-center shadow-sm">
                                <x-icon name="edit" class="h-4 w-4" />
                                Isi Jurnal &amp; Presensi
                            </x-link-button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white py-12 px-6 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-50 text-gray-400">
                        <x-icon name="calendar_month" class="h-6 w-6" />
                    </div>
                    <h3 class="mt-3 text-sm font-semibold text-gray-900">Tidak ada sesi mengajar</h3>
                    <p class="mt-1 text-xs text-gray-500">Anda tidak memiliki jadwal mengajar yang terdaftar untuk hari ini.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>

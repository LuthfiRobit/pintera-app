<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="{ search: '', filterJenis: '' }">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Asesmen Pembelajaran</h1>
            <p class="text-sm text-gray-500">
                Ruang Guru <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Asesmen &amp; Nilai</b>
            </p>
        </div>

        <!-- Summary Stat Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Asesmen Active</p>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $asesmenList->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-brand-50 p-3 text-brand-600">
                        <x-icon name="assessment" class="h-6 w-6" />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Sumatif Lingkup Materi</p>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $asesmenList->filter(fn($a) => $a->jenis->value === 'sumatif_lingkup_materi')->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-3 text-emerald-600">
                        <x-icon name="edit_note" class="h-6 w-6" />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Sumatif Akhir / Semester</p>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $asesmenList->filter(fn($a) => in_array($a->jenis->value, ['sumatif_akhir_semester', 'sumatif_akhir_jenjang', 'pas', 'pts', 'pat']))->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-purple-50 p-3 text-purple-600">
                        <x-icon name="fact_check" class="h-6 w-6" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <x-icon name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input 
                        type="text" 
                        x-model="search" 
                        placeholder="Cari judul asesmen, kelas, atau mata pelajaran..." 
                        class="w-full rounded-lg border-gray-200 pl-10 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2"
                    >
                </div>
                <div>
                    <select x-model="filterJenis" class="w-full sm:w-auto rounded-lg border-gray-200 text-sm text-gray-700 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        <option value="">Semua Jenis Asesmen</option>
                        <option value="sumatif_lingkup_materi">Sumatif Lingkup Materi</option>
                        <option value="sumatif_akhir_semester">Sumatif Akhir Semester (SAS)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Asesmen Cards List -->
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between border-b border-gray-100 bg-white px-6 py-4 gap-3">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Asesmen Anda</p>
                <div class="flex items-center gap-2">
                    <x-badge tone="brand" class="text-xs font-semibold px-2.5 py-0.5">{{ $asesmenList->count() }} Kegiatan</x-badge>
                    <x-link-button href="{{ route('guru.asesmen.create') }}">
                        <span class="text-base leading-none mr-1.5">+</span> Buat Asesmen Baru
                    </x-link-button>
                </div>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse ($asesmenList as $asesmen)
                    <div 
                        x-show="(!search || '{{ strtolower(addslashes($asesmen->judul . ' ' . $asesmen->kelas->nama . ' ' . $asesmen->mataPelajaran->nama)) }}'.includes(search.toLowerCase())) && (!filterJenis || '{{ $asesmen->jenis->value }}' === filterJenis)"
                        x-transition
                        class="flex flex-col gap-4 p-6 transition duration-150 hover:bg-gray-50/60 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-2 max-w-3xl">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-md border border-brand-500/30 bg-brand-50 px-2.5 py-1 text-xs font-extrabold text-brand-700">
                                    {{ $asesmen->jenis->label() }}
                                </span>
                                <x-badge tone="slate" class="text-xs font-medium">{{ $asesmen->semester->nama }}</x-badge>
                                <span class="text-xs text-gray-400 font-medium">|</span>
                                <span class="text-xs text-gray-500 font-medium flex items-center gap-1">
                                    <x-icon name="calendar_month" class="h-3.5 w-3.5 text-gray-400" />
                                    {{ $asesmen->tanggal->translatedFormat('d F Y') }}
                                </span>
                            </div>

                            <h3 class="text-lg font-bold text-gray-900">
                                {{ $asesmen->judul }}
                            </h3>

                            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-600 font-medium">
                                <span class="flex items-center gap-1.5 bg-gray-100 px-2.5 py-1 rounded-md">
                                    <x-icon name="group" class="h-4 w-4 text-gray-500" />
                                    Kelas: <strong class="text-gray-900">{{ $asesmen->kelas->nama }}</strong>
                                </span>
                                <span class="flex items-center gap-1.5 bg-gray-100 px-2.5 py-1 rounded-md">
                                    <x-icon name="description" class="h-4 w-4 text-gray-500" />
                                    Mapel: <strong class="text-gray-900">{{ $asesmen->mataPelajaran->nama }}</strong>
                                </span>
                            </div>
                        </div>

                        <div class="shrink-0 pt-2 sm:pt-0">
                            <a 
                                href="{{ route('guru.asesmen.show', $asesmen) }}" 
                                class="inline-flex items-center gap-2 rounded-xl border border-brand-200 bg-brand-50/50 px-4 py-2 text-sm font-semibold text-brand-700 shadow-sm transition hover:bg-brand-500 hover:text-white active:scale-[0.98]"
                            >
                                <x-icon name="edit_note" class="h-4 w-4" />
                                <span>Input Nilai &amp; Catatan</span>
                                <x-icon name="chevron_right" class="h-4 w-4 opacity-70" />
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center text-gray-400 space-y-3">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                            <x-icon name="assessment" class="h-7 w-7" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-700">Belum Ada Asesmen</p>
                            <p class="text-xs text-gray-400 max-w-sm mx-auto mt-0.5">Buat asesmen baru untuk mulai memasukkan skor angka dan narasi ketercapaian siswa.</p>
                        </div>
                        <x-link-button href="{{ route('guru.asesmen.create') }}" class="inline-flex justify-center">
                            <span class="text-base leading-none mr-1.5">+</span> Buat Asesmen Pertama
                        </x-link-button>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>

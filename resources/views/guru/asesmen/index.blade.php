<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <nav class="flex text-xs text-gray-500 mb-1">
                    <span class="hover:text-gray-700">Ruang Guru</span>
                    <span class="mx-2">/</span>
                    <span class="text-gray-700 font-semibold">Asesmen Pembelajaran</span>
                </nav>
                <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-2.5">
                    <x-icon name="assessment" class="h-7 w-7 text-brand-500" />
                    Asesmen & Nilai Kurikulum Merdeka
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Kelola penilaian Sumatif Lingkup Materi dan Sumatif Akhir Semester untuk kelas yang Anda ampu
                </p>
            </div>
            <a 
                href="{{ route('guru.asesmen.create') }}" 
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition duration-200 hover:bg-brand-600 active:scale-[0.98]"
            >
                <x-icon name="add" class="h-4 w-4" />
                Buat Asesmen Baru
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl py-6 space-y-6" x-data="{ search: '', filterJenis: '' }">
        @if (session('status'))
            <div class="flex items-center gap-3 rounded-xl border border-success-500/20 bg-success-50/50 p-4 text-sm font-medium text-success-700">
                <x-icon name="check_circle" class="h-5 w-5 shrink-0 text-success-500" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Summary Stat Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Asesmen Selesai/Aktif</span>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <x-icon name="assessment" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900">{{ $asesmenList->count() }}</p>
                <p class="mt-1 text-xs text-gray-400">Kegiatan penilaian dibuat</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sumatif Lingkup Materi</span>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <x-icon name="edit_note" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900">{{ $asesmenList->filter(fn($a) => $a->jenis->value === 'sumatif_lingkup_materi')->count() }}</p>
                <p class="mt-1 text-xs text-gray-400">Ulangan harian / projek bab</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sumatif Akhir / Semester</span>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                        <x-icon name="fact_check" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900">{{ $asesmenList->filter(fn($a) => in_array($a->jenis->value, ['sumatif_akhir_semester', 'sumatif_akhir_jenjang', 'pas', 'pts', 'pat']))->count() }}</p>
                <p class="mt-1 text-xs text-gray-400">Ujian semester & akhir jenjang</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <x-icon name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input 
                        type="text" 
                        x-model="search" 
                        placeholder="Cari judul asesmen, kelas, atau mata pelajaran..." 
                        class="w-full rounded-xl border-gray-200 pl-10 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2.5"
                    >
                </div>
                <div>
                    <select x-model="filterJenis" class="w-full sm:w-auto rounded-xl border-gray-200 text-sm text-gray-700 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2.5">
                        <option value="">Semua Jenis Asesmen</option>
                        <option value="sumatif_lingkup_materi">Sumatif Lingkup Materi</option>
                        <option value="sumatif_akhir_semester">Sumatif Akhir Semester (SAS)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Asesmen Cards List -->
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-150 bg-gray-50/50 px-6 py-4">
                <p class="text-sm font-semibold text-gray-700">Daftar Asesmen Anda</p>
                <x-badge tone="brand" class="text-xs font-semibold px-2.5 py-0.5">{{ $asesmenList->count() }} Kegiatan</x-badge>
            </div>

            <div class="divide-y divide-gray-150">
                @forelse ($asesmenList as $asesmen)
                    <div 
                        x-show="(!search || '{{ strtolower(addslashes($asesmen->judul . ' ' . $asesmen->kelas->nama . ' ' . $asesmen->mataPelajaran->nama)) }}'.includes(search.toLowerCase())) && (!filterJenis || '{{ $asesmen->jenis->value }}' === filterJenis)"
                        x-transition
                        class="flex flex-col gap-4 p-6 transition duration-150 hover:bg-gray-50/60 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-2 max-w-3xl">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-lg border border-brand-500/30 bg-brand-50 px-2.5 py-1 text-xs font-extrabold text-brand-700">
                                    {{ $asesmen->jenis->label() }}
                                </span>
                                <x-badge tone="slate" class="text-xs font-medium">{{ $asesmen->semester->nama }}</x-badge>
                                <span class="text-xs text-gray-400 font-medium">|</span>
                                <span class="text-xs text-gray-500 font-medium flex items-center gap-1">
                                    <x-icon name="calendar_month" class="h-3.5 w-3.5 text-gray-400" />
                                    {{ $asesmen->tanggal->translatedFormat('d F Y') }}
                                </span>
                            </div>

                            <h3 class="text-lg font-bold text-gray-900 group-hover:text-brand-600">
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
                                class="inline-flex items-center gap-2 rounded-xl border border-brand-200 bg-brand-50/50 px-4 py-2.5 text-sm font-semibold text-brand-700 shadow-sm transition hover:bg-brand-500 hover:text-white active:scale-[0.98]"
                            >
                                <x-icon name="edit_note" class="h-4 w-4" />
                                <span>Input Nilai & Catatan</span>
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
                        <a href="{{ route('guru.asesmen.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-600">
                            <x-icon name="add" class="h-4 w-4" />
                            Buat Asesmen Pertama
                        </a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <nav class="flex text-xs text-gray-500 mb-1">
                    <span class="hover:text-gray-700">Akademik</span>
                    <span class="mx-2">/</span>
                    <span class="text-gray-700 font-semibold">Komponen Penilaian (TP)</span>
                </nav>
                <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-2.5">
                    <x-icon name="checklist" class="h-7 w-7 text-brand-500" />
                    Komponen Penilaian & Tujuan Pembelajaran (TP)
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Kelola Tujuan Pembelajaran (TP) dan Kriteria Ketercapaian (KKTP) Kurikulum Merdeka per Mata Pelajaran
                </p>
            </div>
            <a 
                href="{{ route('admin.komponen-penilaian.create') }}" 
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition duration-200 hover:bg-brand-600 active:scale-[0.98]"
            >
                <x-icon name="add" class="h-4 w-4" />
                Tambah TP Baru
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl py-6 space-y-6" x-data="{ search: '', filterMapel: '', filterSemester: '' }">
        @if (session('status'))
            <div class="flex items-center gap-3 rounded-xl border border-success-500/20 bg-success-50/50 p-4 text-sm font-medium text-success-700">
                <x-icon name="check_circle" class="h-5 w-5 shrink-0 text-success-500" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Summary Stats Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Tujuan Pembelajaran</span>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <x-icon name="checklist" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900">{{ $komponenList->count() }}</p>
                <p class="mt-1 text-xs text-gray-400">Komponen penilaian terdaftar</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Mata Pelajaran Tercover</span>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <x-icon name="menu_book" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900">{{ $komponenList->pluck('mata_pelajaran_id')->unique()->count() }}</p>
                <p class="mt-1 text-xs text-gray-400">Mata pelajaran yang memiliki TP</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Dengan KKTP Spesifik</span>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <x-icon name="fact_check" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900">{{ $komponenList->filter(fn($k) => !empty($k->kktp))->count() }}</p>
                <p class="mt-1 text-xs text-gray-400">Memiliki rincian Kriteria Ketuntasan</p>
            </div>
        </div>

        <!-- Filter and Search Bar -->
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-center">
                <div class="relative flex-1">
                    <x-icon name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input 
                        type="text" 
                        x-model="search" 
                        placeholder="Cari kode TP atau deskripsi..." 
                        class="w-full rounded-xl border-gray-200 pl-10 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2.5"
                    >
                </div>
                <div class="flex flex-wrap gap-2">
                    <select x-model="filterMapel" class="rounded-xl border-gray-200 text-sm text-gray-700 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2.5">
                        <option value="">Semua Mata Pelajaran</option>
                        @foreach ($komponenList->pluck('mataPelajaran.nama', 'mata_pelajaran_id')->unique() as $id => $nama)
                            <option value="{{ $nama }}">{{ $nama }}</option>
                        @endforeach
                    </select>
                    <select x-model="filterSemester" class="rounded-xl border-gray-200 text-sm text-gray-700 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2.5">
                        <option value="">Semua Semester</option>
                        @foreach ($komponenList->pluck('semester.nama', 'semester_id')->unique() as $id => $nama)
                            <option value="{{ $nama }}">{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Content List -->
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-150 bg-gray-50/50 px-6 py-4">
                <p class="text-sm font-semibold text-gray-700">Daftar Komponen & Tujuan Pembelajaran</p>
                <x-badge tone="brand" class="text-xs font-semibold px-2.5 py-0.5">{{ $komponenList->count() }} Data</x-badge>
            </div>
            
            <div class="divide-y divide-gray-150">
                @forelse ($komponenList as $komponen)
                    <div 
                        x-show="(!search || '{{ strtolower(addslashes($komponen->kode . ' ' . $komponen->deskripsi . ' ' . $komponen->mataPelajaran->nama)) }}'.includes(search.toLowerCase())) && (!filterMapel || '{{ addslashes($komponen->mataPelajaran->nama) }}' === filterMapel) && (!filterSemester || '{{ addslashes($komponen->semester->nama) }}' === filterSemester)"
                        x-transition
                        class="p-6 transition hover:bg-gray-50/60 space-y-3"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                @if ($komponen->kode)
                                    <span class="inline-flex items-center rounded-lg border border-brand-500/30 bg-brand-50 px-2.5 py-1 text-xs font-extrabold text-brand-700">
                                        {{ $komponen->kode }}
                                    </span>
                                @endif
                                <span class="font-bold text-gray-900 text-base">{{ $komponen->mataPelajaran->nama }}</span>
                            </div>
                            <x-badge tone="slate" class="text-xs font-medium">{{ $komponen->semester->nama }}</x-badge>
                        </div>

                        <p class="text-sm text-gray-800 leading-relaxed font-medium">
                            {{ $komponen->deskripsi }}
                        </p>

                        @if ($komponen->kktp)
                            <div class="rounded-xl bg-amber-50/60 p-3.5 border border-amber-200/60 text-xs text-amber-900 space-y-1">
                                <p class="font-bold text-amber-800 uppercase tracking-wide text-[10px] flex items-center gap-1">
                                    <x-icon name="fact_check" class="h-3.5 w-3.5 text-amber-600" />
                                    KKTP (Kriteria Ketercapaian Tujuan Pembelajaran):
                                </p>
                                <p class="text-amber-900 leading-relaxed font-medium pl-4 border-l-2 border-amber-400">{{ $komponen->kktp }}</p>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="py-12 text-center text-gray-400 space-y-3">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                            <x-icon name="checklist" class="h-7 w-7" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-700">Belum Ada Tujuan Pembelajaran</p>
                            <p class="text-xs text-gray-400 max-w-sm mx-auto mt-0.5">Tambahkan Tujuan Pembelajaran (TP) untuk mempermudah guru merujuk indikator penilaian saat menginput nilai asesmen.</p>
                        </div>
                        <a href="{{ route('admin.komponen-penilaian.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-600">
                            <x-icon name="add" class="h-4 w-4" />
                            Tambah TP Pertama
                        </a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('guru.asesmen.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 hover:text-gray-900 shadow-sm">
                <x-icon name="arrow_back" class="h-5 w-5" />
            </a>
            <div>
                <nav class="flex text-xs text-gray-500 mb-1">
                    <span class="hover:text-gray-700">Ruang Guru</span>
                    <span class="mx-2">/</span>
                    <a href="{{ route('guru.asesmen.index') }}" class="hover:text-gray-700">Asesmen</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-700 font-semibold">Input Nilai & Deskripsi</span>
                </nav>
                <h2 class="text-2xl font-bold text-gray-900">
                    {{ $asesmen->judul }}
                </h2>
            </div>
        </div>
    </x-slot>

    @php
        $totalSiswa = $nilaiList->count();
        $filledCount = $nilaiList->filter(fn($n) => $n->skor !== null)->count();
        $progressPct = $totalSiswa > 0 ? round(($filledCount / $totalSiswa) * 100) : 0;
    @endphp

    <div class="mx-auto max-w-6xl py-6 space-y-6">
        @if (session('status'))
            <div class="flex items-center gap-3 rounded-xl border border-success-500/20 bg-success-50/50 p-4 text-sm font-medium text-success-700">
                <x-icon name="check_circle" class="h-5 w-5 shrink-0 text-success-500" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Header Information & Progress Card -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-6">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Jenis Asesmen</p>
                    <span class="mt-1.5 inline-flex items-center rounded-lg border border-brand-500/30 bg-brand-50 px-3 py-1 text-sm font-bold text-brand-700">
                        {{ $asesmen->jenis->label() }}
                    </span>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Kelas & Mata Pelajaran</p>
                    <p class="mt-1 text-base font-bold text-gray-900">{{ $asesmen->kelas->nama }} — {{ $asesmen->mataPelajaran->nama }}</p>
                    <p class="text-xs text-gray-500">{{ $asesmen->semester->nama }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Tanggal Pelaksanaan</p>
                    <p class="mt-1 text-base font-semibold text-gray-900 flex items-center gap-1.5">
                        <x-icon name="calendar_month" class="h-4 w-4 text-brand-500" />
                        {{ $asesmen->tanggal->translatedFormat('l, d F Y') }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Progres Input Nilai</p>
                    <div class="mt-1 flex items-baseline justify-between">
                        <p class="text-2xl font-black text-brand-600">{{ $filledCount }} <span class="text-xs font-normal text-gray-500">/ {{ $totalSiswa }} Siswa</span></p>
                        <span class="text-xs font-bold text-brand-700 bg-brand-50 px-2 py-0.5 rounded">{{ $progressPct }}%</span>
                    </div>
                    <!-- Progress Bar -->
                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full bg-brand-500 transition-all duration-300" style="width: {{ $progressPct }}%"></div>
                    </div>
                </div>
            </div>

            @if ($asesmen->komponenPenilaian->isNotEmpty())
                <div class="border-t border-gray-150 pt-4">
                    <p class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                        <x-icon name="checklist" class="h-4 w-4 text-brand-500" />
                        Tujuan Pembelajaran / Indikator Terkait:
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach ($asesmen->komponenPenilaian as $komponen)
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3.5 space-y-1">
                                <div class="flex items-center gap-2">
                                    @if ($komponen->kode)
                                        <span class="text-[11px] font-extrabold text-brand-700 bg-white px-2 py-0.5 rounded border border-brand-200">{{ $komponen->kode }}</span>
                                    @endif
                                    <span class="text-sm font-semibold text-gray-900">{{ $komponen->deskripsi }}</span>
                                </div>
                                @if ($komponen->kktp)
                                    <p class="text-xs text-gray-600 pl-2 border-l-2 border-brand-500">KKTP: {{ $komponen->kktp }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Grading Table Shell -->
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <form method="POST" action="{{ route('guru.asesmen.update-nilai', $asesmen) }}">
                @csrf
                @method('PUT')

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-gray-150 bg-gray-50/50 px-6 py-4">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Lembar Input Nilai & Deskripsi Kualitatif</h3>
                        <p class="text-xs text-gray-500">Masukkan skor angka (0 - 100) dan catatan deskriptif Kurikulum Merdeka.</p>
                    </div>
                    <button 
                        type="submit" 
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition duration-200 hover:bg-brand-600 active:scale-[0.98]"
                    >
                        <x-icon name="check_circle" class="h-4 w-4" />
                        Simpan Perubahan Nilai
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-100 text-xs uppercase font-bold tracking-wider text-gray-600">
                                <th class="py-3.5 pl-6 pr-3 w-12 text-center">No</th>
                                <th class="px-4 py-3.5 w-64">Nama Peserta Didik</th>
                                <th class="px-4 py-3.5 w-44 text-center">Skor Angka (0-100)</th>
                                <th class="px-6 py-3.5">Catatan Kualitatif / Deskripsi Ketercapaian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-150">
                            @foreach ($nilaiList as $index => $nilai)
                                <tr class="transition duration-150 hover:bg-brand-50/20">
                                    <td class="py-4 pl-6 pr-3 text-center font-semibold text-gray-500">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-bold text-gray-900 text-base">{{ $nilai->siswa->nama_lengkap }}</div>
                                        <div class="text-xs font-medium text-gray-400">{{ $nilai->siswa->nis ?: ($nilai->siswa->nisn ?: 'Tanpa NIS') }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <input 
                                            type="number" 
                                            step="0.1" 
                                            min="0" 
                                            max="100" 
                                            name="nilai[{{ $nilai->siswa_id }}][skor]" 
                                            value="{{ old('nilai.'.$nilai->siswa_id.'.skor', $nilai->skor) }}"
                                            placeholder="0 - 100"
                                            class="w-32 text-center font-extrabold text-base rounded-xl border-gray-300 py-2 shadow-sm focus:border-brand-500 focus:ring-brand-500 placeholder:text-gray-300 placeholder:font-normal {{ $nilai->skor !== null ? 'bg-emerald-50/50 text-emerald-800 border-emerald-300' : 'text-gray-900' }}"
                                        >
                                    </td>
                                    <td class="px-6 py-4">
                                        <input 
                                            type="text" 
                                            name="nilai[{{ $nilai->siswa_id }}][catatan]" 
                                            value="{{ old('nilai.'.$nilai->siswa_id.'.catatan', $nilai->catatan) }}"
                                            placeholder="Contoh: Menunjukkan pemahaman mendalam pada materi ini..."
                                            class="w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm py-2 px-3 focus:border-brand-500 focus:ring-brand-500 placeholder:text-gray-400"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-end border-t border-gray-150 bg-gray-50/50 px-6 py-4">
                    <button 
                        type="submit" 
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition duration-200 hover:bg-brand-600 active:scale-[0.98]"
                    >
                        <x-icon name="check_circle" class="h-5 w-5" />
                        Simpan Seluruh Nilai & Deskripsi
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    @php
        $totalCells = $siswaList->count() * max($komponenList->count(), 1);
        $filledCount = $nilaiMatrix->filter(fn ($n) => $n->nilai_angka !== null)->count();
        $progressPct = $totalCells > 0 ? round(($filledCount / $totalCells) * 100) : 0;
    @endphp

    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Input Nilai &amp; Catatan Asesmen</h1>
            <p class="text-sm text-gray-500">
                Ruang Guru <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('guru.asesmen.index') }}" class="font-semibold text-gray-700 hover:text-brand-600 transition-colors">Asesmen &amp; Nilai</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Input Nilai</b>
            </p>
        </div>

        <!-- Header Information & Progress Card -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-6">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Jenis Asesmen</p>
                    <span class="mt-1.5 inline-flex items-center rounded-md border border-brand-500/30 bg-brand-50 px-3 py-1 text-sm font-bold text-brand-700">
                        {{ $asesmen->jenis->label() }}
                    </span>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Kelas &amp; Mata Pelajaran</p>
                    <p class="mt-1 text-base font-bold text-gray-900">{{ $asesmen->kelas->nama }} — {{ $asesmen->subjek->nama }}</p>
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
                        <p class="text-2xl font-black text-brand-600">{{ $filledCount }} <span class="text-xs font-normal text-gray-500">/ {{ $totalCells }} Nilai</span></p>
                        <span class="text-xs font-bold text-brand-700 bg-brand-50 px-2 py-0.5 rounded">{{ $progressPct }}%</span>
                    </div>
                    <!-- Progress Bar -->
                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full bg-brand-500 transition-all duration-300" style="width: {{ $progressPct }}%"></div>
                    </div>
                </div>
            </div>

            @if ($asesmen->komponenPenilaian->isNotEmpty())
                <div class="border-t border-gray-100 pt-4">
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
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <form method="POST" action="{{ route('guru.asesmen.update-nilai', $asesmen) }}">
                @csrf
                @method('PUT')

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 bg-white px-6 py-4">
                    <div>
                        <p class="font-display text-sm font-bold text-gray-900">Lembar Input Nilai per Tujuan Pembelajaran</p>
                        <p class="text-xs text-gray-500">Masukkan skor angka (0 - 100) dan catatan deskriptif untuk setiap TP.</p>
                    </div>
                    <x-primary-button>
                        <x-icon name="check_circle" class="h-4 w-4 mr-1.5" />
                        Simpan Perubahan Nilai
                    </x-primary-button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-100 text-xs uppercase font-bold tracking-wider text-gray-600">
                                <th class="py-3.5 pl-6 pr-3 w-12 text-center">No</th>
                                <th class="px-4 py-3.5 w-56">Nama Peserta Didik</th>
                                @foreach ($komponenList as $komponen)
                                    <th class="px-4 py-3.5 min-w-[220px]">{{ $komponen->kode ?: $komponen->deskripsi }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($siswaList as $index => $siswa)
                                <tr class="transition duration-150 hover:bg-brand-50/20">
                                    <td class="py-4 pl-6 pr-3 text-center font-semibold text-gray-500">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-bold text-gray-900 text-base">{{ $siswa->nama_lengkap }}</div>
                                        <div class="text-xs font-medium text-gray-400">{{ $siswa->nis ?: ($siswa->nisn ?: 'Tanpa NIS') }}</div>
                                    </td>
                                    @foreach ($komponenList as $komponen)
                                        @php $nilai = $nilaiMatrix->get($siswa->id.'-'.$komponen->id); @endphp
                                        <td class="px-4 py-4 space-y-1.5">
                                            <input
                                                type="number"
                                                step="1"
                                                min="0"
                                                max="100"
                                                name="nilai[{{ $siswa->id }}][{{ $komponen->id }}][nilai_angka]"
                                                value="{{ old('nilai.'.$siswa->id.'.'.$komponen->id.'.nilai_angka', $nilai?->nilai_angka) }}"
                                                placeholder="0 - 100"
                                                class="w-24 text-center font-extrabold text-base rounded-lg border-gray-300 py-1.5 shadow-sm focus:border-brand-500 focus:ring-brand-500 placeholder:text-gray-300 placeholder:font-normal {{ $nilai?->nilai_angka !== null ? 'bg-emerald-50/50 text-emerald-800 border-emerald-300' : 'text-gray-900' }}"
                                            >
                                            <input
                                                type="text"
                                                name="nilai[{{ $siswa->id }}][{{ $komponen->id }}][catatan]"
                                                value="{{ old('nilai.'.$siswa->id.'.'.$komponen->id.'.catatan', $nilai?->catatan) }}"
                                                placeholder="Catatan..."
                                                class="w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm py-1.5 px-2.5 focus:border-brand-500 focus:ring-brand-500 placeholder:text-gray-400"
                                            >
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-end border-t border-gray-100 bg-gray-50/50 px-6 py-4">
                    <x-primary-button>
                        <x-icon name="check_circle" class="h-5 w-5 mr-1.5" />
                        Simpan Seluruh Nilai &amp; Deskripsi
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Rekapitulasi Nilai Rapor</h1>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Rekap Rapor</b>
            </p>
        </div>

        <!-- Filter Controls Card -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            <form method="GET" action="{{ route('admin.rapor.index') }}" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[220px]">
                    <x-input-label value="Pilih Kelas" />
                    <select name="kelas_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" @selected($selectedKelas && $selectedKelas->id === $kelas->id)>{{ $kelas->nama }} ({{ $kelas->tahunAjaran->nama ?? '' }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1 min-w-[220px]">
                    <x-input-label value="Pilih Semester" />
                    <select name="semester_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($semesterList as $semester)
                            <option value="{{ $semester->id }}" @selected($selectedSemester && $selectedSemester->id === $semester->id)>{{ $semester->nama }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        @if ($selectedKelas && $selectedSemester)
            @php
                $allClassScores = collect($rekapNilai)->flatMap(fn($mapels) => collect($mapels)->filter(fn($v) => $v !== null));
                $classAvg = $allClassScores->count() > 0 ? round($allClassScores->avg(), 1) : null;
                $highestScore = $allClassScores->count() > 0 ? $allClassScores->max() : null;
            @endphp

            <!-- Class Stat Summary -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Peserta Didik</p>
                            <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $siswaList->count() }} <span class="text-xs font-normal text-gray-400">Siswa</span></p>
                        </div>
                        <div class="rounded-xl bg-brand-50 p-3 text-brand-600">
                            <x-icon name="group" class="h-6 w-6" />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Rata-Rata Kelas</p>
                            <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $classAvg ?? '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-emerald-50 p-3 text-emerald-600">
                            <x-icon name="analytics" class="h-6 w-6" />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Skor Tertinggi</p>
                            <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $highestScore ?? '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 p-3 text-amber-600">
                            <x-icon name="workspace_premium" class="h-6 w-6" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Matrix Table Card -->
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="flex flex-wrap items-center justify-between border-b border-gray-100 bg-white px-6 py-4 gap-3">
                    <div>
                        <p class="font-display text-sm font-bold text-gray-900">Matriks Rata-Rata Nilai Asesmen Per Mapel</p>
                        <p class="text-xs text-gray-500">Nilai dihitung dari rata-rata seluruh asesmen sumatif yang dilaksanakan.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Legend -->
                        <div class="flex items-center gap-3 text-xs font-medium">
                            <span class="flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Tuntas (&ge; 75)
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Perlu Bimbingan (&lt; 75)
                            </span>
                        </div>
                        @if ($selectedKelas && $selectedSemester && $siswaList->isNotEmpty())
                            <x-secondary-button onclick="window.print()">
                                <x-icon name="print" class="h-4 w-4 mr-1.5 text-gray-500" />
                                Cetak Rekap Nilai
                            </x-secondary-button>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm min-w-[600px]">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-100 text-xs font-bold uppercase tracking-wider text-gray-600">
                                <th class="py-3 pl-6 pr-3 w-12 text-center">No</th>
                                <th class="px-4 py-3 min-w-[220px]">Nama Peserta Didik</th>
                                @forelse ($mapelList as $mapel)
                                    <th class="px-3 py-3 text-center min-w-[120px]">
                                        <span class="block text-gray-900 font-extrabold">{{ $mapel->nama }}</span>
                                    </th>
                                @empty
                                    <th class="px-4 py-3 text-center text-gray-400 font-medium">Belum Ada Mapel Terasesmen</th>
                                @endforelse
                                <th class="px-6 py-3 text-center font-extrabold text-brand-700 w-32 bg-brand-50/50">Rata-Rata Umum</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($siswaList as $index => $siswa)
                                @php
                                    $studentScores = collect($rekapNilai[$siswa->id] ?? [])->filter(fn ($v) => $v !== null);
                                    $generalAvg = $studentScores->count() > 0 ? round($studentScores->avg(), 1) : null;
                                @endphp
                                <tr class="transition hover:bg-gray-50/60">
                                    <td class="py-4 pl-6 pr-3 text-center font-semibold text-gray-500">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-bold text-gray-900 text-base">{{ $siswa->nama_lengkap }}</div>
                                        <div class="text-xs text-gray-400">{{ $siswa->nis ?: ($siswa->nisn ?: 'Tanpa NIS') }}</div>
                                    </td>
                                    @forelse ($mapelList as $mapel)
                                        @php
                                            $skor = $rekapNilai[$siswa->id][$mapel->id] ?? null;
                                        @endphp
                                        <td class="px-3 py-4 text-center font-extrabold text-base">
                                            @if ($skor !== null)
                                                <span class="inline-block rounded-lg px-2.5 py-1 {{ $skor >= 75 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                                    {{ $skor }}
                                                </span>
                                            @else
                                                <span class="text-gray-300 font-normal text-xs">—</span>
                                            @endif
                                        </td>
                                    @empty
                                        <td class="px-4 py-4 text-center text-gray-300 text-xs">—</td>
                                    @endforelse
                                    <td class="px-6 py-4 text-center font-black text-lg text-brand-700 bg-brand-50/20">
                                        @if ($generalAvg !== null)
                                            <span class="inline-block rounded-xl px-3 py-1 bg-brand-50 text-brand-800 border border-brand-200">
                                                {{ $generalAvg }}
                                            </span>
                                        @else
                                            <span class="text-gray-300 font-normal text-xs">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 3 + $mapelList->count() }}" class="py-12 text-center text-gray-400">
                                        Belum ada siswa terdaftar di kelas ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-300 p-12 text-center text-gray-400 space-y-3 bg-white">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                    <x-icon name="assessment" class="h-7 w-7" />
                </div>
                <div>
                    <p class="text-base font-semibold text-gray-700">Silakan Pilih Kelas dan Semester</p>
                    <p class="text-xs text-gray-400 max-w-sm mx-auto mt-0.5">Pilih parameter kelas di bagian atas untuk menampilkan rekapitulasi nilai rapor peserta didik.</p>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>

<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        {{-- Flash Messages & Toast Integrations --}}
        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-sm font-medium text-error-700 shadow-xs flex items-center gap-2.5" x-data x-init="$store.toast.push('error', @js($errors->first()))">
                <x-icon name="warning" class="h-5 w-5 text-error-600 shrink-0" />
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        {{-- Top Navigation & Breadcrumbs --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
            <div>
                <p class="text-xs text-gray-400">
                    <a href="{{ route('admin.rapor.persetujuan.index') }}" class="hover:text-brand-600 transition">Akademik</a>
                    <span class="mx-1 text-gray-300">&rsaquo;</span>
                    <a href="{{ route('admin.rapor.persetujuan.index') }}" class="hover:text-brand-600 transition">Persetujuan Rapor</a>
                    <span class="mx-1 text-gray-300">&rsaquo;</span>
                    <b class="font-semibold text-gray-600">Review {{ $pengajuanRapor->kelas->nama }}</b>
                </p>
                <div class="flex flex-wrap items-center gap-2.5 mt-1.5">
                    <h1 class="font-display text-xl font-bold text-gray-900">Review Rapor — {{ $pengajuanRapor->kelas->nama }}</h1>
                    @if ($pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diajukan)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            Tahap Verifikasi Waka Kurikulum
                        </span>
                    @elseif ($pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diverifikasi)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                            Tahap Persetujuan Kepala Sekolah
                        </span>
                    @elseif ($pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Disetujui)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                            <x-icon name="check_circle" class="h-3.5 w-3.5 text-emerald-600" />
                            Disetujui
                        </span>
                    @elseif ($pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Ditolak)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">
                            <x-icon name="warning" class="h-3.5 w-3.5 text-rose-600" />
                            Perlu Revisi
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 mt-1 font-mono">Semester: {{ $pengajuanRapor->semester->nama }} — {{ $pengajuanRapor->kelas->tahunAjaran->nama }}</p>
            </div>

            <div class="shrink-0">
                <a
                    href="{{ route('admin.rapor.persetujuan.index') }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-2xs transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"
                >
                    <span>&larr;</span>
                    <span>Kembali ke Daftar</span>
                </a>
            </div>
        </div>

        {{-- Class Stat Summary (Samakan dengan Rekap Rapor) --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Wali Kelas</p>
                        <p class="mt-1 font-display text-sm font-bold text-gray-900 truncate">{{ $pengajuanRapor->kelas->waliKelas?->nama ?? $pengajuanRapor->diajukanOleh?->name ?? 'Belum ditentukan' }}</p>
                    </div>
                    <div class="rounded-xl bg-purple-50 p-2.5 text-purple-600">
                        <x-icon name="person" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1 text-[11px] text-gray-400">{{ count($mapelList) }} Mata Pelajaran</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Total Peserta Didik</p>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $siswaList->count() }} <span class="text-xs font-normal text-gray-400">Siswa</span></p>
                    </div>
                    <div class="rounded-xl bg-brand-50 p-2.5 text-brand-600">
                        <x-icon name="group" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1 text-[11px] text-gray-400">Terdaftar di kelas</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-1">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Rata-Rata Kelas</p>
                            <x-tooltip text="Dihitung dari rata-rata seluruh nilai numerik individual (siswa x mapel).">
                                <x-icon name="info" class="h-3 w-3 cursor-help text-gray-400" />
                            </x-tooltip>
                        </div>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $classAvg ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-2.5 text-emerald-600">
                        <x-icon name="analytics" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1 text-[11px] text-emerald-600 font-medium">Capaian kelas</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Skor Tertinggi</p>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $highestScore ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-2.5 text-amber-600">
                        <x-icon name="workspace_premium" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1 text-[11px] text-amber-600 font-medium">Nilai maksimal</p>
            </div>
        </div>

        {{-- Catatan Revisi dari Siklus Sebelumnya (jika ada) --}}
        @if ($pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diajukan && $pengajuanRapor->catatan_revisi)
            <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-5 shadow-xs">
                <div class="flex items-start gap-3">
                    <div class="rounded-xl bg-amber-100 p-2 text-amber-700 shrink-0">
                        <x-icon name="warning" class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="font-display font-bold text-amber-900 text-sm">Catatan Revisi dari Siklus Sebelumnya:</p>
                        <p class="mt-1 text-xs text-amber-800 leading-relaxed">{{ $pengajuanRapor->catatan_revisi }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Informative Kelengkapan Nilai Warning (Lapis 2) --}}
        @if ($kelengkapanNilai->isNotEmpty())
            <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-5 shadow-xs">
                <div class="flex items-start gap-3">
                    <div class="rounded-xl bg-amber-100 p-2 text-amber-700 shrink-0">
                        <x-icon name="warning" class="h-5 w-5" />
                    </div>
                    <div class="flex-1">
                        <p class="font-display font-bold text-amber-900 text-sm">Kelengkapan nilai belum 100%</p>
                        <p class="mt-0.5 text-xs text-amber-700">Terdapat siswa yang asesmennya belum tuntas dinilai oleh guru mata pelajaran. Anda tetap berhak memutuskan sesuai pertimbangan kebijakan sekolah.</p>
                        <ul class="mt-3 divide-y divide-amber-200/60 rounded-xl border border-amber-200 bg-white/70 text-xs">
                            @foreach ($kelengkapanNilai as $sel)
                                <li class="px-3.5 py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                    <span class="font-semibold text-gray-900">{{ $sel->subjek->nama }}</span>
                                    <span class="text-amber-800">
                                        <b class="font-bold text-amber-900">{{ $sel->siswaBelumLengkap->count() }}</b> dari {{ $sel->totalSiswa }} siswa belum lengkap:
                                        <span class="italic text-gray-600">{{ $sel->siswaBelumLengkap->pluck('nama_lengkap')->join(', ') }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- REKAP NILAI PER MAPEL (MATRIKS TABEL SAMAKAN DENGAN REKAP RAPOR) --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between border-b border-gray-100 bg-white px-6 py-4 gap-3">
                <div>
                    <h2 class="font-display text-sm font-bold text-gray-900">Matriks Rata-Rata Nilai Asesmen Per Mapel</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Nilai dihitung dari rata-rata seluruh asesmen sumatif yang dilaksanakan.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    {{-- Legend --}}
                    <div class="flex items-center gap-3 text-xs font-medium">
                        <span class="flex items-center gap-1.5 text-gray-600">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            <span>Tuntas (&ge; {{ config('akademik.ambang_tuntas', 75) }})</span>
                        </span>
                        <span class="flex items-center gap-1.5 text-gray-600">
                            <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                            <span>Perlu Bimbingan (&lt; {{ config('akademik.ambang_tuntas', 75) }})</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="overflow-auto max-h-[calc(100vh-280px)] min-h-[350px] scrollbar-thin">
                <table class="w-full text-left text-sm min-w-[700px] border-separate border-spacing-0">
                    <thead class="sticky top-0 z-20 bg-gray-100">
                        <tr class="text-xs font-bold uppercase tracking-wider text-gray-600">
                            <th scope="col" class="sticky top-0 left-0 z-30 bg-gray-100 py-3.5 pl-6 pr-3 w-[56px] min-w-[56px] max-w-[56px] text-center border-b border-r border-gray-200">No</th>
                            <th scope="col" class="sticky top-0 left-[56px] z-30 bg-gray-100 px-4 py-3.5 min-w-[220px] border-b border-r border-gray-200 shadow-[2px_0_4px_-1px_rgba(0,0,0,0.06)]">Nama Peserta Didik</th>
                            @forelse ($mapelList as $subjekKey => $mapel)
                                <th scope="col" class="sticky top-0 z-20 bg-gray-100 px-3 py-3.5 text-center min-w-[120px] border-b border-r border-gray-200">
                                    <span class="block text-gray-700 font-bold truncate max-w-[160px] mx-auto" title="{{ $mapel->nama }}">{{ $mapel->nama }}</span>
                                </th>
                            @empty
                                <th scope="col" class="sticky top-0 z-20 bg-gray-100 px-4 py-3.5 text-center text-gray-400 font-medium border-b border-r border-gray-200">Belum Ada Mapel Terasesmen</th>
                            @endforelse
                            <th scope="col" class="sticky top-0 z-20 bg-brand-50/90 px-6 py-3.5 text-center font-bold text-brand-700 w-32 border-b border-gray-200">Rata-Rata Umum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($siswaList as $index => $siswa)
                            @php
                                $studentScores = collect($rekapNilai[$siswa->id] ?? [])
                                    ->filter(fn ($sel) => $sel !== null && $sel->tuntas !== null)
                                    ->map(fn ($sel) => (float) $sel->label);
                                $generalAvg = $studentScores->count() > 0 ? round($studentScores->avg(), 1) : null;
                            @endphp
                            <tr class="group transition hover:bg-gray-50/60">
                                {{-- No --}}
                                <td class="sticky left-0 z-10 bg-white group-hover:bg-gray-50/90 py-3 pl-6 pr-3 text-center text-xs font-semibold text-gray-500 border-b border-r border-gray-100 w-[56px] min-w-[56px] max-w-[56px]">
                                    {{ $index + 1 }}
                                </td>

                                {{-- Nama Peserta Didik --}}
                                <td class="sticky left-[56px] z-10 bg-white group-hover:bg-gray-50/90 px-4 py-3 border-b border-r border-gray-100 shadow-[2px_0_4px_-1px_rgba(0,0,0,0.06)] min-w-[220px]">
                                    <div class="font-semibold text-gray-900 text-sm">{{ $siswa->nama_lengkap }}</div>
                                    <div class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $siswa->nis ?: ($siswa->nisn ?: 'Tanpa NIS') }}</div>
                                </td>

                                {{-- Capaian Nilai Mapel --}}
                                @forelse ($mapelList as $subjekKey => $mapel)
                                    @php
                                        $sel = $rekapNilai[$siswa->id][$subjekKey] ?? null;
                                    @endphp
                                    <td class="px-3 py-3 text-center text-sm border-b border-r border-gray-100">
                                        @if ($sel === null)
                                            <span class="text-gray-300 font-normal text-xs">—</span>
                                        @elseif ($sel->tuntas !== null)
                                            <span class="inline-flex items-center justify-center min-w-[40px] rounded-lg px-2.5 py-1 text-xs font-semibold {{ $sel->tuntas ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                                {{ $sel->label }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center justify-center min-w-[40px] rounded-lg px-2.5 py-1 text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                                                {{ $sel->label }}
                                            </span>
                                        @endif
                                    </td>
                                @empty
                                    <td class="px-4 py-3 text-center text-gray-300 text-xs border-b border-r border-gray-100">—</td>
                                @endforelse

                                {{-- Rata-Rata Umum --}}
                                <td class="px-6 py-3 text-center text-sm font-bold text-brand-700 bg-brand-50/20 border-b border-gray-100">
                                    @if ($generalAvg !== null)
                                        <span class="inline-flex items-center justify-center min-w-[46px] rounded-xl px-3 py-1 text-xs font-bold bg-brand-50 text-brand-800 border border-brand-200">
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

        {{-- CATATAN WALI KELAS & PRATINJAU DOKUMEN ("BUKA DI PLATFORM") --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between px-1">
                <div>
                    <h2 class="font-display text-sm font-bold text-gray-900">Catatan Wali Kelas &amp; Pratinjau Rapor</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Periksa catatan sikap &amp; perkembangan serta pratinjau lembar rapor siswa.</p>
                </div>
                <span class="rounded-full bg-brand-50 border border-brand-200 px-3 py-1 text-xs font-semibold text-brand-700">
                    {{ $siswaList->count() }} Siswa
                </span>
            </div>

            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                @foreach ($siswaList as $siswa)
                    @php($catatan = $catatanList->get($siswa->id))
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-3 transition hover:shadow-md">
                        <div class="flex items-start justify-between gap-2 border-b border-gray-100 pb-3">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 font-display text-xs font-bold text-brand-700 border border-brand-100">
                                    {{ substr($siswa->nama_lengkap, 0, 2) }}
                                </div>
                                <div>
                                    <p class="font-display font-bold text-gray-900 text-xs sm:text-sm">{{ $siswa->nama_lengkap }}</p>
                                    @if ($siswa->nis || $siswa->nisn)
                                        <p class="text-[11px] text-gray-400 font-mono">NIS: {{ $siswa->nis ?? $siswa->nisn }}</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Dual Actions: Buka di Platform & Cetak/Unduh PDF --}}
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button
                                    type="button"
                                    @click="$store.imagePreview.buka('{{ route('admin.rapor.persetujuan.cetak', ['pengajuanRapor' => $pengajuanRapor->id, 'siswa' => $siswa->id]) }}?inline=1', 'Pratinjau Rapor - {{ $siswa->nama_lengkap }}', true)"
                                    class="inline-flex items-center gap-1 rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-1 text-[11px] font-bold text-brand-700 transition hover:bg-brand-100 shadow-2xs"
                                    title="Pratinjau langsung di dalam platform"
                                >
                                    <x-icon name="visibility" class="h-3.5 w-3.5 text-brand-600" />
                                    <span>Buka di Platform</span>
                                </button>
                                <a
                                    href="{{ route('admin.rapor.persetujuan.cetak', ['pengajuanRapor' => $pengajuanRapor->id, 'siswa' => $siswa->id]) }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 px-2 py-1 text-[11px] font-semibold text-gray-600 transition hover:bg-gray-100"
                                    title="Buka / Unduh file PDF di tab baru"
                                >
                                    <x-icon name="download" class="h-3.5 w-3.5 text-gray-500" />
                                    <span>PDF</span>
                                </a>
                            </div>
                        </div>

                        @if ($catatan)
                            <div class="grid grid-cols-1 gap-2.5 text-xs sm:grid-cols-2">
                                <div class="rounded-xl bg-gray-50/75 p-3 border border-gray-100">
                                    <dt class="font-bold text-gray-600 text-[11px] uppercase tracking-wider mb-1">Catatan Sikap</dt>
                                    <dd class="text-gray-700 leading-relaxed">{{ $catatan->catatan_sikap ?: '—' }}</dd>
                                </div>
                                <div class="rounded-xl bg-gray-50/75 p-3 border border-gray-100">
                                    <dt class="font-bold text-gray-600 text-[11px] uppercase tracking-wider mb-1">Catatan Perkembangan</dt>
                                    <dd class="text-gray-700 leading-relaxed">{{ $catatan->catatan_perkembangan ?: '—' }}</dd>
                                </div>
                            </div>
                        @else
                            <div class="rounded-xl border border-error-100 bg-error-50/60 p-3 text-xs text-error-700 flex items-center gap-2">
                                <x-icon name="warning" class="h-4 w-4 text-error-500 shrink-0" />
                                <span>Belum ada catatan wali kelas untuk siswa ini.</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- EXECUTIVE DECISION SUITE --}}
        @if ($isReadOnly)
            {{-- READ-ONLY STATE (Status Card Resmi) --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-4">
                    <div>
                        <h2 class="font-display text-base font-bold text-gray-900">Status Keputusan Rapor</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Pengajuan ini telah memiliki keputusan resmi dan berada dalam mode arsip read-only.</p>
                    </div>
                    <span @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1 text-xs font-bold uppercase tracking-wider',
                        'bg-emerald-50 text-emerald-700 border border-emerald-200' => $pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Disetujui,
                        'bg-rose-50 text-rose-700 border border-rose-200' => $pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Ditolak,
                    ])>
                        @if ($pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Disetujui)
                            <x-icon name="check_circle" class="h-4 w-4 text-emerald-600" />
                        @else
                            <x-icon name="warning" class="h-4 w-4 text-rose-600" />
                        @endif
                        {{ $pengajuanRapor->status->label() }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <p class="text-gray-400 font-semibold uppercase tracking-wider text-[10px]">Waktu Keputusan</p>
                        <p class="font-medium text-gray-800 mt-0.5">{{ optional($tanggalKeputusan)->translatedFormat('d F Y, H:i') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 font-semibold uppercase tracking-wider text-[10px]">Pejabat Penandatangan</p>
                        <p class="font-medium text-gray-800 mt-0.5">{{ $namaPengambilKeputusan ?: 'Sistem / Otomatis' }}</p>
                    </div>
                </div>

                @if ($pengajuanRapor->catatan_revisi)
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-xs text-gray-700">
                        <p class="font-bold text-gray-700 mb-1">Catatan Keputusan / Arahan Revisi:</p>
                        <p class="leading-relaxed whitespace-pre-line text-gray-600">{{ $pengajuanRapor->catatan_revisi }}</p>
                    </div>
                @endif
            </div>
        @else
            {{-- ACTIVE DECISION FORM (Executive Interactive Suite) --}}
            <form
                method="POST"
                action="{{ route('admin.rapor.persetujuan.decision', $pengajuanRapor) }}"
                x-data="{ action: 'APPROVE', catatan: '' }"
                class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-5"
            >
                @csrf
                <div>
                    <h3 class="font-display text-base font-bold text-gray-900">Keputusan Pengesahan Rapor</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Pilih tindakan untuk rapor kelas ini. Pastikan Anda telah meninjau matriks nilai dan catatan siswa di atas.</p>
                </div>

                {{-- Two Big Interactive Choice Cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Option 1: Setujui --}}
                    <label
                        @click="action = 'APPROVE'"
                        class="relative flex cursor-pointer rounded-2xl border-2 p-4 transition-all duration-200 focus-within:ring-2 focus-within:ring-emerald-500 focus-within:ring-offset-2"
                        :class="action === 'APPROVE' ? 'border-emerald-500 bg-emerald-50/40 shadow-xs ring-2 ring-emerald-500/20' : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50/50'"
                    >
                        <input type="radio" name="action" value="APPROVE" x-model="action" class="sr-only">
                        <div class="flex items-start gap-3.5 w-full">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition"
                                :class="action === 'APPROVE' ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-gray-500'"
                            >
                                <x-icon name="check_circle" class="h-5 w-5" />
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="font-display font-bold text-sm text-gray-900">Setujui Rapor Kelas</p>
                                    <span
                                        x-show="action === 'APPROVE'"
                                        class="h-4 w-4 rounded-full bg-emerald-500 text-white flex items-center justify-center"
                                    ><x-icon name="check" class="h-2.5 w-2.5" /></span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                    Loloskan rapor kelas ini untuk tahap persetujuan berikutnya atau langsung siap cetak/dibagikan kepada wali murid.
                                </p>
                            </div>
                        </div>
                    </label>

                    {{-- Option 2: Tolak & Minta Revisi --}}
                    <label
                        @click="action = 'REJECT'"
                        class="relative flex cursor-pointer rounded-2xl border-2 p-4 transition-all duration-200 focus-within:ring-2 focus-within:ring-rose-500 focus-within:ring-offset-2"
                        :class="action === 'REJECT' ? 'border-rose-500 bg-rose-50/40 shadow-xs ring-2 ring-rose-500/20' : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50/50'"
                    >
                        <input type="radio" name="action" value="REJECT" x-model="action" class="sr-only">
                        <div class="flex items-start gap-3.5 w-full">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition"
                                :class="action === 'REJECT' ? 'bg-rose-500 text-white' : 'bg-gray-100 text-gray-500'"
                            >
                                <x-icon name="warning" class="h-5 w-5" />
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="font-display font-bold text-sm text-gray-900">Tolak, Minta Revisi Wali Kelas</p>
                                    <span
                                        x-show="action === 'REJECT'"
                                        class="h-4 w-4 rounded-full bg-rose-500 text-white flex items-center justify-center"
                                    ><x-icon name="check" class="h-2.5 w-2.5" /></span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                    Kembalikan berkas pengajuan ke wali kelas untuk melengkapi nilai yang belum tuntas atau memperbaiki catatan.
                                </p>
                            </div>
                        </div>
                    </label>
                </div>

                {{-- Dynamic Catatan Field --}}
                <div class="space-y-1.5 pt-1">
                    <div class="flex items-center justify-between">
                        <label
                            class="block text-xs font-semibold"
                            :class="action === 'REJECT' ? 'text-rose-700' : 'text-gray-700'"
                            x-text="action === 'REJECT' ? 'Catatan (Wajib diisi untuk penolakan)' : 'Catatan (Opsional)'"
                        ></label>
                        <span class="text-[11px] text-gray-400" x-show="action === 'REJECT'">
                            Wajib disertakan sebagai panduan wali kelas
                        </span>
                    </div>
                    <textarea
                        name="catatan"
                        rows="3"
                        x-model="catatan"
                        :placeholder="action === 'REJECT' ? 'Tuliskan alasan penolakan atau instruksi revisi secara spesifik untuk wali kelas...' : 'Tambahkan pesan apresiasi atau catatan pengesahan (opsional)...'"
                        class="w-full rounded-xl border-gray-200 text-xs shadow-2xs transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                    ></textarea>
                </div>

                {{-- Semantic Submit Button --}}
                <div class="flex items-center justify-end pt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-xs font-bold text-white shadow-xs transition-all duration-200"
                        :class="action === 'APPROVE' ? 'bg-emerald-600 hover:bg-emerald-700 hover:shadow-md' : 'bg-rose-600 hover:bg-rose-700 hover:shadow-md'"
                    >
                        <x-icon name="check" class="h-3.5 w-3.5" x-show="action === 'APPROVE'" />
                        <x-icon name="close" class="h-3.5 w-3.5" x-show="action === 'REJECT'" />
                        <span x-text="action === 'APPROVE' ? 'Setujui Rapor Kelas Ini' : 'Tolak, Minta Revisi Wali Kelas'"></span>
                    </button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>

<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-5 pt-2">

        {{-- ─── Header & Breadcrumb ─────────────────────────────── --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-xl font-bold text-gray-900">Nilai &amp; Rapor Saya</h1>
                    @if ($siswa?->kelas)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                            <x-icon name="school" class="h-3.5 w-3.5" />
                            <span>{{ $siswa->kelas->nama }}</span>
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-xs text-gray-500">
                    Pantau pencapaian hasil belajar mata pelajaran dan unduh rapor resmi Anda.
                </p>
            </div>
            <p class="text-xs text-gray-400 shrink-0">
                Portal Siswa <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-600">Nilai &amp; Rapor</b>
            </p>
        </div>

        {{-- ─── KPI Summary Cards ───────────────────────────────── --}}
        @php
            $nilaiRataRata = $nilaiList->avg('nilai_angka');
            $nilaiTertinggi = $nilaiList->max('nilai_angka');
            $nilaiTerendah = $nilaiList->min('nilai_angka');
            $currentSemester = $semesterList->firstWhere('id', $semesterId);
        @endphp
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {{-- 1. Jumlah Mata Pelajaran --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-brand-600">Mata Pelajaran</p>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $nilaiList->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-brand-50 p-2.5 text-brand-600">
                        <x-icon name="menu_book" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1.5 text-[11px] text-gray-500">
                    {{ $currentSemester?->nama ?? 'Semester terpilih' }}
                </p>
            </div>

            {{-- 2. Nilai Rata-rata --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Rata-rata</p>
                        <p class="mt-1 font-display text-2xl font-bold text-{{ $nilaiRataRata >= 85 ? 'emerald' : ($nilaiRataRata >= 75 ? 'amber' : 'rose') }}-700">
                            {{ $nilaiList->isNotEmpty() ? number_format($nilaiRataRata, 1) : '—' }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-2.5 text-emerald-600">
                        <x-icon name="assessment" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1.5 text-[11px] text-gray-500">
                    @if ($nilaiList->isNotEmpty())
                        Tertinggi {{ $nilaiTertinggi }} · Terendah {{ $nilaiTerendah }}
                    @else
                        Belum ada nilai tercatat
                    @endif
                </p>
            </div>

            {{-- 3. Status Rapor --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-purple-600">Status Rapor</p>
                        <p class="mt-1 font-display text-sm font-bold text-gray-900">
                            @if ($pengajuanRapor)
                                Disetujui
                            @elseif ($pengajuanRaporSemua?->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diverifikasi)
                                Diverifikasi
                            @elseif ($pengajuanRaporSemua?->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diajukan)
                                Menunggu
                            @elseif ($pengajuanRaporSemua?->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Ditolak)
                                Perlu Revisi
                            @else
                                Belum Diajukan
                            @endif
                        </p>
                    </div>
                    <div class="rounded-xl {{ $pengajuanRapor ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-400' }} p-2.5">
                        <x-icon name="{{ $pengajuanRapor ? 'verified' : 'pending' }}" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1.5 text-[11px] text-gray-500">
                    {{ $pengajuanRapor ? 'Rapor siap diunduh' : 'Belum tersedia untuk diunduh' }}
                </p>
            </div>

            {{-- 4. Semester Aktif --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Semester</p>
                        <p class="mt-1 font-display text-base font-bold text-gray-900">
                            {{ $currentSemester?->nama ?? '—' }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-2.5 text-amber-600">
                        <x-icon name="calendar_month" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1.5 text-[11px] text-gray-500">
                    {{ $semesterList->count() }} semester tersedia
                </p>
            </div>
        </div>

        {{-- ─── Filter Semester (TomSelect, No Reload) ─────────── --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                <div class="flex-1">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-600">
                        Pilih Semester
                    </label>
                    <select
                        id="semester_id_select"
                        name="semester_id"
                        class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm"
                        onchange="window.location.href = '{{ route('admin.nilai-rapor-saya.index') }}?semester_id=' + this.value"
                    >
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}" @selected($sem->id == $semesterId)>
                                {{ $sem->nama }}{{ $sem->status_aktif ? ' (Aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if ($siswa?->kelas)
                    <div class="hidden sm:flex items-center gap-2 text-xs text-gray-400 pb-2">
                        <x-icon name="person" class="h-4 w-4 shrink-0" />
                        <span>{{ $siswa->nama_lengkap }}</span>
                        <span class="text-gray-300">&middot;</span>
                        <span class="font-semibold text-gray-600">{{ $siswa->kelas->nama }}</span>
                        @if ($siswa->nis)
                            <span class="text-gray-300">&middot;</span>
                            <span>NIS: <b class="font-medium text-gray-700">{{ $siswa->nis }}</b></span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- ─── Rapor Download Card (only when approved) ────────── --}}
        @if ($pengajuanRapor)
            <div class="rounded-2xl border border-emerald-200 bg-gradient-to-r from-emerald-50/60 via-white to-emerald-50/30 p-5 shadow-card">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3.5">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-md shadow-emerald-500/20">
                            <x-icon name="receipt" class="h-6 w-6" />
                        </span>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-display text-base font-bold text-gray-900">Rapor Resmi Tersedia</h3>
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                    <x-icon name="check_circle" class="h-3.5 w-3.5 text-emerald-600" />
                                    Disetujui
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-gray-500">
                                Rapor hasil belajar {{ $currentSemester?->nama }} sudah disetujui dan siap diunduh dalam format PDF.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        x-data
                        @click="$store.imagePreview.buka('{{ route('admin.nilai-rapor-saya.unduh-rapor', ['semester_id' => $semesterId]) }}?inline=1', 'Rapor Saya - {{ $currentSemester?->nama }}', true)"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition active:scale-95 shrink-0"
                    >
                        <x-icon name="download" class="h-4 w-4" />
                        <span>Unduh Rapor PDF</span>
                    </button>
                </div>
            </div>
        @elseif ($pengajuanRaporSemua)
            {{-- Rapor belum disetujui tapi sudah ada pengajuan --}}
            <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 shadow-xs flex items-start gap-3">
                <x-icon name="info" class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                <div>
                    <p class="text-sm font-semibold text-amber-900">
                        Rapor {{ $currentSemester?->nama }} Belum Tersedia untuk Diunduh
                    </p>
                    <p class="mt-0.5 text-xs text-amber-700">
                        Status saat ini: <strong>{{ $pengajuanRaporSemua->status->label() }}</strong>.
                        Rapor akan tersedia setelah mendapat persetujuan dari Kepala Sekolah.
                    </p>
                </div>
            </div>
        @endif

        {{-- ─── Tabel Nilai ─────────────────────────────────────── --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <div>
                    <h3 class="font-display text-sm font-bold text-gray-900">Daftar Nilai Hasil Belajar</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $currentSemester?->nama ?? 'Semester terpilih' }}</p>
                </div>
                <span class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-semibold text-gray-600">
                    {{ $nilaiList->count() }} Mata Pelajaran
                </span>
            </div>

            @if ($nilaiList->isEmpty())
                <div class="py-16 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50">
                        <x-icon name="assessment" class="h-8 w-8 text-gray-300" />
                    </div>
                    <h4 class="mt-4 font-display text-sm font-semibold text-gray-700">Belum Ada Nilai</h4>
                    <p class="mt-1.5 text-xs text-gray-400 max-w-xs mx-auto">
                        Belum ada nilai yang tercatat untuk {{ $currentSemester?->nama ?? 'semester ini' }}.
                        Nilai akan muncul setelah guru mata pelajaran menginput asesmen.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3.5">Mata Pelajaran</th>
                                <th class="px-5 py-3.5">Asesmen / Komponen Nilai</th>
                                <th class="px-5 py-3.5 w-36 text-center">Nilai Akhir</th>
                                <th class="px-5 py-3.5 w-28 text-center">Ketuntasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($nilaiList as $nilai)
                                @php
                                    $mapelNama = $nilai->komponenPenilaian?->subjek?->nama ?? $nilai->asesmen?->subjek?->nama ?? '—';
                                    $asesmenNama = $nilai->asesmen?->nama ?? $nilai->komponenPenilaian?->nama ?? '—';
                                    $nilaiAngka = $nilai->nilai_angka;
                                    $kktp = $nilai->komponenPenilaian?->kktp_minimal ?? 75;
                                    $lulus = $nilaiAngka !== null && $nilaiAngka >= $kktp;
                                @endphp
                                <tr class="transition hover:bg-brand-50/20">
                                    {{-- Mata Pelajaran --}}
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-[10px] font-bold text-brand-700 border border-brand-100">
                                                {{ mb_substr($mapelNama, 0, 2) }}
                                            </div>
                                            <span class="font-semibold text-gray-900">{{ $mapelNama }}</span>
                                        </div>
                                    </td>

                                    {{-- Asesmen / Komponen --}}
                                    <td class="px-5 py-3.5 text-xs text-gray-500">
                                        {{ $asesmenNama }}
                                    </td>

                                    {{-- Nilai Akhir --}}
                                    <td class="px-5 py-3.5 text-center">
                                        @if ($nilaiAngka !== null)
                                            <span class="inline-flex items-center justify-center w-12 rounded-xl px-2 py-1 text-sm font-bold
                                                {{ $nilaiAngka >= 85 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                   ($nilaiAngka >= 75 ? 'bg-amber-50 text-amber-700 border border-amber-200' :
                                                   'bg-rose-50 text-rose-700 border border-rose-200') }}">
                                                {{ $nilaiAngka }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400 italic">—</span>
                                        @endif
                                    </td>

                                    {{-- Ketuntasan --}}
                                    <td class="px-5 py-3.5 text-center">
                                        @if ($nilaiAngka !== null)
                                            @if ($lulus)
                                                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                                                    <x-icon name="check_circle" class="h-3 w-3 text-emerald-600" />
                                                    Tuntas
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                                    Belum Tuntas
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-xs text-gray-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Legend / Summary Footer --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-gray-50/60 px-5 py-3">
                    <div class="flex flex-wrap items-center gap-3 text-[11px] text-gray-500">
                        <span class="inline-flex items-center gap-1">
                            <span class="inline-block h-2.5 w-2.5 rounded-sm bg-emerald-400"></span>
                            ≥ 85 Sangat Baik
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="inline-block h-2.5 w-2.5 rounded-sm bg-amber-400"></span>
                            75–84 Baik
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="inline-block h-2.5 w-2.5 rounded-sm bg-rose-400"></span>
                            &lt; 75 Perlu Perbaikan
                        </span>
                    </div>
                    @if ($nilaiRataRata !== null)
                        <span class="text-[11px] font-semibold text-gray-600">
                            Rata-rata: {{ number_format($nilaiRataRata, 1) }}
                        </span>
                    @endif
                </div>
            @endif
        </div>

        {{-- ─── Not a Siswa Guard ───────────────────────────────── --}}
        @if (! $siswa)
            <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-6 text-center shadow-xs">
                <x-icon name="person_off" class="mx-auto h-10 w-10 text-amber-400" />
                <h4 class="mt-3 font-display text-base font-bold text-amber-900">Akun Belum Terhubung ke Data Siswa</h4>
                <p class="mt-1 text-xs text-amber-700">Hubungi admin sekolah untuk menghubungkan akun Anda ke data siswa.</p>
            </div>
        @endif

    </div>

    {{-- Initialize TomSelect for Semester Select --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const el = document.getElementById('semester_id_select');
            if (el && window.TomSelect) {
                new window.TomSelect(el, {
                    maxItems: 1,
                    create: false,
                    placeholder: 'Pilih semester...',
                });
            }
        });
    </script>
</x-app-layout>

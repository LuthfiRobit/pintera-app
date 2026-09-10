@if (isset($stats))
    <div data-rapor-stats="{{ json_encode($stats) }}" class="hidden" aria-hidden="true"></div>
@endif

@if (! $kelas || ! $semester)
    <div class="rounded-2xl border border-gray-200 bg-white p-12 text-center text-sm text-gray-500 shadow-card">
        <x-icon name="info" class="mx-auto h-8 w-8 text-gray-400 mb-2" />
        <p class="font-medium text-gray-700">Pilih kelas dan semester untuk melihat daftar siswa.</p>
    </div>
@else
    {{-- Status Notification Alerts (Jika Ditolak / Diverifikasi / Nilai Kosong) -- diletakkan di
         partial ini (bukan index.blade.php) supaya ikut ter-refresh setiap kali filter/kelas
         diganti via AJAX, bukan basi mengikuti kelas yang dipilih saat load awal. --}}
    @if ($pengajuanRapor && $pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Ditolak)
        <div class="rounded-2xl border border-rose-200 bg-rose-50/90 p-4 text-sm text-rose-800 shadow-xs flex items-start gap-3 mb-4">
            <x-icon name="warning" class="h-5 w-5 text-rose-600 shrink-0 mt-0.5" />
            <div class="space-y-1">
                <p class="font-bold">Pengajuan rapor kelas ini dikembalikan / perlu direvisi oleh pimpinan.</p>
                @if ($pengajuanRapor->catatan_revisi)
                    <p class="text-xs text-rose-700 bg-white/80 p-2.5 rounded-xl border border-rose-200 font-medium">
                        <span class="font-bold text-rose-900">Catatan Pimpinan:</span> {{ $pengajuanRapor->catatan_revisi }}
                    </p>
                @endif
                <p class="text-xs text-rose-600">Silakan lakukan penyesuaian catatan sesuai arahan lalu ajukan ulang rapor.</p>
            </div>
        </div>
    @endif

    @if ($pengajuanRapor && in_array($pengajuanRapor->status, [\App\Domains\Akademik\Enums\StatusPengajuanRapor::Diverifikasi, \App\Domains\Akademik\Enums\StatusPengajuanRapor::Disetujui]))
        <div class="rounded-2xl border border-blue-200 bg-blue-50/80 p-4 text-sm text-blue-800 shadow-xs flex items-center gap-3 mb-4">
            <x-icon name="info" class="h-5 w-5 text-blue-600 shrink-0" />
            <div>
                <p class="font-semibold">Rapor kelas ini sudah berstatus "{{ $pengajuanRapor->status->label() }}" sejak {{ $pengajuanRapor->diajukan_pada?->translatedFormat('d F Y, H:i') }}. Tidak bisa diajukan ulang dari halaman ini.</p>
                <p class="text-xs text-blue-600 mt-0.5">Pengajuan telah dikunci dan sedang dalam tahap finalisasi pengesahan.</p>
            </div>
        </div>
    @endif

    @if ($kelengkapanNilai->isNotEmpty())
        @php
            $totalSiswaBelumLengkap = $kelengkapanNilai->sum(fn ($sel) => $sel->siswaBelumLengkap->count());
        @endphp
        <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-800 shadow-xs mb-4" x-data="{ bukaRincian: false }">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <x-icon name="warning" class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-bold">Perhatian: masih ada {{ $totalSiswaBelumLengkap }} nilai yang kosong di kelas ini.</p>
                        <p class="text-xs text-amber-700 mt-0.5">Anda tetap dapat mengajukan rapor kelas, namun pastikan hal ini telah dikoordinasikan dengan guru mata pelajaran terkait.</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="bukaRincian = !bukaRincian"
                    class="text-xs font-semibold text-amber-800 hover:text-amber-950 underline shrink-0"
                    x-text="bukaRincian ? 'Tutup Rincian' : 'Lihat Rincian'"
                ></button>
            </div>

            <div x-show="bukaRincian" x-collapse class="mt-3 pt-3 border-t border-amber-200/60 text-xs space-y-1.5">
                @foreach ($kelengkapanNilai as $sel)
                    <div class="flex items-center justify-between text-amber-900 bg-white/60 px-3 py-1.5 rounded-lg">
                        <span class="font-semibold">{{ $sel->subjek->nama }}</span>
                        <span class="text-amber-800">
                            {{ $sel->siswaBelumLengkap->count() }} dari {{ $sel->totalSiswa }} siswa belum lengkap
                            ({{ $sel->siswaBelumLengkap->pluck('nama_lengkap')->join(', ') }})
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-700">
            <thead class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-5 py-3.5 w-44">Aksi</th>
                    <th class="px-5 py-3.5">Nama Siswa &amp; NIS</th>
                    <th class="px-5 py-3.5 w-48">Status Catatan</th>
                    <th class="px-5 py-3.5">Ringkasan Catatan / Ekskul</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($siswaList as $siswa)
                    <tr class="transition hover:bg-brand-50/20 group">
                        {{-- 1. AKSI (di Kiri Sesuai Standar Pintera & Gambar Referensi) --}}
                        <td class="px-5 py-4 w-44">
                            <div class="flex items-center gap-1.5 shrink-0">
                                <a
                                    href="{{ route('guru.rapor.catatan.edit', ['siswa' => $siswa->id, 'semester_id' => $semester->id]) }}"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-600 hover:bg-gray-50 hover:text-brand-700 transition shadow-2xs"
                                >
                                    <x-icon name="edit" class="h-3.5 w-3.5 text-gray-500" />
                                    <span>Edit</span>
                                </a>

                                <button
                                    type="button"
                                    @click="$store.imagePreview.buka('{{ route('guru.rapor.cetak', ['siswa' => $siswa->id, 'semester_id' => $semester->id]) }}?inline=1', 'Pratinjau Rapor - {{ $siswa->nama_lengkap }}', true)"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition shadow-2xs"
                                >
                                    <x-icon name="print" class="h-3.5 w-3.5 text-gray-500" />
                                    <span>PDF</span>
                                </button>
                            </div>
                        </td>

                        {{-- 2. NAMA SISWA & NIS --}}
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 font-display text-xs font-bold text-brand-700 border border-brand-100">
                                    {{ substr($siswa->nama_lengkap, 0, 2) }}
                                </div>
                                <div>
                                    <p class="font-display font-bold text-gray-900 group-hover:text-brand-700 transition">
                                        {{ $siswa->nama_lengkap }}
                                    </p>
                                    <p class="text-xs text-gray-500 flex items-center gap-2 mt-0.5">
                                        <span>NIS: <b class="font-medium text-gray-700">{{ $siswa->nis ?? '-' }}</b></span>
                                        @if ($siswa->nisn)
                                            <span class="text-gray-300">&bull;</span>
                                            <span>NISN: <b class="font-medium text-gray-700">{{ $siswa->nisn }}</b></span>
                                        @endif
                                        @if ($siswa->gender)
                                            <span class="text-gray-300">&bull;</span>
                                            <span class="text-gray-400">{{ $siswa->gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </td>

                        {{-- 3. STATUS CATATAN --}}
                        <td class="px-5 py-4 w-48">
                            @if ($siswa->catatan_lengkap)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                    <x-icon name="check_circle" class="h-3.5 w-3.5 text-emerald-600" />
                                    <span>Lengkap</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    <span>Belum Lengkap</span>
                                </span>
                            @endif
                        </td>

                        {{-- 4. RINGKASAN CATATAN / EKSKUL --}}
                        <td class="px-5 py-4">
                            @if ($siswa->catatan)
                                <div class="space-y-1 max-w-md">
                                    @if ($siswa->catatan->catatan_perkembangan || $siswa->catatan->catatan_sikap)
                                        <p class="text-xs text-gray-700 line-clamp-1 italic text-ellipsis overflow-hidden">
                                            "{{ Str::limit($siswa->catatan->catatan_perkembangan ?: $siswa->catatan->catatan_sikap, 80) }}"
                                        </p>
                                    @endif
                                    <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                                        @php
                                            $ekskulCount = is_array($siswa->catatan->ekstrakurikuler) ? count($siswa->catatan->ekstrakurikuler) : 0;
                                            $prestasiCount = is_array($siswa->catatan->prestasi) ? count($siswa->catatan->prestasi) : 0;
                                        @endphp
                                        @if ($ekskulCount > 0)
                                            <span class="inline-flex items-center gap-1 rounded-md bg-purple-50 px-1.5 py-0.5 font-medium text-purple-700 border border-purple-200">
                                                <x-icon name="badge" class="h-3 w-3" />
                                                <span>{{ $ekskulCount }} Ekskul</span>
                                            </span>
                                        @endif
                                        @if ($prestasiCount > 0)
                                            <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-1.5 py-0.5 font-medium text-amber-800 border border-amber-200">
                                                <x-icon name="verified" class="h-3 w-3" />
                                                <span>{{ $prestasiCount }} Prestasi</span>
                                            </span>
                                        @endif
                                        @if (! $siswa->catatan->catatan_perkembangan && ! $siswa->catatan->catatan_sikap && $ekskulCount === 0 && $prestasiCount === 0)
                                            <span class="text-xs text-gray-400">Catatan tersimpan (draft)</span>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <span class="text-xs text-gray-400 italic">Belum ada catatan di semester ini</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center text-gray-500">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
                                <x-icon name="inbox" class="h-6 w-6" />
                            </div>
                            <p class="font-display text-sm font-bold text-gray-800">
                                Belum Ada Siswa Terdaftar
                            </p>
                            <p class="mt-1 text-xs text-gray-500 max-w-sm mx-auto">
                                Belum ada siswa terdaftar di kelas ini atau tidak ada siswa yang cocok dengan filter pencarian.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Bottom Action Bar for Submission -- kelengkapan WAJIB dihitung dari $stats (seluruh
         roster kelas), BUKAN dari $siswaList yang bisa sudah kena filter pencarian/status.
         Kalau dipakai $siswaList di sini, memfilter tab "Catatan Lengkap" bisa membuat tombol
         ajukan aktif secara keliru walau di luar filter masih ada siswa yang belum lengkap. --}}
    @if ($kelas && $semester && ($stats['totalSiswa'] ?? 0) > 0)
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-gray-100 bg-gray-50/60 px-5 py-4">
            <div class="text-xs text-gray-600">
                @if (($stats['totalBelumLengkap'] ?? 0) === 0)
                    <span class="text-emerald-700 font-semibold flex items-center gap-1.5">
                        <x-icon name="check_circle" class="h-4 w-4 text-emerald-600 shrink-0" />
                        <span>Semua catatan siswa telah lengkap ({{ $stats['totalSiswa'] ?? 0 }} siswa). Siap diajukan ke Waka Kurikulum.</span>
                    </span>
                @else
                    <span class="text-amber-800 font-medium flex items-center gap-1.5">
                        <x-icon name="info" class="h-4 w-4 text-amber-600 shrink-0" />
                        <span>Lengkapi seluruh catatan siswa terlebih dahulu sebelum mengajukan rapor kelas.</span>
                    </span>
                @endif
            </div>

            <form
                method="POST"
                action="{{ route('guru.rapor.pengajuan.submit') }}"
                @if ($kelengkapanNilai->isNotEmpty())
                    onsubmit="return confirm('Masih ada nilai yang kosong di kelas ini. Anda tetap dapat mengajukan rapor, namun pastikan hal ini sudah dikoordinasikan. Lanjutkan mengajukan?')"
                @endif
            >
                @csrf
                <input type="hidden" name="kelas_id" value="{{ $kelas->id }}">
                <input type="hidden" name="semester_id" value="{{ $semester->id }}">
                <x-primary-button
                    type="submit"
                    :disabled="($stats['totalBelumLengkap'] ?? 0) > 0 || in_array($pengajuanRapor?->status, [\App\Domains\Akademik\Enums\StatusPengajuanRapor::Diverifikasi, \App\Domains\Akademik\Enums\StatusPengajuanRapor::Disetujui])"
                    class="shadow-sm"
                >
                    <x-icon name="send" class="h-4 w-4 mr-1.5" />
                    <span>Ajukan Rapor untuk Verifikasi</span>
                </x-primary-button>
            </form>
        </div>
    @endif
</div>
@endif

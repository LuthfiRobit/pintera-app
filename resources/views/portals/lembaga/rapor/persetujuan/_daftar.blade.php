@if (isset($stats))
    <div data-rapor-stats="{{ json_encode($stats) }}" class="hidden" aria-hidden="true"></div>
@endif
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-700">
            <thead class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-5 py-3.5 w-44">Aksi</th>
                    <th class="px-5 py-3.5">Kelas &amp; Wali Kelas</th>
                    <th class="px-5 py-3.5">Tahun Ajaran &amp; Semester</th>
                    <th class="px-5 py-3.5">Status Alur</th>
                    <th class="px-5 py-3.5">Diajukan Pada</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($pengajuanList as $pengajuan)
                    @php
                        $waliKelasNama = $pengajuan->kelas->waliKelas?->nama ?? $pengajuan->diajukanOleh?->name ?? 'Belum ditentukan';
                        $statusEnum = $pengajuan->status;
                    @endphp
                    <tr class="transition hover:bg-brand-50/20 group">
                        {{-- Aksi (di Kiri sesuai Standar) --}}
                        <td class="px-5 py-4 w-44">
                            @if (($tab ?? 'menunggu') === 'riwayat')
                                <a
                                    href="{{ route('admin.rapor.persetujuan.show', $pengajuan) }}"
                                    class="inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-2xs transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"
                                >
                                    <span>Lihat Detail</span>
                                    <span class="text-gray-400">&rsaquo;</span>
                                </a>
                            @else
                                <a
                                    href="{{ route('admin.rapor.persetujuan.show', $pengajuan) }}"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-xs transition hover:bg-brand-700 hover:shadow-sm"
                                >
                                    <span>Review &amp; Keputusan</span>
                                    <span>&rarr;</span>
                                </a>
                            @endif
                        </td>

                        {{-- Kelas & Wali Kelas --}}
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 font-display text-sm font-bold text-brand-700 border border-brand-100">
                                    {{ substr($pengajuan->kelas->nama, 0, 2) }}
                                </div>
                                <div>
                                    <p class="font-display font-bold text-gray-900 group-hover:text-brand-700 transition">
                                        {{ $pengajuan->kelas->nama }}
                                    </p>
                                    <p class="text-xs text-gray-500 flex items-center gap-1 mt-0.5">
                                        <span>Wali Kelas:</span>
                                        <span class="font-medium text-gray-700">{{ $waliKelasNama }}</span>
                                    </p>
                                </div>
                            </div>
                        </td>

                        {{-- Tahun Ajaran & Semester --}}
                        <td class="px-5 py-4">
                            <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50/80 px-2.5 py-1 text-xs font-medium text-gray-700">
                                <x-icon name="apartment" class="h-3 w-3 text-gray-400" />
                                <span>{{ $pengajuan->semester->nama }} — {{ $pengajuan->kelas->tahunAjaran->nama }}</span>
                            </span>
                        </td>

                        {{-- Status Alur --}}
                        <td class="px-5 py-4">
                            @if ($statusEnum === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diajukan)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    Menunggu Verifikasi Waka
                                </span>
                            @elseif ($statusEnum === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diverifikasi)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                    Menunggu Persetujuan Kepsek
                                </span>
                            @elseif ($statusEnum === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Disetujui)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                    <x-icon name="check_circle" class="h-3.5 w-3.5 text-emerald-600" />
                                    Disetujui
                                </span>
                            @elseif ($statusEnum === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Ditolak)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">
                                    <x-icon name="warning" class="h-3.5 w-3.5 text-rose-600" />
                                    Perlu Revisi
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-xs font-semibold text-gray-600">
                                    {{ $statusEnum->label() }}
                                </span>
                            @endif
                        </td>

                        {{-- Diajukan Pada --}}
                        <td class="px-5 py-4 text-xs text-gray-500">
                            <div>
                                <p class="font-medium text-gray-700">{{ $pengajuan->diajukan_pada?->format('d M Y H:i') ?? '—' }}</p>
                                @if ($pengajuan->diajukanOleh)
                                    <p class="text-[11px] text-gray-400">Oleh {{ $pengajuan->diajukanOleh->name }}</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-16 text-center text-gray-500">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
                                <x-icon name="inbox" class="h-6 w-6" />
                            </div>
                            <p class="font-display text-sm font-bold text-gray-800">
                                {{ ($tab ?? 'menunggu') === 'riwayat' ? 'Belum Ada Riwayat' : 'Tidak Ada Pengajuan Menunggu' }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 max-w-sm mx-auto">
                                {{ ($tab ?? 'menunggu') === 'riwayat' ? 'Belum ada riwayat keputusan persetujuan rapor.' : 'Tidak ada pengajuan rapor yang menunggu keputusan Anda saat ini.' }}
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

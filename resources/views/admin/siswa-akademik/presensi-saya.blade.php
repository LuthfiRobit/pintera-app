@php
    $dari = $dariTanggal ?? $startDate ?? now()->startOfMonth()->toDateString();
    $sampai = $sampaiTanggal ?? $endDate ?? now()->endOfMonth()->toDateString();
@endphp

<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6 pt-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold tracking-tight text-ink">Presensi Saya</h1>
                <p class="mt-1 text-sm text-slate">Catatan riwayat kehadiran kegiatan belajar mengajar Anda.</p>
            </div>
            @if ($siswa)
                <div class="inline-flex items-center gap-2 rounded-xl bg-paper px-3.5 py-1.5 border border-ink/10 text-xs font-medium text-slate">
                    <x-icon name="school" class="h-4 w-4 text-brand-500" />
                    <span>{{ $siswa->nama_lengkap }}</span>
                    <span class="text-ink/20">&middot;</span>
                    <span class="font-semibold text-ink">{{ $siswa->kelas?->nama ?? 'Belum Ada Kelas' }}</span>
                </div>
            @endif
        </div>

        <x-panel class="p-6">
            <form method="GET" action="{{ route('admin.presensi-saya.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:items-end">
                <div>
                    <x-input-label for="dari_tanggal" value="Dari Tanggal" class="font-medium text-xs text-slate uppercase tracking-wider" />
                    <input type="date" id="dari_tanggal" name="dari_tanggal" value="{{ $dari }}" class="mt-1.5 block w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <x-input-label for="sampai_tanggal" value="Sampai Tanggal" class="font-medium text-xs text-slate uppercase tracking-wider" />
                    <input type="date" id="sampai_tanggal" name="sampai_tanggal" value="{{ $sampai }}" class="mt-1.5 block w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition active:scale-95">
                        <x-icon name="filter" class="h-4 w-4" />
                        <span>Filter Presensi</span>
                    </button>
                </div>
            </form>
        </x-panel>

        <x-panel class="p-6">
            <div class="flex items-center justify-between border-b border-ink/10 pb-4">
                <div>
                    <h3 class="font-display text-lg font-bold text-ink">Riwayat Kehadiran</h3>
                    <p class="text-xs text-slate">
                        Periode: {{ \Carbon\Carbon::parse($dari)->translatedFormat('d M Y') }} - {{ \Carbon\Carbon::parse($sampai)->translatedFormat('d M Y') }}
                    </p>
                </div>
                <span class="rounded-full bg-paper px-3 py-1 text-xs font-medium text-slate">
                    {{ $riwayatList->total() }} Catatan
                </span>
            </div>

            @if ($riwayatList->isEmpty())
                <div class="py-12 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-paper text-slate">
                        <x-icon name="history" class="h-7 w-7" />
                    </span>
                    <h4 class="mt-3 font-display text-sm font-semibold text-ink">Belum Ada Catatan Presensi</h4>
                    <p class="mt-1 text-xs text-slate">Tidak ada catatan presensi pada rentang tanggal yang dipilih.</p>
                </div>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-ink/10 text-left text-sm">
                        <thead>
                            <tr class="text-[11px] font-semibold uppercase tracking-wider text-slate">
                                <th class="py-3 px-4">Tanggal & Waktu</th>
                                <th class="py-3 px-4">Mata Pelajaran</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/5">
                            @foreach ($riwayatList as $item)
                                @php
                                    $statusVal = $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status;
                                    $statusLabel = $item->status instanceof \App\Domains\Akademik\Enums\StatusPresensi ? $item->status->label() : ucfirst($statusVal);
                                    $tone = match ($statusVal) {
                                        'hadir' => 'green',
                                        'izin' => 'amber',
                                        'sakit' => 'red',
                                        'alpa' => 'red',
                                        'terlambat' => 'amber',
                                        default => 'slate',
                                    };
                                @endphp
                                <tr class="transition hover:bg-brand-50/20">
                                    <td class="py-3.5 px-4">
                                        <div class="font-medium text-ink">
                                            {{ optional($item->sesiPembelajaran)->tanggal?->translatedFormat('d M Y') ?? '-' }}
                                        </div>
                                        @if (optional($item->sesiPembelajaran)->jam_mulai)
                                            <div class="text-[11px] font-mono text-slate">
                                                {{ $item->sesiPembelajaran->jam_mulai }} - {{ $item->sesiPembelajaran->jam_selesai }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-medium text-ink">
                                            {{ $item->sesiPembelajaran?->mataPelajaran?->nama ?? optional(optional($item->sesiPembelajaran)->jadwalPelajaran)->mataPelajaran->nama ?? 'Tematik' }}
                                        </div>
                                        @if (optional($item->sesiPembelajaran)->guru)
                                            <div class="text-xs text-slate">
                                                {{ $item->sesiPembelajaran->guru->nama }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <x-badge :tone="$tone">{{ $statusLabel }}</x-badge>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs text-slate">
                                        {{ $item->keterangan ?: '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($riwayatList->hasPages())
                    <div class="mt-6 pt-4 border-t border-ink/10">
                        {{ $riwayatList->links() }}
                    </div>
                @endif
            @endif
        </x-panel>
    </div>
</x-app-layout>

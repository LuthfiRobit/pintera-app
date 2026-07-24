{{-- resources/views/portal/dashboard.blade.php --}}
<x-layouts.portal-dashboard title="Dashboard">
    <h1 class="text-[21px] font-bold text-gray-900">Halo, {{ Str::of(auth('portal')->user()->nama)->before(' ') }} 👋</h1>
    <p class="mt-1 text-[13px] text-gray-500">Berikut ringkasan pendaftaranmu di Pintera.</p>

    @if (! $progress && $pendaftaranList->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-gray-200 p-10 text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-portal-50 text-portal-300">
                <x-icon name="school" class="h-8 w-8" />
            </span>
            <h2 class="mt-4 text-[15px] font-bold text-gray-900">Kamu belum memilih lembaga &amp; jalur</h2>
            <p class="mx-auto mt-1.5 max-w-sm text-[13px] text-gray-500">Pilih lembaga dan jalur pendaftaran untuk mulai mengisi formulir.</p>
            <a href="{{ route('spmb.welcome') }}" class="mt-5 inline-flex items-center gap-2 rounded-[10px] bg-portal-500 px-5 py-2.5 text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                Pilih Lembaga &amp; Jalur
                <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
            </a>
        </div>
    @else
        @if ($progress)
            <div class="mt-5 grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                <div class="rounded-[20px] bg-[linear-gradient(155deg,#16324F_0%,#1E3A5F_70%)] p-6 text-white">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wide text-white/60">{{ $progress['lembaga']->nama }} &middot; Jalur {{ $progress['jalur']->nama }}</p>
                            <h2 class="mt-1 text-[18px] font-bold">Lengkapi Data Pendaftaran</h2>
                        </div>
                        <div class="text-right">
                            <p class="text-[26px] font-bold leading-none [font-variant-numeric:tabular-nums]">{{ $progress['persen'] }}%</p>
                            <p class="text-[10.5px] uppercase tracking-wide text-white/60">Selesai</p>
                        </div>
                    </div>

                    <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/15">
                        <div class="h-full rounded-full bg-white" style="width: {{ $progress['persen'] }}%"></div>
                    </div>

                    <div class="mt-4 flex justify-between text-[10.5px] text-white/60">
                        @foreach ($progress['langkah'] as $langkah)
                            <span class="{{ $langkah['done'] ? 'font-bold text-white' : ($langkah['active'] ? 'text-white' : '') }}">
                                {{ $langkah['done'] ? '✓ ' : '' }}{{ $langkah['label'] }}
                            </span>
                        @endforeach
                    </div>

                    <a href="{{ route($progress['lanjutkan_ke']) }}" class="mt-5 flex items-center justify-center gap-2 rounded-[10px] bg-white px-4 py-3 text-[13.5px] font-bold text-portal-500 transition hover:bg-gray-100">
                        Lanjutkan Pengisian
                        <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-3.5">
                    <div class="rounded-[14px] border border-gray-200 bg-white p-4">
                        <span class="flex h-[34px] w-[34px] items-center justify-center rounded-[9px] bg-portal-50 text-portal-500">
                            <x-icon name="description" class="h-4 w-4" />
                        </span>
                        <p class="mt-2.5 text-[15px] font-bold text-gray-900">{{ $progress['dokumen_terupload'] }} / {{ $progress['total_syarat_dokumen'] }}</p>
                        <p class="text-[11px] text-gray-400">Dokumen Terupload</p>
                    </div>
                    <div class="rounded-[14px] border border-gray-200 bg-white p-4">
                        <span class="flex h-[34px] w-[34px] items-center justify-center rounded-[9px] bg-portal-50 text-portal-500">
                            <x-icon name="payments" class="h-4 w-4" />
                        </span>
                        @if ($progress['nominal'] !== null)
                            <p class="mt-2.5 text-[15px] font-bold text-gray-900">Rp{{ number_format($progress['nominal'], 0, ',', '.') }}</p>
                            <p class="text-[11px] text-gray-400">Ditagihkan setelah selesai</p>
                        @else
                            <p class="mt-2.5 text-[15px] font-bold text-gray-900">&mdash;</p>
                            <p class="text-[11px] text-gray-400">Biaya Pendaftaran</p>
                        @endif
                    </div>
                    <div class="rounded-[14px] border border-gray-200 bg-white p-4">
                        <span class="flex h-[34px] w-[34px] items-center justify-center rounded-[9px] bg-portal-50 text-portal-500">
                            <x-icon name="schedule" class="h-4 w-4" />
                        </span>
                        <p class="mt-2.5 text-[15px] font-bold text-gray-900">{{ $progress['gelombang']?->tanggal_tutup?->translatedFormat('d M Y') ?? 'Tidak diketahui' }}</p>
                        <p class="text-[11px] text-gray-400">Batas {{ $progress['gelombang']?->nama ?? 'Gelombang' }}</p>
                    </div>
                    <div class="rounded-[14px] border border-gray-200 bg-white p-4">
                        <span class="flex h-[34px] w-[34px] items-center justify-center rounded-[9px] bg-portal-50 text-portal-500">
                            <x-icon name="quiz" class="h-4 w-4" />
                        </span>
                        <p class="mt-2.5 text-[15px] font-bold text-gray-900">Belum Ada</p>
                        <p class="text-[11px] text-gray-400">Jadwal Tes Seleksi</p>
                    </div>
                </div>
            </div>
        @endif

        @if ($pendaftaranList->isNotEmpty())
            <div class="{{ $progress ? 'mt-8' : 'mt-2' }} flex items-center justify-between">
                <h2 class="text-[15px] font-bold text-gray-900">Riwayat Pendaftaran</h2>
                <a href="{{ route('spmb.welcome') }}" class="inline-flex items-center gap-2 rounded-[10px] bg-portal-500 px-4 py-2 text-[12.5px] font-bold text-white transition hover:bg-portal-600">
                    <x-icon name="add" class="h-3.5 w-3.5" />
                    Daftar Lagi
                </a>
            </div>

            <div class="mt-3.5 space-y-2.5">
                @foreach ($pendaftaranList as $pendaftaran)
                    @php
                        $badge = match ($pendaftaran->status) {
                            'diterima' => ['success', 'check_circle', 'Diterima'],
                            'ditolak' => ['error', 'cancel', 'Ditolak'],
                            'daftar_ulang' => ['portal', 'autorenew', 'Daftar Ulang'],
                            'aktif' => ['success', 'check_circle', 'Aktif'],
                            default => ['warning', 'hourglass_empty', 'Menunggu Verifikasi'],
                        };
                        [$tone, $icon, $label] = $badge;
                    @endphp
                    <div class="flex flex-wrap items-center gap-3.5 rounded-[14px] border border-gray-200 bg-white px-[18px] py-3.5">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[11px] bg-portal-50 text-[12px] font-extrabold text-portal-500">
                            {{ $pendaftaran->lembaga->bentuk_pendidikan }}
                        </span>
                        <div class="min-w-[160px] flex-1">
                            <p class="text-[13.5px] font-bold text-gray-900">{{ $pendaftaran->calonMurid->nama_lengkap }}</p>
                            <p class="mt-0.5 text-[11.5px] text-gray-400">{{ $pendaftaran->lembaga->nama }} &middot; {{ $pendaftaran->jalurPpdb->nama }} &middot; {{ $pendaftaran->gelombangPpdb->nama }}</p>
                            <p class="mt-0.5 font-mono text-[11.5px] text-gray-400">{{ $pendaftaran->kode_pendaftaran }}</p>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-{{ $tone }}-50 px-2.5 py-1 text-[11.5px] font-bold text-{{ $tone }}-{{ $tone === 'portal' ? '500' : '700' }}">
                            <x-icon name="{{ $icon }}" class="h-2.5 w-2.5" />
                            {{ $label }}
                        </span>
                        <a href="{{ route('portal.pendaftaran.bukti', $pendaftaran) }}" target="_blank" class="shrink-0 text-[12.5px] font-bold text-portal-500 hover:underline">
                            Unduh Bukti (PDF)
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</x-layouts.portal-dashboard>

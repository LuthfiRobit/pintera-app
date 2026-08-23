{{-- resources/views/portal/tagihan/index.blade.php --}}
<x-portal-layout title="Tagihan & Pembayaran">
    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-spmb-accent">Portal Pendaftar</p>
    <h1 class="mt-1 font-display text-2xl font-bold text-spmb-primary">Tagihan & Pembayaran</h1>

    @if (session('status'))
        <div class="mt-4 rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-xl bg-signal-red/10 p-4 text-sm text-signal-red">{{ $errors->first() }}</div>
    @endif

    @forelse ($pendaftaranList as $pendaftaran)
        @if ($pendaftaran->tagihan->isNotEmpty())
            <x-panel class="mt-6 p-6">
                <p class="font-display font-semibold text-ink">{{ $pendaftaran->calonMurid->nama_lengkap }} &middot; {{ $pendaftaran->kode_pendaftaran }}</p>

                <div class="mt-4 space-y-6">
                    @foreach ($pendaftaran->tagihan as $tagihan)
                        <div class="border-t border-ink/10 pt-4 first:border-t-0 first:pt-0">
                            @php $pembayaranAktifTagihan = $tagihan->pembayaran->whereIn('status', ['menunggu_verifikasi', 'lunas'])->isNotEmpty(); @endphp
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-ink">
                                    {{ $tagihan->kategori === 'pendaftaran' ? 'Tagihan Pendaftaran' : 'Tagihan Daftar Ulang' }}
                                    — Rp {{ number_format($tagihan->total_tagihan, 0, ',', '.') }}
                                </p>
                                <x-badge :tone="$tagihan->status === 'lunas' ? 'green' : ($tagihan->status === 'dicicil' ? 'blue' : 'amber')">
                                    {{ ucfirst(str_replace('_', ' ', $tagihan->status)) }}
                                </x-badge>
                            </div>

                            @if ($tagihan->skemaCicilan)
                                <div class="mt-3 space-y-2">
                                    @foreach ($tagihan->cicilan as $termin)
                                        <div class="flex items-center justify-between rounded-xl bg-spmb-tint px-4 py-3 text-sm">
                                            <span class="text-ink">Termin {{ $termin->urutan }} — Rp {{ number_format($termin->nominal, 0, ',', '.') }}</span>
                                            @if (in_array($termin->status, ['belum_bayar', 'ditolak']) && ($termin->urutan === 1 || optional($tagihan->cicilan->firstWhere('urutan', $termin->urutan - 1))->status === 'lunas'))
                                                <form method="POST" action="{{ route('portal.tagihan.bayar-cicilan', $termin) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                                                    @csrf
                                                    <input type="file" name="bukti" required class="text-xs">
                                                    <x-spmb-primary-button class="!px-3 !py-1.5 !text-xs">Kirim Bukti</x-spmb-primary-button>
                                                </form>
                                            @else
                                                <x-badge :tone="$termin->status === 'lunas' ? 'green' : ($termin->status === 'menunggu_verifikasi' ? 'amber' : ($termin->status === 'ditolak' ? 'red' : 'slate'))">
                                                    {{ ucfirst(str_replace('_', ' ', $termin->status)) }}
                                                </x-badge>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @elseif ($tagihan->status === 'belum_bayar' && ! $pembayaranAktifTagihan)
                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <form method="POST" action="{{ route('portal.tagihan.bayar-lunas', $tagihan) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                                        @csrf
                                        <input type="file" name="bukti" required class="text-xs">
                                        <x-spmb-primary-button class="!px-3 !py-1.5 !text-xs">Bayar Lunas</x-spmb-primary-button>
                                    </form>

                                    @if (app(\App\Domains\Keuangan\Services\TagihanCicilanEligibilityService::class)->bisaDicicil($tagihan))
                                        <form method="POST" action="{{ route('portal.tagihan.skema-cicilan', $tagihan) }}" class="flex items-center gap-2">
                                            @csrf
                                            <select name="jumlah_termin" class="rounded-xl border-slate/25 text-xs">
                                                @for ($n = 2; $n <= app(\App\Domains\Keuangan\Services\TagihanCicilanEligibilityService::class)->maksCicilan($tagihan); $n++)
                                                    <option value="{{ $n }}">Cicil {{ $n }}x</option>
                                                @endfor
                                            </select>
                                            <button type="submit" class="text-xs font-semibold text-spmb-accent hover:underline">Mulai Cicil</button>
                                        </form>
                                    @endif
                                </div>
                            @endif

                            @if ($tagihan->riwayatPembayaran->isNotEmpty())
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-xs font-semibold text-spmb-accent">Riwayat Transaksi ({{ $tagihan->riwayatPembayaran->count() }})</summary>
                                    <div class="mt-2 space-y-2">
                                        @foreach ($tagihan->riwayatPembayaran as $riwayat)
                                            <div class="rounded-xl border border-ink/10 px-4 py-2.5 text-xs">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-slate">{{ $riwayat->created_at->translatedFormat('d M Y, H:i') }} &middot; {{ $riwayat->sumber === 'calon_siswa' ? 'Anda' : 'Dicatat admin' }}</span>
                                                    <x-badge :tone="$riwayat->status === 'lunas' ? 'green' : ($riwayat->status === 'menunggu_verifikasi' ? 'amber' : 'red')">
                                                        {{ $riwayat->status === 'menunggu_verifikasi' ? 'Menunggu verifikasi' : ucfirst($riwayat->status) }}
                                                    </x-badge>
                                                </div>
                                                @if ($riwayat->status === 'ditolak' && $riwayat->catatan_verifikasi)
                                                    <p class="mt-1 text-signal-red">{{ $riwayat->catatan_verifikasi }}</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-panel>
        @endif
    @empty
        <x-panel class="mt-6 p-8 text-center">
            <p class="text-sm text-slate">Belum ada tagihan.</p>
        </x-panel>
    @endforelse
</x-portal-layout>

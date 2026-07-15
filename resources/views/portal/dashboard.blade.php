{{-- resources/views/portal/dashboard.blade.php --}}
<x-portal-layout title="Dashboard">
    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-spmb-accent">Portal Pendaftar</p>
    <h1 class="mt-1 font-display text-2xl font-bold text-spmb-primary">Pendaftaran Saya</h1>

    @if ($pendaftaranList->isEmpty())
        <x-panel class="mt-6 p-8 text-center">
            <p class="text-sm text-slate">Belum ada pendaftaran yang tertaut ke akun ini.</p>
        </x-panel>
    @else
        <div class="mt-6 space-y-4">
            @foreach ($pendaftaranList as $pendaftaran)
                <x-panel class="p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-display font-semibold text-ink">{{ $pendaftaran->calonMurid->nama_lengkap }}</p>
                            <p class="mt-0.5 text-sm text-slate">{{ $pendaftaran->lembaga->nama }} &middot; {{ $pendaftaran->jalurPpdb->nama }} &middot; {{ $pendaftaran->gelombangPpdb->nama }}</p>
                            <p class="mt-0.5 font-mono text-xs text-slate">{{ $pendaftaran->kode_pendaftaran }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-badge :tone="$pendaftaran->status === 'diterima' ? 'green' : ($pendaftaran->status === 'ditolak' ? 'red' : 'amber')">
                                {{ $pendaftaran->status === 'diterima' ? 'Diterima' : ($pendaftaran->status === 'ditolak' ? 'Ditolak' : 'Menunggu Verifikasi') }}
                            </x-badge>
                            <a href="{{ route('portal.pendaftaran.bukti', $pendaftaran) }}" target="_blank" class="text-sm font-semibold text-spmb-accent hover:underline">
                                Unduh Bukti (PDF)
                            </a>
                        </div>
                    </div>
                </x-panel>
            @endforeach
        </div>
    @endif
</x-portal-layout>

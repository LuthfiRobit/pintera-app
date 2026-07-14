<x-spmb-public-layout :lembaga="$lembaga" title="Pendaftaran Berhasil" :langkah="6">
    <div class="space-y-4">
        <div class="flex items-center gap-3 rounded-2xl bg-signal-green/10 p-4 text-signal-green">
            <svg class="h-8 w-8 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <div>
                <p class="font-display font-bold">Pendaftaran Berhasil</p>
                <p class="text-sm">Data {{ $pendaftaran->calonMurid->nama_lengkap }} sudah kami terima.</p>
            </div>
        </div>

        <div class="rounded-2xl bg-spmb-primary p-6 text-center text-white shadow-elevated">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-white/70">Kode Pendaftaran Anda</p>
            <p class="mt-2 font-mono text-3xl font-bold tracking-widest">{{ $pendaftaran->kode_pendaftaran }}</p>
        </div>

        <x-panel class="p-6 text-center">
            <p class="text-sm text-slate">Kode ini juga sudah dikirim ke <span class="font-medium text-ink">{{ $pendaftaran->email_pendaftaran }}</span>. Simpan untuk cek status pendaftaran nanti.</p>
        </x-panel>
    </div>
</x-spmb-public-layout>

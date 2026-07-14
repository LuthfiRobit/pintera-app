<x-spmb-public-layout :lembaga="$lembaga" title="Pendaftaran Berhasil">
    <x-panel class="p-6 text-center">
        <p class="font-display text-lg font-bold text-signal-green">Pendaftaran Berhasil</p>
        <p class="mt-3 text-sm text-slate">Kode Pendaftaran Anda:</p>
        <p class="mt-1 font-mono text-2xl font-bold tracking-widest text-ink">{{ $pendaftaran->kode_pendaftaran }}</p>
        <p class="mt-4 text-sm text-slate">Kode ini juga sudah dikirim ke {{ $pendaftaran->email_pendaftaran }}. Simpan untuk cek status nanti.</p>
    </x-panel>
</x-spmb-public-layout>

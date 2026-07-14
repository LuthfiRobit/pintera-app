<x-spmb-public-layout :lembaga="$lembaga" title="Status Pendaftaran">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-spmb-primary">Status Pendaftaran</h2>

        @php
            $tonePerStatus = ['menunggu_verifikasi' => 'amber', 'diterima' => 'green', 'ditolak' => 'red'];
            $labelPerStatus = ['menunggu_verifikasi' => 'Menunggu Verifikasi', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'];
        @endphp
        <x-badge :tone="$tonePerStatus[$pendaftaran->status] ?? 'slate'" class="mt-3">
            {{ $labelPerStatus[$pendaftaran->status] ?? $pendaftaran->status }}
        </x-badge>

        <dl class="mt-5 divide-y divide-ink/10 text-sm">
            <div class="flex justify-between py-2"><dt class="text-slate">Nama</dt><dd class="font-medium text-ink">{{ $pendaftaran->calonMurid->nama_lengkap }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate">Jalur</dt><dd class="text-ink">{{ $pendaftaran->jalurPpdb->nama }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate">Kode Pendaftaran</dt><dd class="font-mono text-ink">{{ $pendaftaran->kode_pendaftaran }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate">Tanggal Submit</dt><dd class="text-ink">{{ $pendaftaran->submitted_at->translatedFormat('d F Y H:i') }}</dd></div>
        </dl>

        <x-link-button
            variant="ghost"
            href="{{ route('spmb.bukti', ['lembagaSlug' => $lembaga->slug, 'kodePendaftaran' => $pendaftaran->kode_pendaftaran, 'email' => $pendaftaran->email_pendaftaran]) }}"
            class="mt-6"
        >
            Unduh Bukti Pendaftaran (PDF)
        </x-link-button>
    </x-panel>
</x-spmb-public-layout>

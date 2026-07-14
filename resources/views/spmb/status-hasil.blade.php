<x-spmb-public-layout :lembaga="$lembaga" title="Status Pendaftaran">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Status Pendaftaran</h2>

        <span class="mt-3 inline-flex items-center rounded-full bg-signal-amber/10 px-3 py-1 text-sm font-bold text-signal-amber">
            @if ($pendaftaran->status === 'menunggu_verifikasi') Menunggu Verifikasi
            @elseif ($pendaftaran->status === 'diterima') Diterima
            @elseif ($pendaftaran->status === 'ditolak') Ditolak
            @else {{ $pendaftaran->status }}
            @endif
        </span>

        <dl class="mt-5 divide-y divide-ink/10 text-sm">
            <div class="flex justify-between py-2"><dt class="text-slate">Nama</dt><dd class="font-medium text-ink">{{ $pendaftaran->calonMurid->nama_lengkap }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate">Jalur</dt><dd class="text-ink">{{ $pendaftaran->jalurPpdb->nama }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate">Kode Pendaftaran</dt><dd class="font-mono text-ink">{{ $pendaftaran->kode_pendaftaran }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate">Tanggal Submit</dt><dd class="text-ink">{{ $pendaftaran->submitted_at->translatedFormat('d F Y H:i') }}</dd></div>
        </dl>

        <a
            href="{{ route('spmb.bukti', ['lembagaSlug' => $lembaga->slug, 'kodePendaftaran' => $pendaftaran->kode_pendaftaran, 'email' => $pendaftaran->email_pendaftaran]) }}"
            class="mt-6 inline-flex items-center gap-2 rounded-xl border border-ink/15 px-4 py-2.5 text-sm font-bold text-ink hover:bg-paper"
        >
            Unduh Bukti Pendaftaran (PDF)
        </a>
    </x-panel>
</x-spmb-public-layout>

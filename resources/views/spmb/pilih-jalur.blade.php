<x-spmb-public-layout :lembaga="$lembaga" title="Pilih Jalur">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Pilih Jalur Pendaftaran</h2>
        <p class="mt-1 text-sm text-slate">Gelombang: {{ $gelombang->nama }} &middot; Ditutup {{ $gelombang->tanggal_tutup->translatedFormat('d F Y') }}</p>

        <div class="mt-6 space-y-3">
            @foreach ($jalurList as $jalur)
                <a
                    href="{{ route('spmb.mulai', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}"
                    class="block rounded-xl border border-ink/10 p-4 transition hover:border-brass hover:bg-brass/5"
                >
                    <p class="font-display font-semibold text-ink">{{ $jalur->nama }}</p>
                    @if ($jalur->deskripsi)
                        <p class="mt-1 text-sm text-slate">{{ $jalur->deskripsi }}</p>
                    @endif
                </a>
            @endforeach
        </div>
    </x-panel>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('spmb.status.form', ['lembagaSlug' => $lembaga->slug]) }}" class="text-slate hover:text-ink">Sudah mendaftar? Cek status di sini</a>
    </p>
</x-spmb-public-layout>

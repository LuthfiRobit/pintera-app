<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keuangan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Nominal — {{ $jenisTagihan->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-xl">
        @if (! $tahunAjaranAktif)
            <x-panel class="p-6 text-sm text-slate">Tidak ada tahun ajaran aktif untuk lembaga ini.</x-panel>
        @else
            <x-panel class="p-6">
                <form method="POST" action="{{ route('admin.jenis-tagihan.nominal.store', $jenisTagihan) }}" class="space-y-4">
                    @csrf
                    @foreach ($jalurList as $jalur)
                        <div>
                            <x-input-label :value="$jalur->nama" />
                            <x-text-input type="number" step="0.01" min="0" name="nominal[{{ $jalur->id }}]" class="mt-1.5" :value="old('nominal.'.$jalur->id, $nominalMap[$jalur->id] ?? '')" placeholder="0 untuk gratis" />
                        </div>
                    @endforeach
                    <x-primary-button>Simpan Semua Nominal</x-primary-button>
                </form>
            </x-panel>
        @endif
    </div>
</x-app-layout>

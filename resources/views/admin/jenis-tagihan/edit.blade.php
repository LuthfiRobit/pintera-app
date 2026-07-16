<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keuangan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">{{ $jenisTagihan->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-xl space-y-6">
        <x-panel class="p-6">
            <form method="POST" action="{{ route('admin.jenis-tagihan.update', $jenisTagihan) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <x-input-label value="Nama" />
                    <x-text-input type="text" name="nama" class="mt-1.5" :value="old('nama', $jenisTagihan->nama)" required />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Kategori" />
                    <select name="kategori" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass" required>
                        @foreach (['pendaftaran' => 'Pendaftaran', 'daftar_ulang' => 'Daftar Ulang', 'lainnya' => 'Lainnya'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('kategori', $jenisTagihan->kategori) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-data="{ bisaDicicil: @js($jenisTagihan->bisa_dicicil) }">
                    <label class="inline-flex items-center text-sm text-ink">
                        <input type="checkbox" name="bisa_dicicil" value="1" x-model="bisaDicicil" class="rounded border-ink/25 text-brass shadow-sm focus:ring-brass">
                        <span class="ms-2">Bisa dicicil</span>
                    </label>
                    <div x-show="bisaDicicil" x-cloak class="mt-3">
                        <x-input-label value="Maksimal Jumlah Cicilan" />
                        <x-text-input type="number" name="maks_cicilan" min="2" class="mt-1.5 w-32" :value="old('maks_cicilan', $jenisTagihan->maks_cicilan)" />
                    </div>
                </div>
                <x-primary-button>Simpan</x-primary-button>
            </form>
        </x-panel>

        <x-panel class="p-6">
            <p class="text-sm text-slate">Atur nominal per jalur untuk jenis tagihan ini.</p>
            <a href="{{ route('admin.jenis-tagihan.nominal', $jenisTagihan) }}" class="mt-2 inline-block text-sm font-semibold text-ink hover:underline">Kelola Nominal &rarr;</a>
        </x-panel>
    </div>
</x-app-layout>

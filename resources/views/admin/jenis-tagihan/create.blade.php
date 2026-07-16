<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keuangan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Tambah Jenis Tagihan</h2>
    </x-slot>

    <div class="mx-auto max-w-xl">
        <x-panel class="p-6">
            <form method="POST" action="{{ route('admin.jenis-tagihan.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label value="Nama" />
                    <x-text-input type="text" name="nama" class="mt-1.5" :value="old('nama')" required autofocus />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Kategori" />
                    <select name="kategori" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass" required>
                        <option value="pendaftaran">Pendaftaran</option>
                        <option value="daftar_ulang">Daftar Ulang</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                    <x-input-error :messages="$errors->get('kategori')" class="mt-1.5" />
                </div>
                <div x-data="{ bisaDicicil: false }">
                    <label class="inline-flex items-center text-sm text-ink">
                        <input type="checkbox" name="bisa_dicicil" value="1" x-model="bisaDicicil" class="rounded border-ink/25 text-brass shadow-sm focus:ring-brass">
                        <span class="ms-2">Bisa dicicil</span>
                    </label>
                    <div x-show="bisaDicicil" x-cloak class="mt-3">
                        <x-input-label value="Maksimal Jumlah Cicilan" />
                        <x-text-input type="number" name="maks_cicilan" min="2" class="mt-1.5 w-32" :value="old('maks_cicilan')" />
                        <x-input-error :messages="$errors->get('maks_cicilan')" class="mt-1.5" />
                    </div>
                </div>
                <x-primary-button>Simpan</x-primary-button>
            </form>
        </x-panel>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Data Induk</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Tambah Tahun Ajaran</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.tahun-ajaran.store') }}" class="space-y-5 p-6">
                @csrf

                <div>
                    <x-input-label value="Nama (mis. 2026/2027)" />
                    <x-text-input type="text" name="nama" value="{{ old('nama') }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Tanggal Mulai" />
                        <x-text-input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('tanggal_mulai')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Selesai" />
                        <x-text-input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('tanggal_selesai')" class="mt-1.5" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>Simpan</x-primary-button>
                    <a href="{{ route('admin.tahun-ajaran.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </form>
        </x-panel>
    </div>
</x-app-layout>

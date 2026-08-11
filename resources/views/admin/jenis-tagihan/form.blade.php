<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-4">
        <a href="{{ route('admin.jenis-tagihan.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-brand-600">
            &larr; Kembali ke Jenis Tagihan
        </a>

        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">{{ $jenisTagihan === null ? 'Tambah Jenis Tagihan' : 'Edit Jenis Tagihan' }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.jenis-tagihan.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Jenis Tagihan</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">{{ $jenisTagihan === null ? 'Tambah' : 'Edit' }}</b>
            </p>
        </div>

        <form
            method="POST"
            action="{{ $jenisTagihan === null ? route('admin.jenis-tagihan.store') : route('admin.jenis-tagihan.update', $jenisTagihan) }}"
            x-data="jenisTagihanForm({
                kategoriAwal: @js(old('kategori', $jenisTagihan?->kategori ?? 'lainnya')),
                kategoriKeringananList: @js($kategoriKeringananList),
            })"
            class="space-y-5"
        >
            @csrf
            @if ($jenisTagihan !== null)
                @method('PUT')
            @endif

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                <p class="font-display text-sm font-bold text-gray-900">1. Informasi Dasar</p>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Nama" />
                        <x-text-input type="text" name="nama" :value="old('nama', $jenisTagihan?->nama)" placeholder="mis. SPP Bulanan" class="mt-1.5" required />
                    </div>
                    <div>
                        <x-input-label value="Kategori" />
                        <select name="kategori" x-model="form.kategori" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="pendaftaran">Pendaftaran</option>
                            <option value="daftar_ulang">Daftar Ulang</option>
                            <option value="lainnya">Lainnya</option>
                            <option value="spp">SPP</option>
                            <option value="tahunan">Tahunan</option>
                            <option value="kegiatan">Kegiatan</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="bisa_dicicil" value="1" x-model="form.bisaDicicil" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" {{ old('bisa_dicicil', $jenisTagihan?->bisa_dicicil) ? 'checked' : '' }}>
                            Bisa dicicil
                        </label>
                        <div x-show="form.bisaDicicil" x-cloak class="mt-2 max-w-[160px]">
                            <x-input-label value="Maksimal Jumlah Cicilan" />
                            <x-text-input type="number" min="2" name="maks_cicilan" :value="old('maks_cicilan', $jenisTagihan?->maks_cicilan)" class="mt-1.5" />
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" {{ old('is_active', $jenisTagihan?->is_active ?? true) ? 'checked' : '' }}>
                            Status Aktif
                        </label>
                    </div>
                </div>

                <div x-show="!kategoriPpdb" x-cloak class="grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Nominal Default" />
                        <x-text-input type="number" step="0.01" min="0" name="default_amount" :value="old('default_amount', $jenisTagihan?->default_amount)" class="mt-1.5" placeholder="Dipakai jika tidak ada Tarif Berdimensi yang cocok" />
                    </div>
                    <div>
                        <x-input-label value="Mode" />
                        <select name="mode" x-model="form.mode" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="manual">Manual</option>
                            <option value="otomatis">Otomatis</option>
                        </select>
                    </div>
                    <template x-if="form.mode === 'otomatis'">
                        <div class="grid grid-cols-1 gap-3 sm:col-span-2 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Tanggal Mulai" />
                                <x-text-input type="date" name="tanggal_mulai" :value="old('tanggal_mulai', optional($jenisTagihan?->tanggal_mulai)->toDateString())" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label value="Tanggal Selesai (opsional)" />
                                <x-text-input type="date" name="tanggal_selesai" :value="old('tanggal_selesai', optional($jenisTagihan?->tanggal_selesai)->toDateString())" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label value="Tanggal Generate (hari ke-)" />
                                <x-text-input type="number" min="1" max="31" name="tanggal_generate" :value="old('tanggal_generate', $jenisTagihan?->tanggal_generate)" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label value="Hari Jatuh Tempo (setelah generate)" />
                                <x-text-input type="number" min="0" name="hari_jatuh_tempo" :value="old('hari_jatuh_tempo', $jenisTagihan?->hari_jatuh_tempo)" class="mt-1.5" />
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Task 4: Section 2 (Target Sasaran) + Section 3 (Tarif Berdimensi) go here -->

            <!-- Task 5: Section 4 (Keringanan) goes here -->

            <div class="flex items-center gap-3">
                <x-primary-button type="submit">{{ $jenisTagihan === null ? 'Tambah' : 'Simpan' }}</x-primary-button>
                <x-secondary-button type="button" @click="window.location.href = @js(route('admin.jenis-tagihan.index'))">Batal</x-secondary-button>
            </div>
        </form>
    </div>

    <script>
        function jenisTagihanForm(config) {
            return {
                form: {
                    kategori: config.kategoriAwal,
                    mode: @js(old('mode', $jenisTagihan?->mode ?? 'manual')),
                    bisaDicicil: @js((bool) old('bisa_dicicil', $jenisTagihan?->bisa_dicicil ?? false)),
                },
                kategoriKeringananOptions: config.kategoriKeringananList,
                get kategoriPpdb() {
                    return ['pendaftaran', 'daftar_ulang'].includes(this.form.kategori);
                },
            };
        }
    </script>
</x-app-layout>

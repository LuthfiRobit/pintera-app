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
                modeAwal: @js(old('mode', $jenisTagihan?->mode ?? 'manual')),
                bisaDicicilAwal: @js((bool) old('bisa_dicicil', $jenisTagihan?->bisa_dicicil ?? false)),
                kategoriKeringananList: @js($kategoriKeringananList),
                referenceOptions: {
                    lembaga: @js($lembagaList->map(fn ($l) => ['value' => $l->id, 'label' => $l->nama])),
                    tahun_ajaran: @js($tahunAjaranList->map(fn ($t) => ['value' => $t->id, 'label' => $t->nama])),
                    tingkat: @js($tingkatList->map(fn ($t) => ['value' => $t, 'label' => $t])),
                    kelas: @js($kelasList->map(fn ($k) => ['value' => $k->id, 'label' => $k->nama])),
                },
                initialSasaran: @js(old('sasaran', $jenisTagihan?->sasaranGrup->where('tipe', 'sasaran')->map(fn ($g) => ['nominal' => null, 'kriteria' => $g->kriteria->map(fn ($k) => ['field' => $k->field, 'operator' => $k->operator, 'value' => $k->value])->values()->all()])->values()->all() ?? [])),
                initialTarif: @js(old('tarif', $jenisTagihan?->sasaranGrup->where('tipe', 'tarif')->map(fn ($g) => ['nominal' => $g->nominal, 'kriteria' => $g->kriteria->map(fn ($k) => ['field' => $k->field, 'operator' => $k->operator, 'value' => $k->value])->values()->all()])->values()->all() ?? [])),
                kategoriKeringananStoreUrl: @js(route('admin.kategori-keringanan.store')),
                initialKeringanan: @js(old('keringanan', $jenisTagihan?->keringananRules->map(fn ($r) => ['kategori_keringanan_id' => $r->kategori_keringanan_id, 'tipe_potongan' => $r->tipe_potongan, 'nilai' => (float) $r->nilai, 'keterangan' => $r->keterangan])->values()->all() ?? [])),
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

            <div x-show="!kategoriPpdb" x-cloak class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                <p class="font-display text-sm font-bold text-gray-900">2. Target Sasaran</p>
                <div class="flex items-center gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" value="semua" x-model="sasaranMode"> Semua Siswa</label>
                    <label class="flex items-center gap-2"><input type="radio" value="kriteria" x-model="sasaranMode"> Berdasarkan Kriteria</label>
                </div>

                <template x-if="sasaranMode === 'kriteria'">
                    <div class="space-y-3">
                        <template x-for="(grup, gi) in form.sasaran" :key="grup.uid">
                            <div class="rounded-xl border border-gray-200 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase text-gray-500" x-text="'Sasaran #' + (gi + 1)"></p>
                                    <button type="button" class="text-xs font-semibold text-error-600" @click="form.sasaran.splice(gi, 1)">Hapus</button>
                                </div>
                                <template x-for="(kriteria, ki) in grup.kriteria" :key="kriteria.uid">
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                                        <select :name="'sasaran[' + gi + '][kriteria][' + ki + '][field]'" x-model="kriteria.field" class="rounded-lg border-gray-200 text-sm">
                                            <template x-for="fieldOpt in kriteriaFields" :key="fieldOpt"><option :value="fieldOpt" x-text="fieldOpt" :selected="fieldOpt === kriteria.field"></option></template>
                                        </select>
                                        <select :name="'sasaran[' + gi + '][kriteria][' + ki + '][operator]'" x-model="kriteria.operator" class="rounded-lg border-gray-200 text-sm">
                                            <option value="in" :selected="kriteria.operator === 'in'">Termasuk</option>
                                            <option value="not_in" :selected="kriteria.operator === 'not_in'">Tidak Termasuk</option>
                                        </select>
                                        <select :name="'sasaran[' + gi + '][kriteria][' + ki + '][value][]'" multiple x-model="kriteria.value" class="rounded-lg border-gray-200 text-sm sm:col-span-1">
                                            <template x-for="opt in optionsFor(kriteria.field)" :key="opt.value"><option :value="opt.value" x-text="opt.label"></option></template>
                                        </select>
                                        <button type="button" class="text-xs font-semibold text-error-600" @click="grup.kriteria.splice(ki, 1)">Hapus Kriteria</button>
                                    </div>
                                </template>
                                <button type="button" class="text-xs font-semibold text-brand-600" @click="grup.kriteria.push(newKriteria())">+ Tambah Kriteria</button>
                            </div>
                        </template>
                        <button type="button" class="text-sm font-semibold text-brand-600" @click="form.sasaran.push(newGrup())">+ Tambah Sasaran</button>
                    </div>
                </template>
            </div>

            <div x-show="!kategoriPpdb" x-cloak class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                <p class="font-display text-sm font-bold text-gray-900">3. Tarif Berdimensi <span class="font-normal text-gray-400">(opsional)</span></p>
                <template x-for="(grup, gi) in form.tarif" :key="grup.uid">
                    <div class="rounded-xl border border-gray-200 p-4 space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase text-gray-500" x-text="'Tarif #' + (gi + 1)"></p>
                            <input type="number" step="0.01" min="0" :name="'tarif[' + gi + '][nominal]'" x-model="grup.nominal" placeholder="Nominal" class="w-40 rounded-lg border-gray-200 text-sm">
                            <button type="button" class="text-xs font-semibold text-error-600" @click="form.tarif.splice(gi, 1)">Hapus</button>
                        </div>
                        <template x-for="(kriteria, ki) in grup.kriteria" :key="kriteria.uid">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                                <select :name="'tarif[' + gi + '][kriteria][' + ki + '][field]'" x-model="kriteria.field" class="rounded-lg border-gray-200 text-sm">
                                    <template x-for="fieldOpt in kriteriaFields" :key="fieldOpt"><option :value="fieldOpt" x-text="fieldOpt" :selected="fieldOpt === kriteria.field"></option></template>
                                </select>
                                <select :name="'tarif[' + gi + '][kriteria][' + ki + '][operator]'" x-model="kriteria.operator" class="rounded-lg border-gray-200 text-sm">
                                    <option value="in" :selected="kriteria.operator === 'in'">Termasuk</option>
                                    <option value="not_in" :selected="kriteria.operator === 'not_in'">Tidak Termasuk</option>
                                </select>
                                <select :name="'tarif[' + gi + '][kriteria][' + ki + '][value][]'" multiple x-model="kriteria.value" class="rounded-lg border-gray-200 text-sm sm:col-span-1">
                                    <template x-for="opt in optionsFor(kriteria.field)" :key="opt.value"><option :value="opt.value" x-text="opt.label"></option></template>
                                </select>
                                <button type="button" class="text-xs font-semibold text-error-600" @click="grup.kriteria.splice(ki, 1)">Hapus Kriteria</button>
                            </div>
                        </template>
                        <button type="button" class="text-xs font-semibold text-brand-600" @click="grup.kriteria.push(newKriteria())">+ Tambah Kriteria</button>
                    </div>
                </template>
                <button type="button" class="text-sm font-semibold text-brand-600" @click="form.tarif.push(newGrup())">+ Tambah Tarif</button>
            </div>

            <div x-show="!kategoriPpdb" x-cloak class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                <p class="font-display text-sm font-bold text-gray-900">4. Keringanan <span class="font-normal text-gray-400">(opsional)</span></p>
                <template x-for="(rule, ri) in form.keringanan" :key="rule.uid">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-5">
                        <select :name="'keringanan[' + ri + '][kategori_keringanan_id]'" x-model.number="rule.kategori_keringanan_id" class="rounded-lg border-gray-200 text-sm">
                            <template x-for="opt in kategoriKeringananOptions" :key="opt.id"><option :value="opt.id" x-text="opt.nama" :selected="opt.id === rule.kategori_keringanan_id"></option></template>
                        </select>
                        <select :name="'keringanan[' + ri + '][tipe_potongan]'" x-model="rule.tipe_potongan" class="rounded-lg border-gray-200 text-sm">
                            <option value="fixed" :selected="rule.tipe_potongan === 'fixed'">Nominal Tetap</option>
                            <option value="persen" :selected="rule.tipe_potongan === 'persen'">Persentase</option>
                        </select>
                        <input type="number" min="0" :max="rule.tipe_potongan === 'persen' ? 100 : null" step="0.01" :name="'keringanan[' + ri + '][nilai]'" x-model="rule.nilai" placeholder="Nilai" class="rounded-lg border-gray-200 text-sm">
                        <input type="text" :name="'keringanan[' + ri + '][keterangan]'" x-model="rule.keterangan" placeholder="Keterangan" class="rounded-lg border-gray-200 text-sm">
                        <button type="button" class="text-xs font-semibold text-error-600" @click="form.keringanan.splice(ri, 1)">Hapus</button>
                    </div>
                </template>
                <div class="flex items-center gap-3">
                    <button type="button" class="text-sm font-semibold text-brand-600" @click="form.keringanan.push(newKeringanan())">+ Tambah Keringanan</button>
                    <button type="button" class="text-sm font-semibold text-gray-500" @click="showKategoriBaru = true">+ Kategori Baru</button>
                </div>

                <div x-show="showKategoriBaru" x-cloak class="rounded-xl border border-dashed border-gray-300 p-4 space-y-2">
                    <x-input-label value="Nama Kategori Keringanan" />
                    <input type="text" x-model="kategoriBaruNama" class="w-full rounded-lg border-gray-200 text-sm" placeholder="mis. Prestasi Akademik">
                    <p class="text-sm text-error-600" x-show="kategoriBaruError" x-text="kategoriBaruError"></p>
                    <div class="flex gap-2">
                        <x-secondary-button type="button" x-bind:disabled="kategoriBaruSubmitting" @click="submitKategoriBaru()">Simpan Kategori</x-secondary-button>
                        <x-secondary-button type="button" @click="showKategoriBaru = false">Batal</x-secondary-button>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button type="submit">{{ $jenisTagihan === null ? 'Tambah' : 'Simpan' }}</x-primary-button>
                <x-secondary-button type="button" @click="window.location.href = @js(route('admin.jenis-tagihan.index'))">Batal</x-secondary-button>
            </div>
        </form>
    </div>

    <script>
        function jenisTagihanForm(config) {
            let uidCounter = 0;
            const nextUid = () => ++uidCounter;
            const hydrateGrup = (grup) => ({ uid: nextUid(), nominal: grup.nominal ?? '', kriteria: grup.kriteria.map((k) => ({ uid: nextUid(), ...k })) });

            return {
                kriteriaFields: ['lembaga', 'tahun_ajaran', 'tingkat', 'kelas', 'jenis_kelamin', 'status_siswa'],
                referenceOptions: config.referenceOptions,
                sasaranMode: config.initialSasaran.length > 0 ? 'kriteria' : 'semua',
                form: {
                    kategori: config.kategoriAwal,
                    mode: config.modeAwal,
                    bisaDicicil: config.bisaDicicilAwal,
                    sasaran: config.initialSasaran.map(hydrateGrup),
                    tarif: config.initialTarif.map(hydrateGrup),
                    keringanan: config.initialKeringanan.map((k) => ({ uid: nextUid(), ...k })),
                },
                kategoriKeringananOptions: config.kategoriKeringananList,
                showKategoriBaru: false,
                kategoriBaruNama: '',
                kategoriBaruError: '',
                kategoriBaruSubmitting: false,
                get kategoriPpdb() {
                    return ['pendaftaran', 'daftar_ulang'].includes(this.form.kategori);
                },
                hydrateGrup,
                newKeringanan() {
                    return { uid: nextUid(), kategori_keringanan_id: this.kategoriKeringananOptions[0]?.id ?? null, tipe_potongan: 'fixed', nilai: '', keterangan: '' };
                },
                async submitKategoriBaru() {
                    this.kategoriBaruSubmitting = true;
                    this.kategoriBaruError = '';
                    try {
                        const response = await fetch(config.kategoriKeringananStoreUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ nama: this.kategoriBaruNama }),
                        });
                        const json = await response.json();
                        if (!response.ok) {
                            this.kategoriBaruError = json.message ?? 'Gagal menambah kategori.';
                            return;
                        }
                        this.kategoriKeringananOptions.push(json.data);
                        this.kategoriBaruNama = '';
                        this.showKategoriBaru = false;
                    } catch (error) {
                        this.kategoriBaruError = 'Gagal menambah kategori.';
                    } finally {
                        this.kategoriBaruSubmitting = false;
                    }
                },
                newKriteria() {
                    return { uid: nextUid(), field: 'status_siswa', operator: 'in', value: [] };
                },
                newGrup() {
                    return { uid: nextUid(), nominal: '', kriteria: [this.newKriteria()] };
                },
                optionsFor(field) {
                    if (field === 'jenis_kelamin') return [{ value: 'L', label: 'Laki-laki' }, { value: 'P', label: 'Perempuan' }];
                    if (field === 'status_siswa') return [{ value: 'aktif', label: 'Aktif' }, { value: 'lulus', label: 'Lulus' }, { value: 'pindah', label: 'Pindah' }, { value: 'keluar', label: 'Keluar' }];
                    return this.referenceOptions[field] ?? [];
                },
            };
        }
    </script>
</x-app-layout>

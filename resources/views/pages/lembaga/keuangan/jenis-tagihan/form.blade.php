<x-app-layout>
    {{-- Top Navigation & Breadcrumbs --}}
    <div class="mx-auto max-w-6xl mb-6 flex flex-wrap items-center justify-between gap-3 px-4 sm:px-0">
        <div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.jenis-tagihan.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Jenis Tagihan</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-900">{{ $jenisTagihan === null ? 'Tambah' : 'Edit' }}</b>
            </p>
        </div>
        <a href="{{ route('admin.jenis-tagihan.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
            <x-icon name="arrow_back" class="h-4 w-4 text-gray-500" />
            <span>Kembali ke Daftar</span>
        </a>
    </div>

    @if ($errors->any())
        <div class="mx-auto max-w-6xl px-4 sm:px-0 mb-6">
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-sm font-medium text-error-700 shadow-sm">{{ $errors->first() }}</div>
        </div>
    @endif

    <div
        x-data="jenisTagihanForm({
            kategoriAwal: @js(old('kategori', $jenisTagihan?->kategori ?? 'lainnya')),
            modeAwal: @js(old('mode', $jenisTagihan?->mode ?? 'manual')),
            bisaDicicilAwal: @js((bool) old('bisa_dicicil', $jenisTagihan?->bisa_dicicil ?? false)),
            kategoriKeringananList: @js($kategoriKeringananList),
            referenceOptions: {
                lembaga: @js($lembagaList->map(fn ($l) => ['value' => $l->id, 'label' => $l->nama])),
                tahun_ajaran: @js($tahunAjaranList->map(fn ($t) => ['value' => $t->id, 'label' => $t->nama])),
                tingkat: @js($tingkatList->map(fn ($t) => ['value' => $t, 'label' => $t])),
                kelas: @js($kelasList->map(fn ($k) => ['value' => $k->id, 'label' => $k->nama.' ('.($k->tahunAjaran?->nama ?? '-').')'])),
            },
            initialSasaran: @js(old('sasaran', $jenisTagihan?->sasaranGrup->where('tipe', 'sasaran')->map(fn ($g) => ['nominal' => null, 'kriteria' => $g->kriteria->map(fn ($k) => ['field' => $k->field, 'operator' => $k->operator, 'value' => $k->value])->values()->all()])->values()->all() ?? [])),
            initialTarif: @js(old('tarif', $jenisTagihan?->sasaranGrup->where('tipe', 'tarif')->map(fn ($g) => ['nominal' => $g->nominal, 'kriteria' => $g->kriteria->map(fn ($k) => ['field' => $k->field, 'operator' => $k->operator, 'value' => $k->value])->values()->all()])->values()->all() ?? [])),
            kategoriKeringananStoreUrl: @js(route('admin.kategori-keringanan.store')),
            initialKeringanan: @js(old('keringanan', $jenisTagihan?->keringananRules->map(fn ($r) => ['kategori_keringanan_id' => $r->kategori_keringanan_id, 'tipe_potongan' => $r->tipe_potongan, 'nilai' => (float) $r->nilai, 'keterangan' => $r->keterangan])->values()->all() ?? [])),
        })"
    >
        <form
            method="POST"
            action="{{ $jenisTagihan === null ? route('admin.jenis-tagihan.store') : route('admin.jenis-tagihan.update', $jenisTagihan) }}"
            @submit.prevent="validateBeforeSubmit($event)"
            class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[minmax(0,340px)_1fr] items-start px-4 sm:px-0"
        >
            @csrf
            @if ($jenisTagihan !== null)
                @method('PUT')
            @endif

        {{-- Sticky Sidebar Form (Kolom Kiri) --}}
        <div class="sticky top-6 flex flex-col gap-5">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card transition hover:shadow-elevated">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <x-icon name="receipt_long" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900">Identitas Tagihan</h3>
                        <p class="text-xs text-gray-500">Konfigurasi esensial</p>
                    </div>
                </div>

                <div class="space-y-5">
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

                    <div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="bisa_dicicil" value="1" x-model="form.bisaDicicil" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 transition" {{ old('bisa_dicicil', $jenisTagihan?->bisa_dicicil) ? 'checked' : '' }}>
                            <span class="font-medium">Bisa dicicil</span>
                        </label>
                        <div x-show="form.bisaDicicil" x-cloak class="mt-2" x-transition>
                            <x-input-label value="Maksimal Jumlah Cicilan" />
                            <x-text-input type="number" min="2" name="maks_cicilan" :value="old('maks_cicilan', $jenisTagihan?->maks_cicilan)" class="mt-1.5" />
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 transition" {{ old('is_active', $jenisTagihan?->is_active ?? true) ? 'checked' : '' }}>
                            <span class="font-medium">Status Aktif</span>
                        </label>
                    </div>

                    <div class="flex flex-col gap-3 pt-4">
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700 active:scale-[0.98]">
                            <x-icon name="save" class="h-4 w-4" />
                            <span>{{ $jenisTagihan === null ? 'Buat Jenis Tagihan' : 'Simpan Perubahan' }}</span>
                        </button>
                        <a href="{{ route('admin.jenis-tagihan.index') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-[0.98]">
                            Batal
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Konten Dinamis (Kolom Kanan) --}}
        <div class="flex flex-col gap-6">
            <template x-if="!kategoriPpdb">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                    <p class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Mode Generate & Default</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 pt-1">
                        <div>
                            <x-input-label value="Nominal Default" />
                            <x-text-input type="number" step="0.01" min="0" name="default_amount" :value="old('default_amount', $jenisTagihan?->default_amount)" class="mt-1.5" placeholder="Dipakai jika kriteria tak cocok" />
                        </div>
                        <div>
                            <x-input-label value="Mode" />
                            <select name="mode" x-model="form.mode" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="manual">Manual</option>
                                <option value="otomatis">Otomatis</option>
                            </select>
                        </div>
                        <template x-if="form.mode === 'otomatis'">
                            <div class="grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-2 pt-2">
                                <div>
                                    <x-input-label value="Tanggal Mulai" />
                                    <x-text-input type="date" name="tanggal_mulai" :value="old('tanggal_mulai', optional($jenisTagihan?->tanggal_mulai)->toDateString())" class="mt-1.5" />
                                    <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Tanggal jenis tagihan ini mulai aktif digenerate otomatis.</p>
                                </div>
                                <div>
                                    <x-input-label value="Tanggal Selesai (opsional)" />
                                    <x-text-input type="date" name="tanggal_selesai" :value="old('tanggal_selesai', optional($jenisTagihan?->tanggal_selesai)->toDateString())" class="mt-1.5" />
                                    <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Kosongkan jika tidak ada batas akhir.</p>
                                </div>
                                <div>
                                    <x-input-label value="Tanggal Generate (hari ke-)" />
                                    <x-text-input type="number" min="1" max="31" name="tanggal_generate" :value="old('tanggal_generate', $jenisTagihan?->tanggal_generate)" class="mt-1.5" />
                                    <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Tanggal setiap bulan saat tagihan otomatis dibuat (mis. isi 1 untuk tanggal 1 tiap bulan).</p>
                                </div>
                                <div>
                                    <x-input-label value="Hari Jatuh Tempo (setelah generate)" />
                                    <x-text-input type="number" min="0" name="hari_jatuh_tempo" :value="old('hari_jatuh_tempo', $jenisTagihan?->hari_jatuh_tempo)" class="mt-1.5" />
                                    <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Jumlah hari setelah tanggal generate sampai batas waktu pembayaran.</p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="!kategoriPpdb">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                    <p class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Target Sasaran</p>
                    <div class="flex items-center gap-6 pt-1">
                        <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-gray-700">
                            <input type="radio" value="semua" x-model="sasaranMode" class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500">
                            Semua Siswa
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-gray-700">
                            <input type="radio" value="kriteria" x-model="sasaranMode" class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500">
                            Berdasarkan Kriteria
                        </label>
                    </div>

                    <template x-if="sasaranMode === 'kriteria'">
                        <div class="space-y-4 pt-2">
                            <template x-for="(grup, gi) in form.sasaran" :key="grup.uid">
                                <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 space-y-4 shadow-sm">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700" x-text="gi + 1"></div>
                                            <p class="text-sm font-bold text-gray-900">Grup Sasaran</p>
                                        </div>
                                        <button type="button" class="text-xs font-bold text-error-600 hover:text-error-700" @click="form.sasaran.splice(gi, 1)">Hapus Grup</button>
                                    </div>
                                    <template x-for="(kriteria, ki) in grup.kriteria" :key="kriteria.uid">
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-12 items-start">
                                            <select :name="'sasaran[' + gi + '][kriteria][' + ki + '][field]'" x-model="kriteria.field" @change="$dispatch('kriteria-field-changed', { uid: kriteria.uid })" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-4">
                                                <template x-for="fieldOpt in kriteriaFields" :key="fieldOpt"><option :value="fieldOpt" x-text="fieldLabels[fieldOpt] ?? fieldOpt" :selected="fieldOpt === kriteria.field"></option></template>
                                            </select>
                                            <select :name="'sasaran[' + gi + '][kriteria][' + ki + '][operator]'" x-model="kriteria.operator" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-3">
                                                <option value="in" :selected="kriteria.operator === 'in'">Termasuk</option>
                                                <option value="not_in" :selected="kriteria.operator === 'not_in'">Tidak Termasuk</option>
                                            </select>
                                            <div class="sm:col-span-4">
                                                <select :name="'sasaran[' + gi + '][kriteria][' + ki + '][value][]'" multiple x-init="$nextTick(() => { initTomSelect($el, kriteria) })" @kriteria-field-changed.window="if ($event.detail.uid === kriteria.uid) { $nextTick(() => { initTomSelect($el, kriteria) }) }" class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                    <template x-for="opt in optionsFor(kriteria.field)" :key="opt.value"><option :value="opt.value" x-text="opt.label" :selected="(kriteria.value ?? []).map(String).includes(String(opt.value))"></option></template>
                                                </select>
                                                <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Pilih satu/banyak. Klik <span class="font-semibold text-gray-600">×</span> untuk batal.</p>
                                            </div>
                                            <div class="text-right sm:text-left sm:col-span-1 pt-2">
                                                <button type="button" class="text-xs font-bold text-error-600 hover:text-error-700" @click="grup.kriteria.splice(ki, 1)">Hapus</button>
                                            </div>
                                        </div>
                                    </template>
                                    <p class="text-[10px] text-gray-400 leading-tight">Semua kriteria di atas harus terpenuhi bersamaan (DAN).</p>
                                    <button type="button" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700" @click="grup.kriteria.push(newKriteria())">
                                        <x-icon name="add" class="h-3.5 w-3.5" /> Tambah Kriteria
                                    </button>
                                </div>
                            </template>
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-brand-600 hover:bg-gray-50" @click="form.sasaran.push(newGrup())">
                                <x-icon name="add_circle" class="h-4 w-4" /> Tambah Grup Sasaran Baru
                            </button>
                            <p class="text-[10px] text-gray-400 leading-tight">Setiap Grup adalah alternatif terpisah — siswa cukup cocok salah satu (ATAU).</p>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="!kategoriPpdb">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                    <div class="border-b border-gray-100 pb-3 flex justify-between items-center">
                        <p class="font-display text-sm font-bold text-gray-900">Tarif Berdimensi</p>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-gray-500">Opsional</span>
                    </div>
                    <p class="text-[10px] text-gray-400 leading-tight">Diproses berurutan dari atas — Grup pertama yang cocok dengan siswa akan dipakai nominalnya.</p>

                    <div class="space-y-4 pt-1">
                        <template x-for="(grup, gi) in form.tarif" :key="grup.uid">
                            <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 space-y-4 shadow-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700" x-text="gi + 1"></div>
                                        <p class="text-sm font-bold text-gray-900">Grup Tarif</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <input type="number" step="0.01" min="0" :name="'tarif[' + gi + '][nominal]'" x-model="grup.nominal" placeholder="Nominal Rp" class="w-40 rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                        <button type="button" class="text-xs font-bold text-error-600 hover:text-error-700" @click="form.tarif.splice(gi, 1)">Hapus</button>
                                    </div>
                                </div>
                                <template x-for="(kriteria, ki) in grup.kriteria" :key="kriteria.uid">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-12 items-start">
                                        <select :name="'tarif[' + gi + '][kriteria][' + ki + '][field]'" x-model="kriteria.field" @change="$dispatch('kriteria-field-changed', { uid: kriteria.uid })" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-4">
                                            <template x-for="fieldOpt in kriteriaFields" :key="fieldOpt"><option :value="fieldOpt" x-text="fieldLabels[fieldOpt] ?? fieldOpt" :selected="fieldOpt === kriteria.field"></option></template>
                                        </select>
                                        <select :name="'tarif[' + gi + '][kriteria][' + ki + '][operator]'" x-model="kriteria.operator" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-3">
                                            <option value="in" :selected="kriteria.operator === 'in'">Termasuk</option>
                                            <option value="not_in" :selected="kriteria.operator === 'not_in'">Tidak Termasuk</option>
                                        </select>
                                        <div class="sm:col-span-4">
                                            <select :name="'tarif[' + gi + '][kriteria][' + ki + '][value][]'" multiple x-init="$nextTick(() => { initTomSelect($el, kriteria) })" @kriteria-field-changed.window="if ($event.detail.uid === kriteria.uid) { $nextTick(() => { initTomSelect($el, kriteria) }) }" class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                <template x-for="opt in optionsFor(kriteria.field)" :key="opt.value"><option :value="opt.value" x-text="opt.label" :selected="(kriteria.value ?? []).map(String).includes(String(opt.value))"></option></template>
                                            </select>
                                            <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Pilih satu/banyak. Klik <span class="font-semibold text-gray-600">×</span> untuk batal.</p>
                                        </div>
                                        <div class="text-right sm:text-left sm:col-span-1 pt-2">
                                            <button type="button" class="text-xs font-bold text-error-600 hover:text-error-700" @click="grup.kriteria.splice(ki, 1)">Hapus</button>
                                        </div>
                                    </div>
                                </template>
                                <p class="text-[10px] text-gray-400 leading-tight">Semua kriteria di atas harus terpenuhi bersamaan (DAN).</p>
                                <button type="button" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700" @click="grup.kriteria.push(newKriteria())">
                                    <x-icon name="add" class="h-3.5 w-3.5" /> Tambah Kriteria
                                </button>
                            </div>
                        </template>
                        <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-brand-600 hover:bg-gray-50" @click="form.tarif.push(newGrup())">
                            <x-icon name="add_circle" class="h-4 w-4" /> Tambah Grup Tarif Baru
                        </button>
                        <p class="text-[10px] text-gray-400 leading-tight">Setiap Grup adalah alternatif terpisah — siswa cukup cocok salah satu (ATAU).</p>
                    </div>
                </div>
            </template>

            <template x-if="!kategoriPpdb">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                    <div class="border-b border-gray-100 pb-3 flex justify-between items-center">
                        <p class="font-display text-sm font-bold text-gray-900">Keringanan Tagihan</p>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-gray-500">Opsional</span>
                    </div>

                    <div class="space-y-3 pt-1">
                        <template x-for="(rule, ri) in form.keringanan" :key="rule.uid">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-5 items-center">
                                <select :name="'keringanan[' + ri + '][kategori_keringanan_id]'" x-model.number="rule.kategori_keringanan_id" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">-- Pilih Kategori --</option>
                                    <template x-for="opt in kategoriKeringananOptions" :key="opt.id"><option :value="opt.id" x-text="opt.nama" :selected="opt.id === rule.kategori_keringanan_id"></option></template>
                                </select>
                                <select :name="'keringanan[' + ri + '][tipe_potongan]'" x-model="rule.tipe_potongan" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="fixed" :selected="rule.tipe_potongan === 'fixed'">Nominal Tetap</option>
                                    <option value="persen" :selected="rule.tipe_potongan === 'persen'">Persentase</option>
                                </select>
                                <input type="number" min="0" :max="rule.tipe_potongan === 'persen' ? 100 : null" step="0.01" :name="'keringanan[' + ri + '][nilai]'" x-model="rule.nilai" :placeholder="rule.tipe_potongan === 'persen' ? 'Contoh: 20 (%)' : 'Contoh: 50000 (Rupiah)'" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <input type="text" :name="'keringanan[' + ri + '][keterangan]'" x-model="rule.keterangan" placeholder="Keterangan" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <div class="text-right sm:text-center">
                                    <button type="button" class="text-xs font-bold text-error-600 hover:text-error-700" @click="form.keringanan.splice(ri, 1)">Hapus</button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="button" class="inline-flex items-center gap-1 text-sm font-bold text-brand-600 hover:text-brand-700" @click="form.keringanan.push(newKeringanan())">
                            <x-icon name="add" class="h-4 w-4" /> Tambah Keringanan
                        </button>
                        <span class="text-gray-300">|</span>
                        <button type="button" class="inline-flex items-center gap-1 text-sm font-bold text-gray-500 hover:text-gray-700" @click="showKategoriBaru = true">
                            <x-icon name="category" class="h-4 w-4" /> Buat Kategori Baru
                        </button>
                    </div>
                </div>
            </template>
        </div>
        </form>

        {{-- Modal Kategori Keringanan --}}
        @include('pages.lembaga.keuangan.jenis-tagihan._modal-kategori-baru')
    </div>
</x-app-layout>

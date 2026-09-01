<x-app-layout :sidebar-collapsed="true">
    {{-- Top Navigation & Breadcrumbs --}}
    <div class="mx-auto max-w-6xl mb-6 flex flex-wrap items-center justify-between gap-3 px-4 sm:px-0">
        <div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.jenis-tagihan.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Jenis Tagihan</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-900">{{ $jenisTagihan === null ? 'Tambah' : 'Edit' }}</b>
            </p>
        </div>
        <a href="{{ route('admin.jenis-tagihan.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50 active:scale-95">
            <x-icon name="arrow_back" class="h-4 w-4 text-gray-500" />
            <span>Kembali ke Daftar</span>
        </a>
    </div>

    @if ($errors->any())
        <div class="mx-auto max-w-6xl px-4 sm:px-0 mb-6">
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-sm font-medium text-error-700 shadow-xs">{{ $errors->first() }}</div>
        </div>
    @endif

    <div
        x-data="jenisTagihanForm({
            kategoriAwal: @js(old('kategori', $jenisTagihan?->kategori ?? 'spp')),
            modeAwal: @js(old('mode', $jenisTagihan?->mode ?? 'manual')),
            tipeAwal: @js(old('tipe', $jenisTagihan?->tipe?->value ?? ($jenisTagihan?->mode === 'manual' ? 'sekali' : 'bulanan'))),
            defaultAmountAwal: @js(old('default_amount', $jenisTagihan?->default_amount)),
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
            kategoriKeringananStoreUrl: '{{ route('admin.kategori-keringanan.store') }}',
            previewSasaranUrl: '{{ route('admin.jenis-tagihan.preview-sasaran') }}',
            previewTarifKeringananUrl: '{{ route('admin.jenis-tagihan.preview-tarif-keringanan') }}',
            previewSiswaKeringananUrl: '{{ route('admin.jenis-tagihan.preview-siswa-keringanan') }}',
            siswaKeringananStoreUrlTemplate: '{{ route('admin.siswa.keringanan.store', ['siswa' => '__ID__']) }}',
            siswaKeringananDestroyUrlTemplate: '{{ route('admin.siswa-keringanan.destroy', ['siswaKeringanan' => '__ID__']) }}',
            reorderTarifUrl: '{{ $jenisTagihan ? route('admin.jenis-tagihan.tarif-grup.reorder', $jenisTagihan) : '' }}',
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
                            <p class="text-xs text-gray-500">Konfigurasi dasar & status</p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <x-input-label value="Nama Jenis Tagihan" />
                            <x-text-input type="text" name="nama" :value="old('nama', $jenisTagihan?->nama)" placeholder="mis. SPP Bulanan" class="mt-1.5" required />
                        </div>

                        <div>
                            <x-input-label value="Kategori Tagihan" />
                            <select name="kategori" x-model="form.kategori" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                                <optgroup label="Siswa Aktif (Tagihan Reguler & Operasional)">
                                    <option value="spp">SPP (Biaya Pendidikan Rutin)</option>
                                    <option value="tahunan">Tahunan (Uang Pangkal / Sarpras)</option>
                                    <option value="kegiatan">Kegiatan (Field Trip / Ekskul / Ujian)</option>
                                    <option value="lainnya">Lainnya</option>
                                    <option value="custom">Custom</option>
                                </optgroup>
                                <optgroup label="Penerimaan Siswa Baru (PPDB / SPMB)">
                                    <option value="pendaftaran">Pendaftaran PPDB</option>
                                    <option value="daftar_ulang">Daftar Ulang PPDB</option>
                                </optgroup>
                            </select>
                            <p class="mt-1 text-[11px] text-gray-400">Pilih kategori yang sesuai untuk klasifikasi laporan.</p>
                        </div>

                        <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-3.5 space-y-3">
                            <div>
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="checkbox" name="bisa_dicicil" value="1" x-model="form.bisaDicicil" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 transition" {{ old('bisa_dicicil', $jenisTagihan?->bisa_dicicil) ? 'checked' : '' }}>
                                    <span class="font-medium text-gray-800">Bisa Dicicil</span>
                                </label>
                                <div x-show="form.bisaDicicil" x-cloak class="mt-2.5 pt-2.5 border-t border-gray-200/60" x-transition>
                                    <x-input-label value="Maksimal Jumlah Cicilan" />
                                    <x-text-input type="number" min="2" name="maks_cicilan" :value="old('maks_cicilan', $jenisTagihan?->maks_cicilan)" placeholder="mis. 3" class="mt-1.5" />
                                    <p class="mt-1 text-[10px] text-gray-400">Batas frekuensi pembayaran per tagihan.</p>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-gray-200/60">
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 transition" {{ old('is_active', $jenisTagihan?->is_active ?? true) ? 'checked' : '' }}>
                                    <span class="font-medium text-gray-800">Status Aktif</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 pt-2">
                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 text-sm font-bold text-white shadow-xs transition hover:bg-brand-700 active:scale-[0.98]">
                                <x-icon name="check" class="h-4 w-4" />
                                <span>{{ $jenisTagihan === null ? 'Buat Jenis Tagihan' : 'Simpan Perubahan' }}</span>
                            </button>
                            <a href="{{ route('admin.jenis-tagihan.index') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 shadow-xs transition hover:bg-gray-50 active:scale-[0.98]">
                                Batal
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Konten Dinamis (Kolom Kanan) --}}
            <div class="flex flex-col gap-6">
                {{-- Informative Card saat kategori PPDB dipilih --}}
                <template x-if="kategoriPpdb">
                    <div class="rounded-2xl border border-brand-200 bg-brand-50/60 p-6 shadow-card space-y-3">
                        <div class="flex items-start gap-3.5">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-white">
                                <x-icon name="info" class="h-5 w-5" />
                            </div>
                            <div class="space-y-1.5">
                                <h4 class="text-sm font-bold text-gray-900">Alur Khusus Kategori PPDB</h4>
                                <p class="text-xs text-gray-600 leading-relaxed">
                                    Jenis tagihan kategori PPDB (Pendaftaran & Daftar Ulang) terintegrasi langsung dengan modul penerimaan siswa baru. Tagihan akan otomatis terbit saat calon siswa mendaftar sesuai jalur seleksi.
                                </p>
                                <p class="text-xs text-brand-700 font-medium pt-1">
                                    Pengaturan nominal per jalur dan gelombang dapat dikonfigurasi melalui menu SPMB / PPDB setelah jenis tagihan ini disimpan.
                                </p>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- CARD 1: Aturan Penjadwalan & Terbit Tagihan (Non-PPDB) --}}
                <template x-if="!kategoriPpdb">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-5">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3.5">
                            <div>
                                <h4 class="font-bold text-gray-900 text-sm">Aturan Penjadwalan & Terbit Tagihan</h4>
                                <p class="text-xs text-gray-500">Tentukan cara penerbitan dan frekuensi tagihan</p>
                            </div>
                        </div>

                        {{-- 1. Mode Selection via Custom Radio Cards --}}
                        <div>
                            <x-input-label value="Mode Penerbitan Tagihan" class="mb-2" />
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {{-- Mode Otomatis Card --}}
                                <label
                                    class="relative flex cursor-pointer flex-col rounded-xl border p-4 shadow-xs transition focus-within:ring-2 focus-within:ring-brand-500"
                                    :class="form.mode === 'otomatis' ? 'border-brand-500 bg-brand-50/40 ring-1 ring-brand-500' : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50/50'"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-lg" :class="form.mode === 'otomatis' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600'">
                                                <x-icon name="schedule" class="h-4 w-4" />
                                            </div>
                                            <span class="text-sm font-bold" :class="form.mode === 'otomatis' ? 'text-brand-900' : 'text-gray-900'">Otomatis (Jadwal)</span>
                                        </div>
                                        <input
                                            type="radio"
                                            name="mode"
                                            value="otomatis"
                                            x-model="form.mode"
                                            @change="onModeChange()"
                                            class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500"
                                        />
                                    </div>
                                    <p class="mt-2 text-xs leading-relaxed" :class="form.mode === 'otomatis' ? 'text-brand-700' : 'text-gray-500'">
                                        Tagihan diterbitkan otomatis oleh sistem pada tanggal/hari yang dijadwalkan secara rutin.
                                    </p>
                                </label>

                                {{-- Mode Manual Card --}}
                                <label
                                    class="relative flex cursor-pointer flex-col rounded-xl border p-4 shadow-xs transition focus-within:ring-2 focus-within:ring-brand-500"
                                    :class="form.mode === 'manual' ? 'border-brand-500 bg-brand-50/40 ring-1 ring-brand-500' : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50/50'"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-lg" :class="form.mode === 'manual' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600'">
                                                <x-icon name="edit_note" class="h-4 w-4" />
                                            </div>
                                            <span class="text-sm font-bold" :class="form.mode === 'manual' ? 'text-brand-900' : 'text-gray-900'">Manual (Insidental)</span>
                                        </div>
                                        <input
                                            type="radio"
                                            name="mode"
                                            value="manual"
                                            x-model="form.mode"
                                            @change="onModeChange()"
                                            class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500"
                                        />
                                    </div>
                                    <p class="mt-2 text-xs leading-relaxed" :class="form.mode === 'manual' ? 'text-brand-700' : 'text-gray-500'">
                                        Tagihan diterbitkan secara mandiri oleh staf keuangan saat dibutuhkan atau per kegiatan.
                                    </p>
                                </label>
                            </div>
                        </div>

                        {{-- 2. Tipe Penjadwalan (Segmented Buttons) --}}
                        <div class="pt-1">
                            <x-input-label value="Tipe / Frekuensi Periode Tagihan" class="mb-2" />
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                                {{-- Sekali (Manual only) --}}
                                <template x-if="form.mode === 'manual'">
                                    <label
                                        class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-center text-xs font-semibold transition"
                                        :class="form.tipe === 'sekali' ? 'border-brand-500 bg-brand-600 text-white shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                                    >
                                        <input type="radio" name="tipe" value="sekali" x-model="form.tipe" @change="onTipeChange()" class="sr-only" />
                                        <span>Sekali Tagih</span>
                                    </label>
                                </template>

                                {{-- Harian --}}
                                <label
                                    class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-center text-xs font-semibold transition"
                                    :class="form.tipe === 'harian' ? 'border-brand-500 bg-brand-600 text-white shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                                >
                                    <input type="radio" name="tipe" value="harian" x-model="form.tipe" @change="onTipeChange()" class="sr-only" />
                                    <span>Harian</span>
                                </label>

                                {{-- Mingguan --}}
                                <label
                                    class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-center text-xs font-semibold transition"
                                    :class="form.tipe === 'mingguan' ? 'border-brand-500 bg-brand-600 text-white shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                                >
                                    <input type="radio" name="tipe" value="mingguan" x-model="form.tipe" @change="onTipeChange()" class="sr-only" />
                                    <span>Mingguan</span>
                                </label>

                                {{-- Bulanan --}}
                                <label
                                    class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-center text-xs font-semibold transition"
                                    :class="form.tipe === 'bulanan' ? 'border-brand-500 bg-brand-600 text-white shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                                >
                                    <input type="radio" name="tipe" value="bulanan" x-model="form.tipe" @change="onTipeChange()" class="sr-only" />
                                    <span>Bulanan</span>
                                </label>

                                {{-- Tahunan --}}
                                <label
                                    class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-center text-xs font-semibold transition"
                                    :class="form.tipe === 'tahunan' ? 'border-brand-500 bg-brand-600 text-white shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                                >
                                    <input type="radio" name="tipe" value="tahunan" x-model="form.tipe" @change="onTipeChange()" class="sr-only" />
                                    <span>Tahunan</span>
                                </label>
                            </div>
                        </div>

                        {{-- 3. Rentang Waktu Aktif Tagihan (Mode Otomatis) --}}
                        <template x-if="form.mode === 'otomatis'">
                            <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4 space-y-3">
                                <p class="text-xs font-bold text-gray-700">Periode Keaktifan Tagihan Otomatis</p>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input-label value="Tanggal Mulai" />
                                        <x-text-input type="date" name="tanggal_mulai" :value="old('tanggal_mulai', optional($jenisTagihan?->tanggal_mulai)->toDateString())" class="mt-1.5" />
                                        <p class="mt-1 text-[10px] text-gray-400">Tanggal jenis tagihan ini mulai aktif digenerate otomatis.</p>
                                    </div>
                                    <div>
                                        <x-input-label value="Tanggal Selesai (opsional)" />
                                        <x-text-input type="date" name="tanggal_selesai" :value="old('tanggal_selesai', optional($jenisTagihan?->tanggal_selesai)->toDateString())" class="mt-1.5" />
                                        <p class="mt-1 text-[10px] text-gray-400">Kosongkan jika tidak ada batas akhir.</p>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- 4. Parameter Jadwal & Jatuh Tempo (Dynamic per Tipe) --}}
                        <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4 space-y-4">
                            <p class="text-xs font-bold text-gray-700">Ketentuan Waktu Generate & Jatuh Tempo</p>

                            {{-- Harian --}}
                            <template x-if="form.tipe === 'harian'">
                                <div>
                                    <x-input-label value="Jarak Jatuh Tempo (hari setelah generate)" />
                                    <x-text-input type="number" min="0" max="365" name="offset_hari_jatuh_tempo" :value="old('offset_hari_jatuh_tempo', $jenisTagihan?->offset_hari_jatuh_tempo)" class="mt-1.5" placeholder="mis. 3" />
                                    <p class="mt-1 text-[10px] text-gray-400">Jumlah hari dari tanggal generate sampai tagihan jatuh tempo. Kosongkan jika tanpa jatuh tempo.</p>
                                </div>
                            </template>

                            {{-- Mingguan --}}
                            <template x-if="form.tipe === 'mingguan'">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <template x-if="form.mode === 'otomatis'">
                                        <div>
                                            <x-input-label value="Hari Generate Mingguan" />
                                            <select name="hari_generate" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                                                <option value="1" {{ old('hari_generate', $jenisTagihan?->hari_generate) == 1 ? 'selected' : '' }}>Senin</option>
                                                <option value="2" {{ old('hari_generate', $jenisTagihan?->hari_generate) == 2 ? 'selected' : '' }}>Selasa</option>
                                                <option value="3" {{ old('hari_generate', $jenisTagihan?->hari_generate) == 3 ? 'selected' : '' }}>Rabu</option>
                                                <option value="4" {{ old('hari_generate', $jenisTagihan?->hari_generate) == 4 ? 'selected' : '' }}>Kamis</option>
                                                <option value="5" {{ old('hari_generate', $jenisTagihan?->hari_generate) == 5 ? 'selected' : '' }}>Jumat</option>
                                                <option value="6" {{ old('hari_generate', $jenisTagihan?->hari_generate) == 6 ? 'selected' : '' }}>Sabtu</option>
                                                <option value="7" {{ old('hari_generate', $jenisTagihan?->hari_generate) == 7 ? 'selected' : '' }}>Minggu</option>
                                            </select>
                                            <p class="mt-1 text-[10px] text-gray-400">Hari dalam setiap minggu saat tagihan otomatis digenerate.</p>
                                        </div>
                                    </template>
                                    <div :class="form.mode === 'otomatis' ? '' : 'sm:col-span-2'">
                                        <x-input-label value="Jarak Jatuh Tempo (hari setelah generate)" />
                                        <x-text-input type="number" min="0" max="365" name="offset_hari_jatuh_tempo" :value="old('offset_hari_jatuh_tempo', $jenisTagihan?->offset_hari_jatuh_tempo)" class="mt-1.5" placeholder="mis. 5" />
                                        <p class="mt-1 text-[10px] text-gray-400">Jumlah hari dari tanggal generate sampai jatuh tempo. Kosongkan jika tanpa jatuh tempo.</p>
                                    </div>
                                </div>
                            </template>

                            {{-- Bulanan --}}
                            <template x-if="form.tipe === 'bulanan'">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <template x-if="form.mode === 'otomatis'">
                                        <div>
                                            <x-input-label value="Tanggal Generate (hari ke-)" />
                                            <x-text-input type="number" min="1" max="31" name="tanggal_generate" :value="old('tanggal_generate', $jenisTagihan?->tanggal_generate)" class="mt-1.5" placeholder="mis. 1" />
                                            <p class="mt-1 text-[10px] text-gray-400">Tanggal setiap bulan saat tagihan otomatis dibuat (mis. isi 1 untuk tanggal 1 tiap bulan).</p>
                                        </div>
                                    </template>
                                    <div :class="form.mode === 'otomatis' ? '' : 'sm:col-span-2'">
                                        <x-input-label value="Tanggal jatuh tempo (tanggal di bulan yang sama, bukan jarak hari)" />
                                        <x-text-input type="number" min="1" max="31" name="hari_jatuh_tempo" :value="old('hari_jatuh_tempo', $jenisTagihan?->hari_jatuh_tempo)" class="mt-1.5" placeholder="mis. 10" />
                                        <p class="mt-1 text-[10px] text-gray-400">Tanggal di bulan yang sama dengan Tanggal Generate saat tagihan jatuh tempo (mis. isi 25 untuk tanggal 25 di bulan itu).</p>
                                    </div>
                                </div>
                            </template>

                            {{-- Tahunan --}}
                            <template x-if="form.tipe === 'tahunan'">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <template x-if="form.mode === 'otomatis'">
                                        <div class="sm:col-span-1">
                                            <x-input-label value="Bulan Generate" />
                                            <select name="bulan_generate" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                                                @foreach (range(1, 12) as $m)
                                                    <option value="{{ $m }}" {{ old('bulan_generate', $jenisTagihan?->bulan_generate) == $m ? 'selected' : '' }}>
                                                        {{ \Carbon\Carbon::create(2026, $m, 1)->translatedFormat('F') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <p class="mt-1 text-[10px] text-gray-400">Bulan tagihan otomatis diterbitkan tiap tahun.</p>
                                        </div>
                                    </template>
                                    <template x-if="form.mode === 'otomatis'">
                                        <div class="sm:col-span-1">
                                            <x-input-label value="Tanggal Generate" />
                                            <x-text-input type="number" min="1" max="31" name="tanggal_generate" :value="old('tanggal_generate', $jenisTagihan?->tanggal_generate)" class="mt-1.5" placeholder="mis. 1" />
                                            <p class="mt-1 text-[10px] text-gray-400">Tanggal pada bulan generate.</p>
                                        </div>
                                    </template>
                                    <div :class="form.mode === 'otomatis' ? 'sm:col-span-1' : 'sm:col-span-3'">
                                        <x-input-label value="Tanggal Jatuh Tempo" />
                                        <x-text-input type="number" min="1" max="31" name="hari_jatuh_tempo" :value="old('hari_jatuh_tempo', $jenisTagihan?->hari_jatuh_tempo)" class="mt-1.5" placeholder="mis. 20" />
                                        <p class="mt-1 text-[10px] text-gray-400">Tanggal jatuh tempo pada bulan generate tersebut.</p>
                                    </div>
                                </div>
                            </template>

                            {{-- Sekali --}}
                            <template x-if="form.tipe === 'sekali'">
                                <p class="text-xs text-gray-500 italic">Tagihan insidental sekali terbit tidak memiliki siklus jatuh tempo otomatis.</p>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- CARD 2: Nominal Default & Sasaran Siswa (Non-PPDB) --}}
                <template x-if="!kategoriPpdb">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-5">
                        <div class="border-b border-gray-100 pb-3.5 flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-gray-900 text-sm">Nominal Default & Target Sasaran</h4>
                                <p class="text-xs text-gray-500">Tentukan nominal dasar dan kriteria siswa yang ditagih</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="fetchPreviewSasaran()" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100 transition">
                                    <x-icon name="refresh" class="h-3.5 w-3.5" />
                                    <span>Hitung Sasaran</span>
                                </button>
                                <span x-show="previewSasaranCount !== null" class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-bold text-brand-700 border border-brand-200">
                                    <span x-text="previewSasaranCount + ' siswa cocok'"></span>
                                </span>
                            </div>
                        </div>

                        {{-- Nominal Default dengan Format Rupiah --}}
                        <div class="max-w-md">
                            <x-input-label value="Nominal Tagihan Default" />
                            <div class="relative mt-1.5 rounded-lg shadow-xs">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-xs font-bold text-gray-400">Rp</span>
                                </div>
                                <input
                                    type="text"
                                    x-model="form.defaultAmountDisplay"
                                    @input="onDefaultAmountInput($event)"
                                    placeholder="0"
                                    class="block w-full rounded-lg border-gray-200 pl-9 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500"
                                />
                                <input type="hidden" name="default_amount" :value="form.defaultAmount" />
                            </div>
                            <p class="mt-1 text-[10px] text-gray-400">Nominal dasar yang dipakai jika siswa tidak memenuhi kriteria tarif khusus.</p>
                        </div>

                        {{-- Target Sasaran (Semua vs Kriteria) --}}
                        <div class="pt-3 border-t border-gray-100">
                            <x-input-label value="Target Sasaran Siswa" class="mb-2" />
                            <div class="flex items-center gap-6">
                                <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-gray-700">
                                    <input type="radio" value="semua" x-model="sasaranMode" @change="fetchPreviewSasaran()" class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500">
                                    <span>Semua Siswa</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-gray-700">
                                    <input type="radio" value="kriteria" x-model="sasaranMode" @change="fetchPreviewSasaran()" class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500">
                                    <span>Berdasarkan Kriteria Khusus</span>
                                </label>
                            </div>

                            <template x-if="sasaranMode === 'kriteria'">
                                <div class="space-y-4 pt-3">
                                    <template x-for="(grup, gi) in form.sasaran" :key="grup.uid">
                                        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 space-y-4 shadow-xs">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700" x-text="gi + 1"></div>
                                                    <p class="text-sm font-bold text-gray-900">Grup Sasaran</p>
                                                </div>
                                                <button type="button" class="text-xs font-bold text-error-600 hover:text-error-700" @click="form.sasaran.splice(gi, 1); fetchPreviewSasaran();">Hapus Grup</button>
                                            </div>
                                            <template x-for="(kriteria, ki) in grup.kriteria" :key="kriteria.uid">
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-12 items-start">
                                                    <select :name="'sasaran[' + gi + '][kriteria][' + ki + '][field]'" x-model="kriteria.field" @change="$dispatch('kriteria-field-changed', { uid: kriteria.uid }); fetchPreviewSasaran();" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-4">
                                                        <template x-for="fieldOpt in kriteriaFields" :key="fieldOpt"><option :value="fieldOpt" x-text="fieldLabels[fieldOpt] ?? fieldOpt" :selected="fieldOpt === kriteria.field"></option></template>
                                                    </select>
                                                    <select :name="'sasaran[' + gi + '][kriteria][' + ki + '][operator]'" x-model="kriteria.operator" @change="fetchPreviewSasaran()" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-3">
                                                        <option value="in" :selected="kriteria.operator === 'in'">Termasuk</option>
                                                        <option value="not_in" :selected="kriteria.operator === 'not_in'">Tidak Termasuk</option>
                                                    </select>
                                                    <div class="sm:col-span-4">
                                                        <select :name="'sasaran[' + gi + '][kriteria][' + ki + '][value][]'" multiple x-init="$nextTick(() => { initTomSelect($el, kriteria) })" @kriteria-field-changed.window="if ($event.detail.uid === kriteria.uid) { $nextTick(() => { initTomSelect($el, kriteria) }) }" class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                                                            <template x-for="opt in optionsFor(kriteria.field)" :key="opt.value"><option :value="opt.value" x-text="opt.label" :selected="(kriteria.value ?? []).map(String).includes(String(opt.value))"></option></template>
                                                        </select>
                                                        <p class="mt-1 text-[10px] text-gray-400">Pilih satu/banyak. Klik <span class="font-semibold text-gray-600">×</span> untuk hapus.</p>
                                                    </div>
                                                    <div class="text-right sm:text-left sm:col-span-1 pt-2">
                                                        <button type="button" class="text-xs font-bold text-error-600 hover:text-error-700" @click="grup.kriteria.splice(ki, 1); fetchPreviewSasaran();">Hapus</button>
                                                    </div>
                                                </div>
                                            </template>
                                            <p class="text-[10px] text-gray-400 leading-tight">Semua kriteria di dalam satu grup harus terpenuhi bersamaan (DAN).</p>
                                            <button type="button" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700" @click="grup.kriteria.push(newKriteria())">
                                                <x-icon name="add" class="h-3.5 w-3.5" /> Tambah Kriteria
                                            </button>
                                        </div>
                                    </template>
                                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-brand-600 hover:bg-gray-50 transition" @click="form.sasaran.push(newGrup())">
                                        <x-icon name="add" class="h-4 w-4" /> Tambah Grup Sasaran Baru
                                    </button>
                                    <p class="text-[10px] text-gray-400">Setiap Grup adalah alternatif terpisah — siswa cukup cocok salah satu (ATAU).</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- CARD 3: Tarif Berdimensi (Nominal Berdasarkan Kriteria Khusus) --}}
                <template x-if="!kategoriPpdb">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                        <div class="border-b border-gray-100 pb-3.5 flex justify-between items-center">
                            <div>
                                <h4 class="font-bold text-gray-900 text-sm">Tarif Berdimensi (Nominal Khusus)</h4>
                                <p class="text-xs text-gray-500">Nominal spesifik per kriteria kelas / tingkat</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="fetchPreviewTarifKeringanan()" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100 transition">
                                    <x-icon name="refresh" class="h-3.5 w-3.5" />
                                    <span>Hitung Siswa</span>
                                </button>
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-gray-500">Opsional</span>
                            </div>
                        </div>
                        <p class="text-[11px] text-gray-400">Diproses berurutan dari atas — Grup pertama yang cocok dengan data siswa akan dipakai nominalnya. (Gunakan panah &uarr;&darr; untuk ubah prioritas)</p>

                        <div class="space-y-4 pt-1">
                            <template x-for="(grup, gi) in form.tarif" :key="grup.uid">
                                <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 space-y-4 shadow-xs">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            <div class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700" x-text="gi + 1"></div>
                                            <p class="text-sm font-bold text-gray-900">Grup Tarif</p>
                                            <span x-show="previewTarifCounts[gi] !== undefined" class="inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700 border border-brand-200/60">
                                                <span x-text="(previewTarifCounts[gi] ?? 0) + ' siswa cocok'"></span>
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden bg-white shadow-xs">
                                                <button type="button" @click="moveTarifUp(gi)" :disabled="gi === 0" class="p-1 text-gray-500 hover:bg-gray-100 disabled:opacity-25 transition" title="Geser ke atas">
                                                    <x-icon name="arrow_upward" class="h-3.5 w-3.5" />
                                                </button>
                                                <button type="button" @click="moveTarifDown(gi)" :disabled="gi === form.tarif.length - 1" class="p-1 text-gray-500 hover:bg-gray-100 disabled:opacity-25 transition" title="Geser ke bawah">
                                                    <x-icon name="arrow_downward" class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                            <div class="relative w-44">
                                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                    <span class="text-xs font-bold text-gray-400">Rp</span>
                                                </div>
                                                <input
                                                    type="text"
                                                    x-model="grup.nominalDisplay"
                                                    @input="onTarifNominalInput(grup, $event)"
                                                    placeholder="0"
                                                    class="block w-full rounded-lg border-gray-200 pl-9 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500"
                                                />
                                                <input type="hidden" :name="'tarif[' + gi + '][nominal]'" :value="grup.nominal" />
                                            </div>
                                            <button type="button" class="text-xs font-bold text-error-600 hover:text-error-700" @click="form.tarif.splice(gi, 1); fetchPreviewTarifKeringanan();">Hapus</button>
                                        </div>
                                    </div>
                                    <template x-for="(kriteria, ki) in grup.kriteria" :key="kriteria.uid">
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-12 items-start">
                                            <select :name="'tarif[' + gi + '][kriteria][' + ki + '][field]'" x-model="kriteria.field" @change="$dispatch('kriteria-field-changed', { uid: kriteria.uid }); fetchPreviewTarifKeringanan();" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-4">
                                                <template x-for="fieldOpt in kriteriaFields" :key="fieldOpt"><option :value="fieldOpt" x-text="fieldLabels[fieldOpt] ?? fieldOpt" :selected="fieldOpt === kriteria.field"></option></template>
                                            </select>
                                            <select :name="'tarif[' + gi + '][kriteria][' + ki + '][operator]'" x-model="kriteria.operator" @change="fetchPreviewTarifKeringanan()" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-3">
                                                <option value="in" :selected="kriteria.operator === 'in'">Termasuk</option>
                                                <option value="not_in" :selected="kriteria.operator === 'not_in'">Tidak Termasuk</option>
                                            </select>
                                            <div class="sm:col-span-4">
                                                <select :name="'tarif[' + gi + '][kriteria][' + ki + '][value][]'" multiple x-init="$nextTick(() => { initTomSelect($el, kriteria) })" @kriteria-field-changed.window="if ($event.detail.uid === kriteria.uid) { $nextTick(() => { initTomSelect($el, kriteria) }) }" class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                                                    <template x-for="opt in optionsFor(kriteria.field)" :key="opt.value"><option :value="opt.value" x-text="opt.label" :selected="(kriteria.value ?? []).map(String).includes(String(opt.value))"></option></template>
                                                </select>
                                                <p class="mt-1 text-[10px] text-gray-400">Pilih satu/banyak. Klik <span class="font-semibold text-gray-600">×</span> untuk hapus.</p>
                                            </div>
                                            <div class="text-right sm:text-left sm:col-span-1 pt-2">
                                                <button type="button" class="text-xs font-bold text-error-600 hover:text-error-700" @click="grup.kriteria.splice(ki, 1); fetchPreviewTarifKeringanan();">Hapus</button>
                                            </div>
                                        </div>
                                    </template>
                                    <p class="text-[10px] text-gray-400 leading-tight">Semua kriteria di dalam satu grup tarif harus terpenuhi bersamaan (DAN).</p>
                                    <button type="button" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700" @click="grup.kriteria.push(newKriteria())">
                                        <x-icon name="add" class="h-3.5 w-3.5" /> Tambah Kriteria
                                    </button>
                                </div>
                            </template>
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-brand-600 hover:bg-gray-50 transition" @click="form.tarif.push(newGrup())">
                                <x-icon name="add" class="h-4 w-4" /> Tambah Grup Tarif Baru
                            </button>
                            <p class="text-[10px] text-gray-400">Setiap Grup adalah alternatif terpisah — siswa cukup cocok salah satu (ATAU).</p>
                        </div>
                    </div>
                </template>

                {{-- CARD 4: Keringanan Tagihan (Opsional) --}}
                <template x-if="!kategoriPpdb">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                        <div class="border-b border-gray-100 pb-3.5 flex justify-between items-center">
                            <div>
                                <h4 class="font-bold text-gray-900 text-sm">Keringanan & Potongan Biaya</h4>
                                <p class="text-xs text-gray-500">Diskon khusus untuk kategori siswa tertentu</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="fetchPreviewTarifKeringanan()" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100 transition">
                                    <x-icon name="refresh" class="h-3.5 w-3.5" />
                                    <span>Hitung Siswa</span>
                                </button>
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-gray-500">Opsional</span>
                            </div>
                        </div>

                        <div class="space-y-3 pt-1">
                            <template x-for="(rule, ri) in form.keringanan" :key="rule.uid">
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-12 items-center rounded-xl border border-gray-100 bg-gray-50/50 p-3">
                                    <div class="sm:col-span-4 flex items-center gap-2">
                                        <select :name="'keringanan[' + ri + '][kategori_keringanan_id]'" x-model.number="rule.kategori_keringanan_id" @change="fetchPreviewTarifKeringanan()" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                            <option value="">-- Pilih Kategori --</option>
                                            <template x-for="opt in kategoriKeringananOptions" :key="opt.id"><option :value="opt.id" x-text="opt.nama" :selected="opt.id === rule.kategori_keringanan_id"></option></template>
                                        </select>
                                        <span x-show="previewKeringananCounts[rule.kategori_keringanan_id] !== undefined" class="shrink-0 inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700 border border-brand-200/60" x-text="(previewKeringananCounts[rule.kategori_keringanan_id] ?? 0) + ' siswa'"></span>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <select :name="'keringanan[' + ri + '][tipe_potongan]'" x-model="rule.tipe_potongan" @change="onKeringananTipeChange(rule)" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                            <option value="fixed" :selected="rule.tipe_potongan === 'fixed'">Nominal Tetap (Rp)</option>
                                            <option value="persen" :selected="rule.tipe_potongan === 'persen'">Persentase (%)</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-3 relative">
                                        <div x-show="rule.tipe_potongan === 'fixed'" class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                            <span class="text-xs font-bold text-gray-400">Rp</span>
                                        </div>
                                        <input
                                            type="text"
                                            x-model="rule.nilaiDisplay"
                                            @input="onKeringananNilaiInput(rule, $event)"
                                            :placeholder="rule.tipe_potongan === 'persen' ? '0-100' : '0'"
                                            :class="rule.tipe_potongan === 'fixed' ? 'pl-9' : 'pl-3.5'"
                                            class="block w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500"
                                        />
                                        <input type="hidden" :name="'keringanan[' + ri + '][nilai]'" :value="rule.nilai" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <input type="text" :name="'keringanan[' + ri + '][keterangan]'" x-model="rule.keterangan" placeholder="Keterangan" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <div class="sm:col-span-1 text-right">
                                        <button type="button" class="text-xs font-bold text-error-600 hover:text-error-700" @click="form.keringanan.splice(ri, 1); fetchPreviewTarifKeringanan();">Hapus</button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center gap-3 pt-2">
                            <button type="button" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700" @click="form.keringanan.push(newKeringanan())">
                                <x-icon name="add" class="h-4 w-4" /> Tambah Keringanan
                            </button>
                            <span class="text-gray-300">|</span>
                            <button type="button" class="inline-flex items-center gap-1 text-xs font-bold text-gray-500 hover:text-gray-700" @click="showKategoriBaru = true">
                                <x-icon name="category" class="h-4 w-4" /> Buat Kategori Baru
                            </button>
                            <span class="text-gray-300">|</span>
                            <button type="button" class="inline-flex items-center gap-1 text-xs font-bold text-gray-500 hover:text-gray-700" @click="toggleSiswaKeringananPanel()">
                                <x-icon name="group" class="h-4 w-4" />
                                <span x-text="showSiswaKeringananPanel ? 'Tutup Assignment Siswa' : 'Kelola Assignment Siswa'"></span>
                            </button>
                        </div>

                        {{-- Widget assignment siswa-ke-kategori-keringanan, tanpa perlu ke halaman edit Siswa --}}
                        <div x-show="showSiswaKeringananPanel" x-cloak class="mt-2 rounded-xl border border-gray-200 bg-gray-50/60 p-4 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs text-gray-500">
                                    Daftar siswa yang cocok dengan Target Sasaran di atas. Centang untuk assign, hilangkan centang untuk mencabut &mdash; berlaku langsung, tanpa perlu ke halaman edit Siswa.
                                </p>
                                <button type="button" @click="fetchSiswaKeringananList()" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100 transition shrink-0">
                                    <x-icon name="refresh" class="h-3.5 w-3.5" />
                                    <span>Muat Ulang</span>
                                </button>
                            </div>

                            <input type="text" x-model="siswaKeringananSearch" placeholder="Cari nama siswa..." class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">

                            <div x-show="siswaKeringananLoading" class="py-6 text-center text-xs text-gray-400">Memuat daftar siswa...</div>

                            <div x-show="!siswaKeringananLoading && siswaKeringananList.length === 0" class="py-6 text-center text-xs text-gray-400">
                                Belum ada siswa yang cocok dengan Target Sasaran di atas (atau tidak ada siswa di lembaga ini).
                            </div>

                            <div x-show="!siswaKeringananLoading && siswaKeringananList.length > 0" class="overflow-x-auto">
                                <table class="min-w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-gray-200 text-gray-500">
                                            <th class="py-2 pr-3 font-semibold">Nama Siswa</th>
                                            <template x-for="opt in kategoriKeringananOptions" :key="opt.id">
                                                <th class="py-2 px-2 font-semibold text-center" x-text="opt.nama"></th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="siswa in siswaKeringananListFiltered" :key="siswa.id">
                                            <tr class="border-b border-gray-100">
                                                <td class="py-2 pr-3 font-medium text-gray-800" x-text="siswa.nama"></td>
                                                <template x-for="opt in kategoriKeringananOptions" :key="opt.id">
                                                    <td class="py-2 px-2 text-center">
                                                        <input
                                                            type="checkbox"
                                                            class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                                            :checked="siswa.assignments[opt.id] !== undefined"
                                                            :disabled="siswaKeringananTogglingKey === (siswa.id + ':' + opt.id)"
                                                            @change="toggleSiswaKeringanan(siswa, opt.id, $event.target.checked)"
                                                        />
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </form>

        {{-- Modal Kategori Keringanan --}}
        @include('portals.lembaga.keuangan.jenis-tagihan._modal-kategori-baru')
    </div>
</x-app-layout>

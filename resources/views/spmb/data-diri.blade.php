<x-spmb-public-layout :lembaga="$lembaga" title="Data Diri">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Data Diri Calon Murid</h2>
        <p class="mt-1 text-sm text-slate">Isi sesuai dokumen resmi (akta kelahiran, KK). Gunakan huruf kapital di setiap awal kata.</p>

        <form method="POST" action="{{ route('spmb.data-diri.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" class="mt-5 space-y-6">
            @csrf

            <section class="space-y-4">
                <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate">Data Pribadi</h3>
                <div>
                    <x-input-label value="NIK" />
                    <x-text-input type="text" name="nik" value="{{ old('nik') }}" inputmode="numeric" maxlength="16" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('nik')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Nama Lengkap (sesuai akta)" />
                    <x-text-input type="text" name="nama_lengkap" value="{{ old('nama_lengkap') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('nama_lengkap')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="NISN (opsional)" />
                    <x-text-input type="text" name="nisn" value="{{ old('nisn') }}" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Jenis Kelamin" />
                    <select name="jenis_kelamin" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass" required>
                        <option value="">Pilih</option>
                        <option value="L" @selected(old('jenis_kelamin') === 'L')>Laki-laki</option>
                        <option value="P" @selected(old('jenis_kelamin') === 'P')>Perempuan</option>
                    </select>
                    <x-input-error :messages="$errors->get('jenis_kelamin')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Tempat Lahir" />
                    <x-text-input type="text" name="tempat_lahir" value="{{ old('tempat_lahir') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('tempat_lahir')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Tanggal Lahir" />
                    <x-text-input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('tanggal_lahir')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Agama" />
                    <x-text-input type="text" name="agama" value="{{ old('agama') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('agama')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="No. Telepon/WA Orang Tua" />
                    <x-text-input type="text" name="no_telepon" value="{{ old('no_telepon') }}" class="mt-1.5" />
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate">Alamat</h3>
                <div>
                    <x-input-label value="Alamat Jalan" />
                    <x-text-input type="text" name="alamat_jalan" value="{{ old('alamat_jalan') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('alamat_jalan')" class="mt-1.5" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label value="RT" />
                        <x-text-input type="text" name="rt" value="{{ old('rt') }}" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="RW" />
                        <x-text-input type="text" name="rw" value="{{ old('rw') }}" class="mt-1.5" />
                    </div>
                </div>
                <div>
                    <x-input-label value="Desa/Kelurahan" />
                    <x-text-input type="text" name="desa_kelurahan" value="{{ old('desa_kelurahan') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('desa_kelurahan')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Kecamatan" />
                    <x-text-input type="text" name="kecamatan" value="{{ old('kecamatan') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('kecamatan')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Kabupaten/Kota" />
                    <x-text-input type="text" name="kabupaten_kota" value="{{ old('kabupaten_kota') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('kabupaten_kota')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Provinsi" />
                    <x-text-input type="text" name="provinsi" value="{{ old('provinsi') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('provinsi')" class="mt-1.5" />
                </div>
            </section>

            <section class="space-y-4" x-data="{ jumlah: 2 }">
                <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate">Data Orang Tua/Wali</h3>
                <template x-for="i in jumlah" :key="i">
                    <div class="rounded-xl border border-ink/10 p-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-ink">Jenis</label>
                                <select :name="'keluarga[' + (i - 1) + '][jenis]'" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                    <option value="ayah" x-bind:selected="i === 1">Ayah</option>
                                    <option value="ibu" x-bind:selected="i === 2">Ibu</option>
                                    <option value="wali">Wali</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-ink">Nama</label>
                                <input :name="'keluarga[' + (i - 1) + '][nama]'" type="text" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-ink">Pekerjaan</label>
                            <input :name="'keluarga[' + (i - 1) + '][pekerjaan]'" type="text" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        </div>
                    </div>
                </template>
                <button type="button" @click="jumlah++" class="text-sm font-medium text-ink hover:text-brass">+ Tambah Wali</button>
                <x-input-error :messages="$errors->get('keluarga')" class="mt-1.5" />
            </section>

            <x-primary-button>Lanjut ke Formulir Tambahan</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>

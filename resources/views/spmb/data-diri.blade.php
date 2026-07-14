<x-spmb-public-layout :lembaga="$lembaga" title="Data Diri" :langkah="2">
    <div
        x-data="dataDiriForm({
            cekNikUrl: '{{ route('spmb.data-diri.cek-nik', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}',
            old: {
                nama_lengkap: @js(old('nama_lengkap', '')),
                nisn: @js(old('nisn', '')),
                jenis_kelamin: @js(old('jenis_kelamin', '')),
                tempat_lahir: @js(old('tempat_lahir', '')),
                tanggal_lahir: @js(old('tanggal_lahir', '')),
                agama: @js(old('agama', '')),
                golongan_darah: @js(old('golongan_darah', '')),
                no_telepon: @js(old('no_telepon', '')),
                alamat_jalan: @js(old('alamat_jalan', '')),
                rt: @js(old('rt', '')),
                rw: @js(old('rw', '')),
                dusun: @js(old('dusun', '')),
                desa_kelurahan: @js(old('desa_kelurahan', '')),
                kecamatan: @js(old('kecamatan', '')),
                kabupaten_kota: @js(old('kabupaten_kota', '')),
                provinsi: @js(old('provinsi', '')),
                kode_pos: @js(old('kode_pos', '')),
                keluarga: @js(old('keluarga', [])),
            },
        })"
    >
        <x-panel class="p-6">
            <h2 class="font-display text-lg font-bold text-ink">Data Diri Calon Murid</h2>
            <p class="mt-1 text-sm text-slate">Isi sesuai dokumen resmi (akta kelahiran, KK). Gunakan huruf kapital di setiap awal kata.</p>

            <form method="POST" action="{{ route('spmb.data-diri.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" class="mt-5 space-y-6">
                @csrf

                <div x-show="pesanBlokir" x-cloak class="rounded-xl border border-signal-red/30 bg-signal-red/5 p-4 text-sm text-signal-red" x-text="pesanBlokir"></div>
                <x-input-error :messages="$errors->get('nik')" />

                <section class="space-y-4">
                    <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate">Data Pribadi</h3>
                    <div>
                        <x-input-label value="NIK" />
                        <x-text-input type="text" name="nik" value="{{ old('nik') }}" inputmode="numeric" maxlength="16" class="mt-1.5" required @blur="cekNik($event.target.value)" />
                        <p x-show="checking" x-cloak class="mt-1 text-xs text-slate">Memeriksa NIK...</p>
                    </div>
                    <div>
                        <x-input-label value="Nama Lengkap (sesuai akta)" />
                        <x-text-input type="text" name="nama_lengkap" x-model="form.nama_lengkap" class="mt-1.5" required />
                        <x-input-error :messages="$errors->get('nama_lengkap')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="NISN (opsional)" />
                        <x-text-input type="text" name="nisn" x-model="form.nisn" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Jenis Kelamin" />
                        <select name="jenis_kelamin" x-model="form.jenis_kelamin" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass" required>
                            <option value="">Pilih</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                        <x-input-error :messages="$errors->get('jenis_kelamin')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Tempat Lahir" />
                        <x-text-input type="text" name="tempat_lahir" x-model="form.tempat_lahir" class="mt-1.5" required />
                        <x-input-error :messages="$errors->get('tempat_lahir')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Lahir" />
                        <x-text-input type="date" name="tanggal_lahir" x-model="form.tanggal_lahir" class="mt-1.5" required />
                        <x-input-error :messages="$errors->get('tanggal_lahir')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Agama" />
                        <x-text-input type="text" name="agama" x-model="form.agama" class="mt-1.5" required />
                        <x-input-error :messages="$errors->get('agama')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Golongan Darah (opsional)" />
                        <x-text-input type="text" name="golongan_darah" x-model="form.golongan_darah" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="No. Telepon/WA Orang Tua" />
                        <x-text-input type="text" name="no_telepon" x-model="form.no_telepon" class="mt-1.5" />
                    </div>
                </section>

                <section class="space-y-4">
                    <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate">Alamat</h3>
                    <div>
                        <x-input-label value="Alamat Jalan" />
                        <x-text-input type="text" name="alamat_jalan" x-model="form.alamat_jalan" class="mt-1.5" required />
                        <x-input-error :messages="$errors->get('alamat_jalan')" class="mt-1.5" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label value="RT" />
                            <x-text-input type="text" name="rt" x-model="form.rt" class="mt-1.5" />
                        </div>
                        <div>
                            <x-input-label value="RW" />
                            <x-text-input type="text" name="rw" x-model="form.rw" class="mt-1.5" />
                        </div>
                    </div>
                    <div>
                        <x-input-label value="Dusun (opsional)" />
                        <x-text-input type="text" name="dusun" x-model="form.dusun" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Desa/Kelurahan" />
                        <x-text-input type="text" name="desa_kelurahan" x-model="form.desa_kelurahan" class="mt-1.5" required />
                        <x-input-error :messages="$errors->get('desa_kelurahan')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Kecamatan" />
                        <x-text-input type="text" name="kecamatan" x-model="form.kecamatan" class="mt-1.5" required />
                        <x-input-error :messages="$errors->get('kecamatan')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Kabupaten/Kota" />
                        <x-text-input type="text" name="kabupaten_kota" x-model="form.kabupaten_kota" class="mt-1.5" required />
                        <x-input-error :messages="$errors->get('kabupaten_kota')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Provinsi" />
                        <x-text-input type="text" name="provinsi" x-model="form.provinsi" class="mt-1.5" required />
                        <x-input-error :messages="$errors->get('provinsi')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Kode Pos (opsional)" />
                        <x-text-input type="text" name="kode_pos" x-model="form.kode_pos" class="mt-1.5" />
                    </div>
                </section>

                <section class="space-y-4">
                    <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate">Data Orang Tua/Wali</h3>
                    <template x-for="(anggota, index) in keluarga" :key="index">
                        <div class="rounded-xl border border-ink/10 p-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-ink">Jenis</label>
                                    <select :name="'keluarga[' + index + '][jenis]'" x-model="anggota.jenis" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                        <option value="ayah">Ayah</option>
                                        <option value="ibu">Ibu</option>
                                        <option value="wali">Wali</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-ink">Nama</label>
                                    <input :name="'keluarga[' + index + '][nama]'" type="text" x-model="anggota.nama" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="block text-sm font-medium text-ink">Pekerjaan</label>
                                <input :name="'keluarga[' + index + '][pekerjaan]'" type="text" x-model="anggota.pekerjaan" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            </div>
                        </div>
                    </template>
                    <button type="button" @click="tambahWali()" class="text-sm font-medium text-ink hover:text-brass">+ Tambah Wali</button>
                    <x-input-error :messages="$errors->get('keluarga')" class="mt-1.5" />
                </section>

                <x-primary-button>Lanjut ke Formulir Tambahan</x-primary-button>
            </form>
        </x-panel>
    </div>
</x-spmb-public-layout>

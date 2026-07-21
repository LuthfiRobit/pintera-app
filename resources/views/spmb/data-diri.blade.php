{{-- resources/views/spmb/data-diri.blade.php --}}
<x-layouts.portal-wizard title="Data Diri" current="data-diri" :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal">
    <div
        x-data="dataDiriForm({
            cekNikUrl: '{{ route('portal.wizard.data-diri.cek-nik') }}',
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
        class="rounded-2xl border border-gray-200 bg-white p-6"
    >
        <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-portal-50 text-portal-500">
                <x-icon name="person" class="h-4 w-4" />
            </span>
            <h2 class="text-[15.5px] font-bold text-gray-900">Data Diri &amp; Alamat</h2>
        </div>
        <div class="my-4 h-px bg-gray-200"></div>

        <form method="POST" action="{{ route('portal.wizard.data-diri.store') }}" class="space-y-6">
            @csrf

            <div x-show="pesanBlokir" x-cloak class="rounded-xl border border-error-500/30 bg-error-50 p-4 text-[13px] text-error-700" x-text="pesanBlokir"></div>
            @error('nik')
                <p class="text-[12px] text-error-700">{{ $message }}</p>
            @enderror

            <section class="space-y-4">
                <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Data Pribadi</h3>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">NIK</label>
                    <input type="text" name="nik" value="{{ old('nik') }}" inputmode="numeric" maxlength="16" required @blur="cekNik($event.target.value)"
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    <p x-show="checking" x-cloak class="mt-1.5 text-[11px] text-gray-400">Memeriksa NIK...</p>
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Nama Lengkap (sesuai akta)</label>
                    <input type="text" name="nama_lengkap" x-model="form.nama_lengkap" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('nama_lengkap') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">NISN (opsional)</label>
                    <input type="text" name="nisn" x-model="form.nisn"
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Jenis Kelamin</label>
                    <select name="jenis_kelamin" x-model="form.jenis_kelamin" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        <option value="">Pilih</option>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                    @error('jenis_kelamin') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" x-model="form.tempat_lahir" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('tempat_lahir') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" x-model="form.tanggal_lahir" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('tanggal_lahir') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Agama</label>
                    <input type="text" name="agama" x-model="form.agama" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('agama') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Golongan Darah (opsional)</label>
                    <input type="text" name="golongan_darah" x-model="form.golongan_darah"
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">No. Telepon/WA Orang Tua</label>
                    <input type="text" name="no_telepon" x-model="form.no_telepon"
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Alamat</h3>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Alamat Jalan</label>
                    <input type="text" name="alamat_jalan" x-model="form.alamat_jalan" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('alamat_jalan') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">RT</label>
                        <input type="text" name="rt" x-model="form.rt"
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">RW</label>
                        <input type="text" name="rw" x-model="form.rw"
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Dusun (opsional)</label>
                    <input type="text" name="dusun" x-model="form.dusun"
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Desa/Kelurahan</label>
                    <input type="text" name="desa_kelurahan" x-model="form.desa_kelurahan" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('desa_kelurahan') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kecamatan</label>
                    <input type="text" name="kecamatan" x-model="form.kecamatan" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('kecamatan') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kabupaten/Kota</label>
                    <input type="text" name="kabupaten_kota" x-model="form.kabupaten_kota" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('kabupaten_kota') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Provinsi</label>
                    <input type="text" name="provinsi" x-model="form.provinsi" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('provinsi') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kode Pos (opsional)</label>
                    <input type="text" name="kode_pos" x-model="form.kode_pos"
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Data Orang Tua/Wali</h3>
                <template x-for="(anggota, index) in keluarga" :key="index">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Jenis</label>
                                <select :name="'keluarga[' + index + '][jenis]'" x-model="anggota.jenis"
                                    class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                                    <option value="ayah">Ayah</option>
                                    <option value="ibu">Ibu</option>
                                    <option value="wali">Wali</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Nama</label>
                                <input :name="'keluarga[' + index + '][nama]'" type="text" x-model="anggota.nama"
                                    class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Pekerjaan</label>
                            <input :name="'keluarga[' + index + '][pekerjaan]'" type="text" x-model="anggota.pekerjaan"
                                class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        </div>
                    </div>
                </template>
                <button type="button" @click="tambahWali()" class="text-[12.5px] font-bold text-portal-500">+ Tambah Wali</button>
                @error('keluarga') <p class="text-[11px] text-error-700">{{ $message }}</p> @enderror
            </section>

            <div class="flex justify-end border-t border-dashed border-gray-200 pt-5">
                <button type="submit" class="flex items-center justify-center gap-2 rounded-[10px] bg-portal-500 px-6 py-3 text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Simpan &amp; Lanjutkan
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </button>
            </div>
        </form>
    </div>
</x-layouts.portal-wizard>

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
                nama_ayah: @js(old('nama_ayah', '')),
                pekerjaan_ayah: @js(old('pekerjaan_ayah', '')),
                nama_ibu: @js(old('nama_ibu', '')),
                pekerjaan_ibu: @js(old('pekerjaan_ibu', '')),
                nama_wali: @js(old('nama_wali', '')),
                pekerjaan_wali: @js(old('pekerjaan_wali', '')),
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
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Nama Lengkap (sesuai akta)</label>
                    <input type="text" name="nama_lengkap" x-model="form.nama_lengkap" placeholder="Contoh: Ahmad Fauzan" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('nama_lengkap') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3 max-[480px]:grid-cols-1">
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">NIK</label>
                        <input type="text" name="nik" value="{{ old('nik') }}" inputmode="numeric" maxlength="16" placeholder="16 digit sesuai KK" required @blur="cekNik($event.target.value)"
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        <p x-show="checking" x-cloak class="mt-1.5 text-[11px] text-gray-400">Memeriksa NIK...</p>
                    </div>
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Jenis Kelamin</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex cursor-pointer items-center justify-center rounded-[10px] border py-[11px] text-[13.5px] font-semibold transition"
                                :class="form.jenis_kelamin === 'L' ? 'border-portal-500 bg-portal-50 text-portal-500' : 'border-gray-200 text-gray-500'">
                                <input type="radio" name="jenis_kelamin" value="L" x-model="form.jenis_kelamin" class="sr-only" required>
                                Laki-laki
                            </label>
                            <label class="flex cursor-pointer items-center justify-center rounded-[10px] border py-[11px] text-[13.5px] font-semibold transition"
                                :class="form.jenis_kelamin === 'P' ? 'border-portal-500 bg-portal-50 text-portal-500' : 'border-gray-200 text-gray-500'">
                                <input type="radio" name="jenis_kelamin" value="P" x-model="form.jenis_kelamin" class="sr-only" required>
                                Perempuan
                            </label>
                        </div>
                        @error('jenis_kelamin') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 max-[480px]:grid-cols-1">
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">NISN (opsional)</label>
                        <input type="text" name="nisn" x-model="form.nisn" inputmode="numeric" maxlength="10" pattern="[0-9]{10}" title="NISN terdiri dari 10 digit angka" placeholder="10 digit"
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        @error('nisn') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Golongan Darah (opsional)</label>
                        <select name="golongan_darah" x-model="form.golongan_darah"
                            class="field-select field-select-chevron w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                            <option value="">Tidak tahu</option>
                            @foreach ($golonganDarahOptions as $opsi)
                                <option value="{{ $opsi }}">{{ $opsi }}</option>
                            @endforeach
                        </select>
                        @error('golongan_darah') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 max-[480px]:grid-cols-1">
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" x-model="form.tempat_lahir" placeholder="Contoh: Bandung" required
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        @error('tempat_lahir') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Tanggal Lahir</label>
                        <input type="text" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}" placeholder="Pilih tanggal" autocomplete="off" required
                            x-ref="tanggalLahir" x-init="initTanggalLahir($refs.tanggalLahir)"
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        @error('tanggal_lahir') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 max-[480px]:grid-cols-1">
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Agama</label>
                        <select name="agama" x-model="form.agama" required
                            class="field-select field-select-chevron w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                            <option value="">Pilih agama</option>
                            @foreach ($agamaOptions as $opsi)
                                <option value="{{ $opsi }}">{{ $opsi }}</option>
                            @endforeach
                        </select>
                        @error('agama') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">No. Telepon/WA Orang Tua (opsional)</label>
                        <input type="text" name="no_telepon" x-model="form.no_telepon" inputmode="tel" pattern="[0-9+\-\s]{8,20}" title="8-20 karakter, hanya angka, spasi, +, dan -" placeholder="08xx-xxxx-xxxx"
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        @error('no_telepon') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Alamat</h3>

                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Alamat Jalan</label>
                    <input type="text" name="alamat_jalan" x-model="form.alamat_jalan" placeholder="Nama jalan, nomor rumah" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('alamat_jalan') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3 max-[480px]:grid-cols-1">
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">RT (opsional)</label>
                        <input type="text" name="rt" x-model="form.rt" inputmode="numeric" maxlength="3" placeholder="001"
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        @error('rt') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">RW (opsional)</label>
                        <input type="text" name="rw" x-model="form.rw" inputmode="numeric" maxlength="3" placeholder="002"
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        @error('rw') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 max-[480px]:grid-cols-1">
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Dusun (opsional)</label>
                        <input type="text" name="dusun" x-model="form.dusun" placeholder="Nama dusun"
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Desa/Kelurahan</label>
                        <input type="text" name="desa_kelurahan" x-model="form.desa_kelurahan" placeholder="Nama desa/kelurahan" required
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        @error('desa_kelurahan') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 max-[480px]:grid-cols-1">
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kecamatan</label>
                        <input type="text" name="kecamatan" x-model="form.kecamatan" placeholder="Nama kecamatan" required
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        @error('kecamatan') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kabupaten/Kota</label>
                        <input type="text" name="kabupaten_kota" x-model="form.kabupaten_kota" placeholder="Nama kabupaten/kota" required
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        @error('kabupaten_kota') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 max-[480px]:grid-cols-1">
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Provinsi</label>
                        <input type="text" name="provinsi" x-model="form.provinsi" placeholder="Nama provinsi" required
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        @error('provinsi') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kode Pos (opsional)</label>
                        <input type="text" name="kode_pos" x-model="form.kode_pos" inputmode="numeric" maxlength="5" placeholder="40123"
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        @error('kode_pos') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Data Orang Tua/Wali</h3>

                <div class="rounded-xl border border-gray-200 p-4">
                    <p class="mb-3 text-[12.5px] font-bold text-gray-900">Data Ayah</p>
                    <div class="grid grid-cols-2 gap-3 max-[480px]:grid-cols-1">
                        <div>
                            <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Nama Ayah</label>
                            <input type="text" name="nama_ayah" x-model="keluarga.ayah.nama" placeholder="Nama lengkap ayah" required
                                class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                            @error('nama_ayah') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Pekerjaan (opsional)</label>
                            <select name="pekerjaan_ayah" x-ref="pekerjaanAyah" x-init="initPekerjaanSelect($refs.pekerjaanAyah, 'ayah')">
                                <option value="">Cari pekerjaan...</option>
                                @foreach ($pekerjaanOptions as $opsi)
                                    <option value="{{ $opsi }}" @selected(old('pekerjaan_ayah') === $opsi)>{{ $opsi }}</option>
                                @endforeach
                            </select>
                            @error('pekerjaan_ayah') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 p-4">
                    <p class="mb-3 text-[12.5px] font-bold text-gray-900">Data Ibu</p>
                    <div class="grid grid-cols-2 gap-3 max-[480px]:grid-cols-1">
                        <div>
                            <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Nama Ibu</label>
                            <input type="text" name="nama_ibu" x-model="keluarga.ibu.nama" placeholder="Nama lengkap ibu" required
                                class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                            @error('nama_ibu') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Pekerjaan (opsional)</label>
                            <select name="pekerjaan_ibu" x-ref="pekerjaanIbu" x-init="initPekerjaanSelect($refs.pekerjaanIbu, 'ibu')">
                                <option value="">Cari pekerjaan...</option>
                                @foreach ($pekerjaanOptions as $opsi)
                                    <option value="{{ $opsi }}" @selected(old('pekerjaan_ibu') === $opsi)>{{ $opsi }}</option>
                                @endforeach
                            </select>
                            @error('pekerjaan_ibu') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-dashed border-gray-300 p-4">
                    <p class="mb-3 text-[12.5px] font-bold text-gray-900">Data Wali <span class="font-normal text-gray-400">(opsional, isi kalau ada)</span></p>
                    <div class="grid grid-cols-2 gap-3 max-[480px]:grid-cols-1">
                        <div>
                            <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Nama Wali</label>
                            <input type="text" name="nama_wali" x-model="keluarga.wali.nama" placeholder="Nama lengkap wali"
                                class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                            @error('nama_wali') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Pekerjaan (opsional)</label>
                            <select name="pekerjaan_wali" x-ref="pekerjaanWali" x-init="initPekerjaanSelect($refs.pekerjaanWali, 'wali')">
                                <option value="">Cari pekerjaan...</option>
                                @foreach ($pekerjaanOptions as $opsi)
                                    <option value="{{ $opsi }}" @selected(old('pekerjaan_wali') === $opsi)>{{ $opsi }}</option>
                                @endforeach
                            </select>
                            @error('pekerjaan_wali') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
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

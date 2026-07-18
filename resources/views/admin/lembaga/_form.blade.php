@php
    $lembaga = $lembaga ?? null;
    $val = fn (string $field, $default = '') => old($field, $lembaga?->$field ?? $default);
    $selectClass = 'w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500';
@endphp

<div class="space-y-4">
    {{-- Identitas Lembaga --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="apartment" class="h-[15px] w-[15px] text-gray-400" />
            Identitas Lembaga
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @if ($yayasanList ?? null)
                <div>
                    <x-input-label value="Yayasan" />
                    <select name="yayasan_id" class="mt-1.5 {{ $selectClass }}">
                        @foreach ($yayasanList as $yayasan)
                            <option value="{{ $yayasan->id }}" @selected($val('yayasan_id') == $yayasan->id)>{{ $yayasan->nama }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif ($lembaga)
                <input type="hidden" name="yayasan_id" value="{{ $lembaga->yayasan_id }}">
                <div>
                    <x-input-label value="Yayasan" />
                    <x-text-input value="{{ $lembaga->yayasan->nama ?? '—' }}" disabled class="mt-1.5" />
                </div>
            @endif

            <div>
                <x-input-label value="NPSN" />
                <x-text-input type="text" name="npsn" value="{{ $val('npsn') }}" placeholder="Contoh: 20301234" class="mt-1.5 font-mono" />
                <x-input-error :messages="$errors->get('npsn')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="NSS (Nomor Statistik Sekolah)" />
                <x-text-input type="text" name="nss" value="{{ $val('nss') }}" placeholder="Contoh: 201056301045" class="mt-1.5 font-mono" />
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <x-input-label value="Nama Lembaga" />
                <x-text-input type="text" name="nama" value="{{ $val('nama') }}" placeholder="Nama resmi sesuai SK Pendirian" class="mt-1.5" />
                <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Bentuk Pendidikan" />
                <select name="bentuk_pendidikan" class="mt-1.5 {{ $selectClass }}">
                    <option value="" disabled selected>Pilih bentuk pendidikan</option>
                    @foreach (['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB'] as $bentuk)
                        <option value="{{ $bentuk }}" @selected($val('bentuk_pendidikan') === $bentuk)>{{ $bentuk }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label value="Status Sekolah" />
                <select name="status_sekolah" class="mt-1.5 {{ $selectClass }}">
                    <option value="" disabled selected>Pilih status sekolah</option>
                    <option value="negeri" @selected($val('status_sekolah') === 'negeri')>Negeri</option>
                    <option value="swasta" @selected($val('status_sekolah') === 'swasta')>Swasta</option>
                </select>
            </div>

            <div>
                <x-input-label value="Status Kepemilikan" />
                <x-text-input type="text" name="status_kepemilikan" value="{{ $val('status_kepemilikan') }}" placeholder="Contoh: Yayasan, Pemerintah Daerah" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Naungan" />
                <select name="naungan" class="mt-1.5 {{ $selectClass }}">
                    <option value="" disabled selected>Pilih naungan</option>
                    <option value="kemendikdasmen" @selected($val('naungan') === 'kemendikdasmen')>Kemendikdasmen</option>
                    <option value="kemenag" @selected($val('naungan') === 'kemenag')>Kemenag</option>
                </select>
            </div>

            <div>
                <x-input-label value="Akreditasi" />
                <select name="akreditasi" class="mt-1.5 {{ $selectClass }}">
                    @foreach (['belum' => 'Belum Terakreditasi', 'A' => 'A', 'B' => 'B', 'C' => 'C'] as $value => $label)
                        <option value="{{ $value }}" @selected($val('akreditasi', 'belum') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end pb-1">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="status_aktif" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked($val('status_aktif', true))>
                    Lembaga aktif
                </label>
            </div>
        </div>
    </div>

    {{-- Legalitas & Perizinan --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="fact_check" class="h-[15px] w-[15px] text-gray-400" />
            Legalitas &amp; Perizinan
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <x-input-label value="Nomor SK Pendirian" />
                <x-text-input type="text" name="sk_pendirian_nomor" value="{{ $val('sk_pendirian_nomor') }}" placeholder="Contoh: 421.2/123/SK/2010" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Tanggal SK Pendirian" />
                <x-text-input type="date" name="sk_pendirian_tanggal" value="{{ $val('sk_pendirian_tanggal') }}" class="mt-1.5" />
            </div>
            <div></div>

            <div>
                <x-input-label value="Nomor SK Izin Operasional" />
                <x-text-input type="text" name="sk_izin_operasional_nomor" value="{{ $val('sk_izin_operasional_nomor') }}" placeholder="Contoh: 421.2/456/SK/2015" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Tanggal SK Izin Operasional" />
                <x-text-input type="date" name="sk_izin_operasional_tanggal" value="{{ $val('sk_izin_operasional_tanggal') }}" class="mt-1.5" />
            </div>
            <div></div>

            <div>
                <x-input-label value="Nomor SK Akreditasi" />
                <x-text-input type="text" name="sk_akreditasi_nomor" value="{{ $val('sk_akreditasi_nomor') }}" placeholder="Contoh: 123/BAN-SM/SK/2022" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Tanggal SK Akreditasi" />
                <x-text-input type="date" name="tanggal_sk_akreditasi" value="{{ $val('tanggal_sk_akreditasi') }}" class="mt-1.5" />
            </div>
        </div>
    </div>

    {{-- Kepemimpinan --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="group" class="h-[15px] w-[15px] text-gray-400" />
            Kepemimpinan
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label value="Nama Kepala Sekolah" />
                <x-text-input type="text" name="nama_kepala_sekolah" value="{{ $val('nama_kepala_sekolah') }}" placeholder="Nama lengkap beserta gelar" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Nama Bendahara BOSP" />
                <x-text-input type="text" name="nama_bendahara_bosp" value="{{ $val('nama_bendahara_bosp') }}" placeholder="Nama lengkap beserta gelar" class="mt-1.5" />
            </div>
        </div>
    </div>

    {{-- Alamat --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="signpost" class="h-[15px] w-[15px] text-gray-400" />
            Alamat
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="sm:col-span-2 lg:col-span-3">
                <x-input-label value="Alamat Jalan" />
                <textarea name="alamat_jalan" rows="2" placeholder="Nama jalan, nomor, patokan" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ $val('alamat_jalan') }}</textarea>
            </div>

            <div>
                <x-input-label value="RT" />
                <x-text-input type="text" name="rt" value="{{ $val('rt') }}" placeholder="Contoh: 002" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="RW" />
                <x-text-input type="text" name="rw" value="{{ $val('rw') }}" placeholder="Contoh: 005" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Dusun" />
                <x-text-input type="text" name="nama_dusun" value="{{ $val('nama_dusun') }}" placeholder="Nama dusun (jika ada)" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Desa / Kelurahan" />
                <x-text-input type="text" name="desa_kelurahan" value="{{ $val('desa_kelurahan') }}" placeholder="Nama desa atau kelurahan" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Kecamatan" />
                <x-text-input type="text" name="kecamatan" value="{{ $val('kecamatan') }}" placeholder="Nama kecamatan" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Kabupaten / Kota" />
                <x-text-input type="text" name="kabupaten_kota" value="{{ $val('kabupaten_kota') }}" placeholder="Nama kabupaten atau kota" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Provinsi" />
                <x-text-input type="text" name="provinsi" value="{{ $val('provinsi') }}" placeholder="Nama provinsi" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Kode Pos" />
                <x-text-input type="text" name="kode_pos" value="{{ $val('kode_pos') }}" placeholder="Contoh: 65119" class="mt-1.5" />
            </div>
            <div></div>

            <div>
                <x-input-label value="Lintang (Latitude)" />
                <x-text-input type="text" name="lintang" value="{{ $val('lintang') }}" placeholder="Contoh: -7.9666000" class="mt-1.5 font-mono" />
                <x-input-error :messages="$errors->get('lintang')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Bujur (Longitude)" />
                <x-text-input type="text" name="bujur" value="{{ $val('bujur') }}" placeholder="Contoh: 112.6326000" class="mt-1.5 font-mono" />
                <x-input-error :messages="$errors->get('bujur')" class="mt-1.5" />
            </div>
        </div>
    </div>

    {{-- Kontak --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="notifications" class="h-[15px] w-[15px] text-gray-400" />
            Kontak
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <x-input-label value="Telepon" />
                <x-text-input type="text" name="telepon" value="{{ $val('telepon') }}" placeholder="Contoh: 0341-123456" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Fax" />
                <x-text-input type="text" name="fax" value="{{ $val('fax') }}" placeholder="Contoh: 0341-123457" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Email" />
                <x-text-input type="email" name="email" value="{{ $val('email') }}" placeholder="lembaga@pintera.sch.id" class="mt-1.5" />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Website" />
                <x-text-input type="text" name="website" value="{{ $val('website') }}" placeholder="https://lembaga.sch.id" class="mt-1.5" />
            </div>
        </div>
    </div>

    {{-- Data Bank & Pajak --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="payments" class="h-[15px] w-[15px] text-gray-400" />
            Data Bank &amp; Pajak
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <x-input-label value="Nama Bank" />
                <x-text-input type="text" name="nama_bank" value="{{ $val('nama_bank') }}" placeholder="Contoh: Bank Jatim" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Cabang / KCP / Unit" />
                <x-text-input type="text" name="cabang_kcp_unit" value="{{ $val('cabang_kcp_unit') }}" placeholder="Contoh: KCP Malang Kota" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Rekening Atas Nama" />
                <x-text-input type="text" name="rekening_atas_nama" value="{{ $val('rekening_atas_nama') }}" placeholder="Nama pemilik rekening" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Nomor Rekening" />
                <x-text-input type="text" name="nomor_rekening" value="{{ $val('nomor_rekening') }}" placeholder="Contoh: 0012345678" class="mt-1.5 font-mono" />
            </div>
            <div>
                <x-input-label value="Nama Wajib Pajak" />
                <x-text-input type="text" name="nama_wajib_pajak" value="{{ $val('nama_wajib_pajak') }}" placeholder="Sesuai NPWP" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="NPWP" />
                <x-text-input type="text" name="npwp" value="{{ $val('npwp') }}" placeholder="Contoh: 01.234.567.8-901.000" class="mt-1.5 font-mono" />
            </div>

            <div class="flex items-center pt-1">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="mbs" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked($val('mbs'))>
                    Menerapkan MBS (Manajemen Berbasis Sekolah)
                </label>
            </div>
            <div class="flex items-center pt-1">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="memungut_iuran" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked($val('memungut_iuran'))>
                    Memungut iuran dari wali murid
                </label>
            </div>
            <div></div>

            <div>
                <x-input-label value="Nominal Iuran" />
                <x-text-input type="number" step="0.01" name="nominal_iuran" value="{{ $val('nominal_iuran') }}" placeholder="Contoh: 150000" class="mt-1.5" />
                <x-input-error :messages="$errors->get('nominal_iuran')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Periode Iuran" />
                <select name="periode_iuran" class="mt-1.5 {{ $selectClass }}">
                    <option value="">Tidak berlaku</option>
                    <option value="bulanan" @selected($val('periode_iuran') === 'bulanan')>Bulanan</option>
                    <option value="tahunan" @selected($val('periode_iuran') === 'tahunan')>Tahunan</option>
                </select>
            </div>
        </div>
    </div>
</div>

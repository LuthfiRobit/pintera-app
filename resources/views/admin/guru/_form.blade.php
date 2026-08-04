@php
    $guru = $guru ?? null;
    $val = fn (string $field, $default = '') => old($field, $guru?->$field ?? $default);
    $formatDate = function (string $field) use ($val) {
        $raw = $val($field);
        if (! $raw) {
            return '';
        }
        try {
            return \Illuminate\Support\Carbon::parse($raw)->format('Y-m-d');
        } catch (\Exception) {
            return '';
        }
    };
    $inputClass = 'mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500';
    $selectClass = 'mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500';
@endphp

<div class="space-y-4">
    {{-- Akun & Identitas --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="person" class="h-[15px] w-[15px] text-gray-400" />
            Akun &amp; Identitas
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label value="Nama Lengkap *" />
                <input type="text" name="nama" value="{{ $val('nama') }}" class="{{ $inputClass }}">
                <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="NIK *" />
                <input type="text" name="nik" value="{{ $val('nik') }}" class="{{ $inputClass }} font-mono" maxlength="16">
                <x-input-error :messages="$errors->get('nik')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="NIP *" />
                <input type="text" name="nip" value="{{ $val('nip') }}" class="{{ $inputClass }} font-mono">
                @if ($guru === null)
                    <p class="mt-1 text-xs text-gray-400">NIP ini otomatis menjadi password login guru.</p>
                @else
                    <p class="mt-1 text-xs text-gray-400">Mengubah NIP di sini tidak mengubah ulang password akun yang sudah ada.</p>
                @endif
                <x-input-error :messages="$errors->get('nip')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Email *" />
                <input type="email" name="email" value="{{ $val('email') }}" class="{{ $inputClass }}">
                <p class="mt-1 text-xs text-gray-400">Email ini menjadi username login guru.</p>
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Jenis Kelamin *" />
                <select name="jenis_kelamin" class="{{ $selectClass }}">
                    @foreach ($jenisKelaminOptions as $value => $label)
                        <option value="{{ $value }}" @selected($val('jenis_kelamin') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('jenis_kelamin')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Jenis PTK *" />
                <select name="jenis_ptk" class="{{ $selectClass }}">
                    @foreach ($jenisPtkOptions as $value => $label)
                        <option value="{{ $value }}" @selected($val('jenis_ptk') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('jenis_ptk')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Kapasitas Kasus Aktif" />
                <input type="number" name="kapasitas_kasus_aktif" min="0" value="{{ $val('kapasitas_kasus_aktif') }}" class="{{ $inputClass }}">
                <p class="mt-1 text-xs text-gray-400">Khusus Guru BK. Kosongkan kalau tidak dibatasi.</p>
                <x-input-error :messages="$errors->get('kapasitas_kasus_aktif')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Status Kepegawaian *" />
                <select name="status_kepegawaian" class="{{ $selectClass }}">
                    @foreach ($statusKepegawaianOptions as $value => $label)
                        <option value="{{ $value }}" @selected($val('status_kepegawaian') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status_kepegawaian')" class="mt-1.5" />
            </div>
        </div>
    </div>

    {{-- Data Pribadi --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="description" class="h-[15px] w-[15px] text-gray-400" />
            Data Pribadi (Opsional)
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <x-input-label value="NUPTK" />
                <input type="text" name="nuptk" value="{{ $val('nuptk') }}" class="{{ $inputClass }} font-mono">
                <x-input-error :messages="$errors->get('nuptk')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Tempat Lahir" />
                <input type="text" name="tempat_lahir" value="{{ $val('tempat_lahir') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Tanggal Lahir" />
                <input type="date" name="tanggal_lahir" value="{{ $formatDate('tanggal_lahir') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Agama" />
                <input type="text" name="agama" value="{{ $val('agama') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Kewarganegaraan" />
                <input type="text" name="kewarganegaraan" value="{{ $val('kewarganegaraan', 'WNI') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="No. HP" />
                <input type="text" name="no_hp" value="{{ $val('no_hp') }}" class="{{ $inputClass }}">
            </div>
        </div>
    </div>

    {{-- Alamat --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="location_on" class="h-[15px] w-[15px] text-gray-400" />
            Alamat (Opsional)
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="sm:col-span-2 lg:col-span-3">
                <x-input-label value="Alamat Jalan" />
                <input type="text" name="alamat_jalan" value="{{ $val('alamat_jalan') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="RT" />
                <input type="text" name="rt" value="{{ $val('rt') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="RW" />
                <input type="text" name="rw" value="{{ $val('rw') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Kode Pos" />
                <input type="text" name="kode_pos" value="{{ $val('kode_pos') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Desa/Kelurahan" />
                <input type="text" name="desa_kelurahan" value="{{ $val('desa_kelurahan') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Kecamatan" />
                <input type="text" name="kecamatan" value="{{ $val('kecamatan') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Kabupaten/Kota" />
                <input type="text" name="kabupaten_kota" value="{{ $val('kabupaten_kota') }}" class="{{ $inputClass }}">
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <x-input-label value="Provinsi" />
                <input type="text" name="provinsi" value="{{ $val('provinsi') }}" class="{{ $inputClass }}">
            </div>
        </div>
    </div>

    {{-- Kepegawaian --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="checklist" class="h-[15px] w-[15px] text-gray-400" />
            Kepegawaian (Opsional)
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <x-input-label value="Golongan/Pangkat" />
                <input type="text" name="golongan_pangkat" value="{{ $val('golongan_pangkat') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="TMT Tugas" />
                <input type="date" name="tmt_tugas" value="{{ $formatDate('tmt_tugas') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="TMT PNS" />
                <input type="date" name="tmt_pns" value="{{ $formatDate('tmt_pns') }}" class="{{ $inputClass }}">
            </div>
        </div>
    </div>
</div>

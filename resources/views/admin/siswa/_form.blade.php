@php
    $siswa = $siswa ?? null;
    $val = fn (string $field, $default = '') => old($field, $siswa?->$field ?? $default);
    $selectClass = 'w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500';
@endphp

<div class="space-y-4">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="groups" class="h-[15px] w-[15px] text-gray-400" />
            Identitas Siswa
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <x-input-label value="Kelas (opsional)" />
                <select name="kelas_id" class="mt-1.5 {{ $selectClass }}">
                    <option value="">— Belum ditempatkan —</option>
                    @foreach ($kelasList as $kelas)
                        <option value="{{ $kelas->id }}" @selected($val('kelas_id') == $kelas->id)>{{ $kelas->nama }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('kelas_id')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="NIS" />
                <x-text-input type="text" name="nis" value="{{ $val('nis') }}" class="mt-1.5 font-mono" />
                <x-input-error :messages="$errors->get('nis')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="NISN" />
                <x-text-input type="text" name="nisn" value="{{ $val('nisn') }}" class="mt-1.5 font-mono" />
                <x-input-error :messages="$errors->get('nisn')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <x-input-label value="Nama Lengkap" />
                <x-text-input type="text" name="nama_lengkap" value="{{ $val('nama_lengkap') }}" class="mt-1.5" />
                <x-input-error :messages="$errors->get('nama_lengkap')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Jenis Kelamin" />
                <select name="jenis_kelamin" class="mt-1.5 {{ $selectClass }}">
                    <option value="L" @selected($val('jenis_kelamin') === 'L')>Laki-laki</option>
                    <option value="P" @selected($val('jenis_kelamin') === 'P')>Perempuan</option>
                </select>
                <x-input-error :messages="$errors->get('jenis_kelamin')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Tanggal Lahir" />
                <x-text-input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', optional($siswa?->tanggal_lahir)->format('Y-m-d')) }}" class="mt-1.5" />
                <x-input-error :messages="$errors->get('tanggal_lahir')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Tempat Lahir" />
                <x-text-input type="text" name="tempat_lahir" value="{{ $val('tempat_lahir') }}" class="mt-1.5" />
                <x-input-error :messages="$errors->get('tempat_lahir')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Agama" />
                <select name="agama" class="mt-1.5 {{ $selectClass }}">
                    <option value="">— Pilih agama —</option>
                    @foreach (['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $agama)
                        <option value="{{ $agama }}" @selected($val('agama') === $agama)>{{ $agama }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('agama')" class="mt-1.5" />
            </div>
        </div>
    </div>
</div>

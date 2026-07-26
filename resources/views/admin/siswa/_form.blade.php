@php
    $siswa = $siswa ?? null;
    $val = fn (string $field, $default = '') => old($field, $siswa?->$field ?? $default);
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    {{-- Card Header --}}
    <div class="border-b border-gray-100 bg-white px-6 py-4">
        <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
            <x-icon name="groups" class="h-4 w-4 text-brand-500" />
            Identitas Siswa
        </p>
        <p class="mt-0.5 text-xs text-gray-500">Lengkapi data induk siswa, informasi kelahiran, serta penempatan rombel kelas awal.</p>
    </div>

    {{-- Form Body (12-Column Grid) --}}
    <div class="p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            <div class="sm:col-span-6">
                <x-input-label value="NIS (Nomor Induk Siswa)" />
                <x-text-input type="text" name="nis" value="{{ $val('nis') }}" placeholder="Contoh: 2026001" class="mt-1.5 w-full font-mono transition duration-150" />
                <x-input-error :messages="$errors->get('nis')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-6">
                <x-input-label value="NISN (National ID)" />
                <x-text-input type="text" name="nisn" value="{{ $val('nisn') }}" placeholder="10 digit NISN nasional" class="mt-1.5 w-full font-mono transition duration-150" />
                <x-input-error :messages="$errors->get('nisn')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-12">
                <x-input-label value="Nama Lengkap" />
                <x-text-input type="text" name="nama_lengkap" value="{{ $val('nama_lengkap') }}" placeholder="Nama lengkap sesuai akta kelahiran" class="mt-1.5 w-full transition duration-150" />
                <x-input-error :messages="$errors->get('nama_lengkap')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-6">
                <x-input-label value="Jenis Kelamin" />
                <select name="jenis_kelamin" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    <option value="L" @selected($val('jenis_kelamin') === 'L')>Laki-laki</option>
                    <option value="P" @selected($val('jenis_kelamin') === 'P')>Perempuan</option>
                </select>
                <x-input-error :messages="$errors->get('jenis_kelamin')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-6">
                <x-input-label value="Agama" />
                <select name="agama" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">— Pilih agama —</option>
                    @foreach (['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $agama)
                        <option value="{{ $agama }}" @selected($val('agama') === $agama)>{{ $agama }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('agama')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-6">
                <x-input-label value="Tempat Lahir" />
                <x-text-input type="text" name="tempat_lahir" value="{{ $val('tempat_lahir') }}" placeholder="Contoh: Bandung, Jakarta, Surabaya" class="mt-1.5 w-full transition duration-150" />
                <x-input-error :messages="$errors->get('tempat_lahir')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-6">
                <x-input-label value="Tanggal Lahir" />
                <x-text-input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', optional($siswa?->tanggal_lahir)->format('Y-m-d')) }}" class="mt-1.5 w-full transition duration-150" />
                <x-input-error :messages="$errors->get('tanggal_lahir')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-12">
                <x-input-label value="Penempatan Kelas (opsional)" />
                <select name="kelas_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">— Belum ditempatkan —</option>
                    @foreach ($kelasList as $kelas)
                        <option value="{{ $kelas->id }}" @selected($val('kelas_id') == $kelas->id)>{{ $kelas->nama }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('kelas_id')" class="mt-1.5" />
            </div>
        </div>
    </div>

    {{-- Card Footer Action Bar --}}
    <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
        <a href="{{ route('admin.siswa.index') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors duration-200 hover:bg-gray-200/50 hover:text-gray-900">
            Batal
        </a>
        <x-primary-button type="submit" class="shadow-sm transition-all duration-200 active:scale-[0.98]">
            {{ $submitText ?? 'Simpan Data' }}
        </x-primary-button>
    </div>
</div>

@php
    $kelas = $kelas ?? null;
    $val = fn (string $field, $default = '') => old($field, $kelas?->$field ?? $default);
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    {{-- Card Header --}}
    <div class="border-b border-gray-100 bg-white px-6 py-4">
        <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
            <x-icon name="group" class="h-4 w-4 text-brand-500" />
            Identitas Kelas
        </p>
        <p class="mt-0.5 text-xs text-gray-500">Lengkapi data nama kelas, tahun ajaran terkait, serta penugasan wali kelas.</p>
    </div>

    {{-- Form Body (12-Column Grid) --}}
    <div class="p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            <div class="sm:col-span-6">
                <x-input-label value="Tahun Ajaran" />
                <select name="tahun_ajaran_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($tahunAjaranList as $tahunAjaran)
                        <option value="{{ $tahunAjaran->id }}" @selected($val('tahun_ajaran_id') == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('tahun_ajaran_id')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-6">
                <x-input-label value="Pola Jam (opsional)" />
                <select name="pola_jam_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">— Belum ditentukan —</option>
                    @foreach ($polaJamList as $pola)
                        <option value="{{ $pola->id }}" @selected($val('pola_jam_id') == $pola->id)>{{ $pola->nama }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('pola_jam_id')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-7">
                <x-input-label value="Nama Kelas" />
                <x-text-input type="text" name="nama" value="{{ $val('nama') }}" placeholder="Contoh: 6A, Kelompok A, XI IPA 2" class="mt-1.5 w-full transition duration-150" />
                <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-5">
                <x-input-label value="Tingkat (opsional)" />
                <x-text-input type="text" name="tingkat" value="{{ $val('tingkat') }}" placeholder="Contoh: 6, XI (Kosongkan utk PAUD)" class="mt-1.5 w-full transition duration-150" />
                <x-input-error :messages="$errors->get('tingkat')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-12">
                <x-input-label value="Wali Kelas (opsional)" />
                <select name="wali_kelas_guru_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">— Belum ditentukan —</option>
                    @foreach ($guruList as $guru)
                        <option value="{{ $guru->id }}" @selected($val('wali_kelas_guru_id') == $guru->id)>{{ $guru->nama }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('wali_kelas_guru_id')" class="mt-1.5" />
            </div>
        </div>
    </div>

    {{-- Card Footer Action Bar --}}
    <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
        <a href="{{ route('admin.kelas.index') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors duration-200 hover:bg-gray-200/50 hover:text-gray-900">
            Batal
        </a>
        <x-primary-button type="submit" class="shadow-sm transition-all duration-200 active:scale-[0.98]">
            {{ $submitText ?? 'Simpan Data' }}
        </x-primary-button>
    </div>
</div>

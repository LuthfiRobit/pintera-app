@php
    $mataPelajaran = $mataPelajaran ?? null;
    $val = fn (string $field, $default = '') => old($field, $mataPelajaran?->$field instanceof \BackedEnum ? $mataPelajaran->$field->value : ($mataPelajaran?->$field ?? $default));
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    {{-- Card Header --}}
    <div class="border-b border-gray-100 bg-white px-6 py-4">
        <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
            <x-icon name="description" class="h-4 w-4 text-brand-500" />
            Identitas Mata Pelajaran
        </p>
        <p class="mt-0.5 text-xs text-gray-500">Tentukan nama dan pengelompokan tipe untuk mata pelajaran atau aspek perkembangan.</p>
    </div>

    {{-- Form Body (12-Column Grid) --}}
    <div class="p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            <div class="sm:col-span-7">
                <x-input-label value="Nama Mata Pelajaran" />
                <x-text-input type="text" name="nama" value="{{ $val('nama') }}" placeholder="Contoh: Matematika, Bahasa Indonesia, Nilai Agama..." class="mt-1.5 w-full transition duration-150" />
                <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-5">
                <x-input-label value="Tipe" />
                <select name="tipe" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    <option value="mapel" @selected($val('tipe', 'mapel') === 'mapel')>Mata Pelajaran</option>
                    <option value="aspek_perkembangan" @selected($val('tipe') === 'aspek_perkembangan')>Aspek Perkembangan (PAUD)</option>
                </select>
                <x-input-error :messages="$errors->get('tipe')" class="mt-1.5" />
            </div>
        </div>
    </div>

    {{-- Card Footer Action Bar --}}
    <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
        <a href="{{ route('admin.mata-pelajaran.index') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors duration-200 hover:bg-gray-200/50 hover:text-gray-900">
            Batal
        </a>
        <x-primary-button type="submit" class="shadow-sm transition-all duration-200 active:scale-[0.98]">
            {{ $submitText ?? 'Simpan Data' }}
        </x-primary-button>
    </div>
</div>

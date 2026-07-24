@php
    $mataPelajaran = $mataPelajaran ?? null;
    $val = fn (string $field, $default = '') => old($field, $mataPelajaran?->$field instanceof \BackedEnum ? $mataPelajaran->$field->value : ($mataPelajaran?->$field ?? $default));
    $selectClass = 'w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500';
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
    <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
        <x-icon name="description" class="h-[15px] w-[15px] text-gray-400" />
        Identitas Mata Pelajaran
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input-label value="Nama" />
            <x-text-input type="text" name="nama" value="{{ $val('nama') }}" placeholder="Contoh: Matematika" class="mt-1.5" />
            <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label value="Tipe" />
            <select name="tipe" class="mt-1.5 {{ $selectClass }}">
                <option value="mapel" @selected($val('tipe') === 'mapel')>Mata Pelajaran</option>
                <option value="aspek_perkembangan" @selected($val('tipe') === 'aspek_perkembangan')>Aspek Perkembangan (PAUD)</option>
            </select>
            <x-input-error :messages="$errors->get('tipe')" class="mt-1.5" />
        </div>
    </div>
</div>

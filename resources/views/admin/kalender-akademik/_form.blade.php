@php
    $entri = $entri ?? null;
    $val = fn (string $field, $default = '') => old($field, $entri?->$field instanceof \BackedEnum ? $entri->$field->value : ($entri?->$field ?? $default));
    $selectClass = 'w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500';
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
    <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
        <x-icon name="calendar_month" class="h-[15px] w-[15px] text-gray-400" />
        Detail Entri
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @if ($entri)
            <div class="sm:col-span-2">
                <p class="text-sm text-gray-500">
                    Tanggal: <span class="font-semibold text-gray-700">{{ $entri->tanggal->translatedFormat('d F Y') }}</span>
                    &middot; Cakupan: <span class="font-semibold text-gray-700">{{ $entri->lembaga_id === null ? 'Nasional' : 'Khusus Lembaga Ini' }}</span>
                    — tanggal &amp; cakupan tidak dapat diubah setelah dibuat.
                </p>
            </div>
        @else
            <div>
                <x-input-label value="Tanggal" />
                <x-text-input type="date" name="tanggal" value="{{ $val('tanggal') }}" class="mt-1.5" />
                <x-input-error :messages="$errors->get('tanggal')" class="mt-1.5" />
            </div>
        @endif

        <div>
            <x-input-label value="Nama" />
            <x-text-input type="text" name="nama" value="{{ $val('nama') }}" placeholder="Libur Semester Ganjil" class="mt-1.5" />
            <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label value="Tipe" />
            <select name="tipe" class="mt-1.5 {{ $selectClass }}">
                <option value="libur" @selected($val('tipe') === 'libur')>Libur</option>
                <option value="kerja" @selected($val('tipe') === 'kerja')>Tetap Masuk (Override)</option>
            </select>
            <x-input-error :messages="$errors->get('tipe')" class="mt-1.5" />
        </div>

        @if (! $entri && ($bolehNasional ?? false))
            <div class="flex items-center pt-1">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="berlaku_nasional" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                    Berlaku untuk semua lembaga (nasional)
                </label>
            </div>
        @endif

        <div class="sm:col-span-2">
            <x-input-label value="Keterangan (opsional)" />
            <textarea name="keterangan" rows="2" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ $val('keterangan') }}</textarea>
            <x-input-error :messages="$errors->get('keterangan')" class="mt-1.5" />
        </div>
    </div>
</div>

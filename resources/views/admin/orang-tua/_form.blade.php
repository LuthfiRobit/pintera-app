@php
    $orangTua = $orangTua ?? null;
    $val = fn (string $field, $default = '') => old($field, $orangTua?->$field ?? $default);
    $inputClass = 'mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500';
@endphp

<div class="space-y-4">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="person" class="h-[15px] w-[15px] text-gray-400" />
            Data Orang Tua/Wali
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label value="Nama Lengkap *" />
                <input type="text" name="nama_lengkap" value="{{ $val('nama_lengkap') }}" class="{{ $inputClass }}">
                <x-input-error :messages="$errors->get('nama_lengkap')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="NIK *" />
                @if ($orangTua === null)
                    <input type="text" name="nik" value="{{ $val('nik') }}" class="{{ $inputClass }} font-mono" maxlength="16">
                    <p class="mt-1 text-xs text-gray-400">NIK ini otomatis menjadi username &amp; password awal login orang tua.</p>
                @else
                    <input type="text" value="{{ $orangTua->nik }}" class="{{ $inputClass }} font-mono bg-gray-50" disabled>
                    <p class="mt-1 text-xs text-gray-400">NIK tidak bisa diubah karena sudah menjadi identitas login.</p>
                @endif
                <x-input-error :messages="$errors->get('nik')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="No. HP *" />
                <input type="text" name="no_hp" value="{{ $val('no_hp') }}" class="{{ $inputClass }}">
                <x-input-error :messages="$errors->get('no_hp')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Email" />
                <input type="email" name="email" value="{{ $val('email') }}" class="{{ $inputClass }}">
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Pekerjaan" />
                <input type="text" name="pekerjaan" value="{{ $val('pekerjaan') }}" class="{{ $inputClass }}">
                <x-input-error :messages="$errors->get('pekerjaan')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label value="Alamat" />
                <textarea name="alamat" rows="2" class="{{ $inputClass }}">{{ $val('alamat') }}</textarea>
                <x-input-error :messages="$errors->get('alamat')" class="mt-1.5" />
            </div>
        </div>
    </div>
</div>

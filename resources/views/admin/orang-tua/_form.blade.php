@php
    $orangTua = $orangTua ?? null;
    $val = fn (string $field, $default = '') => old($field, $orangTua?->$field ?? $default);
@endphp

<div class="space-y-4">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="person" class="h-[15px] w-[15px] text-gray-400" />
            Data Orang Tua/Wali
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label value="Nama Lengkap" required />
                <x-text-input type="text" name="nama_lengkap" value="{{ $val('nama_lengkap') }}" required placeholder="Contoh: Budi Santoso" class="mt-1.5 block w-full" :error="$errors->has('nama_lengkap')" />
                <x-input-error :messages="$errors->get('nama_lengkap')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="NIK" required />
                @if ($orangTua === null)
                    <x-text-input type="text" name="nik" value="{{ $val('nik') }}" required placeholder="16 Digit NIK" class="mt-1.5 block w-full font-mono sm:w-3/4" maxlength="16" minlength="16" pattern="[0-9]+" :error="$errors->has('nik')" />
                    <x-input-hint>NIK ini otomatis menjadi username &amp; password awal login orang tua.</x-input-hint>
                @else
                    <x-text-input type="text" value="{{ $orangTua->nik ?: '-' }}" class="mt-1.5 block w-full font-mono sm:w-3/4" disabled />
                    <x-input-hint>NIK tidak bisa diubah karena sudah menjadi identitas login.</x-input-hint>
                @endif
                <x-input-error :messages="$errors->get('nik')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="No. HP / WhatsApp" required />
                <x-text-input type="tel" name="no_hp" value="{{ $val('no_hp') }}" required placeholder="08xxxxxxxxxx" class="mt-1.5 block w-full sm:w-3/4" maxlength="20" :error="$errors->has('no_hp')" />
                <x-input-hint>Gunakan format awalan 08.</x-input-hint>
                <x-input-error :messages="$errors->get('no_hp')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Email" />
                <x-text-input type="email" name="email" value="{{ $val('email') }}" placeholder="contoh@pintera.id" class="mt-1.5 block w-full" :error="$errors->has('email')" />
                <x-input-hint>(Opsional) Akan digunakan untuk notifikasi sistem.</x-input-hint>
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Pekerjaan" />
                <x-text-input type="text" name="pekerjaan" value="{{ $val('pekerjaan') }}" placeholder="Contoh: Wiraswasta" class="mt-1.5 block w-full" :error="$errors->has('pekerjaan')" />
                <x-input-hint>(Opsional)</x-input-hint>
                <x-input-error :messages="$errors->get('pekerjaan')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label value="Alamat" />
                <x-textarea name="alamat" rows="2" placeholder="Alamat lengkap tempat tinggal" class="mt-1.5 block w-full" :error="$errors->has('alamat')">{{ $val('alamat') }}</x-textarea>
                <x-input-hint>(Opsional)</x-input-hint>
                <x-input-error :messages="$errors->get('alamat')" class="mt-1.5" />
            </div>
        </div>
    </div>
</div>

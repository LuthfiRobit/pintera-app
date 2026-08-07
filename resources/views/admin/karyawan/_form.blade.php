@php
    $karyawan = $karyawan ?? null;
    $val = fn (string $field, $default = '') => old($field, $karyawan?->$field ?? $default);
@endphp

<div class="space-y-4">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="person" class="h-[15px] w-[15px] text-gray-400" />
            Data Karyawan
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label value="Nama Lengkap *" />
                <x-text-input type="text" name="nama" value="{{ $val('nama') }}" class="mt-1.5 block w-full" />
                <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="NIK *" />
                @if ($karyawan === null)
                    <x-text-input type="text" name="nik" value="{{ $val('nik') }}" class="mt-1.5 block w-full font-mono" maxlength="16" />
                    <x-input-hint>NIK ini otomatis menjadi username &amp; password awal login karyawan.</x-input-hint>
                @else
                    <x-text-input type="text" value="{{ $karyawan->nik }}" class="mt-1.5 block w-full font-mono bg-gray-50" disabled />
                @endif
                <x-input-error :messages="$errors->get('nik')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Email" />
                <x-text-input type="email" name="email" value="{{ $val('email') }}" class="mt-1.5 block w-full" />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="No. HP" />
                <x-text-input type="text" name="no_hp" value="{{ $val('no_hp') }}" class="mt-1.5 block w-full" />
                <x-input-error :messages="$errors->get('no_hp')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Jenis Karyawan *" />
                <x-select name="jenis_karyawan_id" x-ref="jenisSelect" x-init="initSelect($refs.jenisSelect)">
                    <option value=""></option>
                    @foreach ($jenisKaryawanList as $jenis)
                        <option value="{{ $jenis->id }}" @selected((string) $val('jenis_karyawan_id') === (string) $jenis->id)>{{ $jenis->nama }}</option>
                    @endforeach
                </x-select>
                <x-input-error :messages="$errors->get('jenis_karyawan_id')" class="mt-1.5" />
            </div>
        </div>
    </div>

    @if ($karyawan === null && $canCreatePool)
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
                <x-icon name="apartment" class="h-[15px] w-[15px] text-gray-400" />
                Penempatan
            </p>

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_pool" value="1" x-model="isPool" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                Karyawan Pool (melayani semua lembaga di bawah satu yayasan)
            </label>

            <div class="mt-4" x-show="isPool">
                <x-input-label value="Yayasan *" />
                <x-select name="yayasan_id">
                    <option value=""></option>
                    @foreach ($yayasanList as $yayasan)
                        <option value="{{ $yayasan->id }}">{{ $yayasan->nama }}</option>
                    @endforeach
                </x-select>
                <x-input-error :messages="$errors->get('yayasan_id')" class="mt-1.5" />
            </div>
        </div>
    @endif
</div>

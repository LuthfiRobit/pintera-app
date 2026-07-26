@php
    $kelas = $kelas ?? null;
    $val = fn (string $field, $default = '') => old($field, $kelas?->$field ?? $default);
    $selectClass = 'w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500';
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
    <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
        <x-icon name="group" class="h-[15px] w-[15px] text-gray-400" />
        Identitas Kelas
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <x-input-label value="Tahun Ajaran" />
            <select name="tahun_ajaran_id" class="mt-1.5 {{ $selectClass }}">
                @foreach ($tahunAjaranList as $tahunAjaran)
                    <option value="{{ $tahunAjaran->id }}" @selected($val('tahun_ajaran_id') == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('tahun_ajaran_id')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label value="Nama Kelas" />
            <x-text-input type="text" name="nama" value="{{ $val('nama') }}" placeholder="6A, Kelompok A, XI IPA 2" class="mt-1.5" />
            <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label value="Tingkat (opsional)" />
            <x-text-input type="text" name="tingkat" value="{{ $val('tingkat') }}" placeholder="6, XI, kosongkan utk PAUD" class="mt-1.5" />
            <x-input-error :messages="$errors->get('tingkat')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label value="Pola Jam (opsional)" />
            <select name="pola_jam_id" class="mt-1.5 {{ $selectClass }}">
                <option value="">— Belum ditentukan —</option>
                @foreach ($polaJamList as $pola)
                    <option value="{{ $pola->id }}" @selected($val('pola_jam_id') == $pola->id)>{{ $pola->nama }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('pola_jam_id')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label value="Wali Kelas (opsional)" />
            <select name="wali_kelas_guru_id" class="mt-1.5 {{ $selectClass }}">
                <option value="">— Belum ditentukan —</option>
                @foreach ($guruList as $guru)
                    <option value="{{ $guru->id }}" @selected($val('wali_kelas_guru_id') == $guru->id)>{{ $guru->nama }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('wali_kelas_guru_id')" class="mt-1.5" />
        </div>
    </div>
</div>

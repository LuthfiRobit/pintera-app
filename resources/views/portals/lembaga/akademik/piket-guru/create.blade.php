@php
    $guruOptions = collect([['id' => '', 'nama' => '— Pilih atau cari guru —', 'subtext' => '']])
        ->concat($guruList->map(fn ($g) => [
            'id' => (string) $g->id,
            'nama' => $g->nama,
            'subtext' => $g->nip ? 'NIP: '.$g->nip : ($g->nuptk ? 'NUPTK: '.$g->nuptk : ($g->jenis_ptk ? str_replace('_', ' ', ucwords($g->jenis_ptk, '_')) : '')),
        ]))
        ->values();
@endphp

<x-app-layout>
    <div class="mx-auto max-w-lg space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Jadwal Piket Mingguan</h1>
            <a href="{{ route('admin.piket-guru.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Kembali</a>
        </div>

        <form method="POST" action="{{ route('admin.piket-guru.store') }}" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            @csrf
            <div class="relative z-20" x-data="tomSelectPegawai({
                options: @js($guruOptions),
                oldValue: @js(old('guru_id', '')),
                placeholder: '— Pilih atau cari guru —'
            })">
                <x-input-label value="Guru" />
                <div class="mt-1.5">
                    <select
                        name="guru_id"
                        x-ref="selectElement"
                        class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        autocomplete="off"
                        required
                    >
                        <option value="">— Pilih atau cari guru —</option>
                        @foreach ($guruList as $guru)
                            <option value="{{ $guru->id }}" @selected(old('guru_id') == $guru->id)>{{ $guru->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <x-input-error :messages="$errors->get('guru_id')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Hari" />
                <x-select name="hari" class="mt-1.5 w-full">
                    @foreach ([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'] as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(old('hari') == $nilai)>{{ $label }}</option>
                    @endforeach
                </x-select>
                <x-input-error :messages="$errors->get('hari')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Tahun Ajaran & Semester" />
                <x-select name="semester_id" class="mt-1.5 w-full">
                    @foreach ($semesterList->groupBy(fn ($semester) => $semester->tahunAjaran->nama) as $namaTahunAjaran => $semesterGrup)
                        <optgroup label="{{ $namaTahunAjaran }}">
                            @foreach ($semesterGrup as $semester)
                                <option value="{{ $semester->id }}" @selected(old('semester_id', $semesterAktif?->id) == $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </x-select>
                <x-input-error :messages="$errors->get('semester_id')" class="mt-1" />
            </div>
            @if ($semesterList->isEmpty())
                <p class="text-sm text-error-600">Belum ada tahun ajaran/semester untuk lembaga ini. Buat terlebih dahulu di halaman Tahun Ajaran.</p>
            @endif
            <div class="flex justify-end pt-2">
                <x-primary-button type="submit" :disabled="$semesterList->isEmpty()">Simpan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>

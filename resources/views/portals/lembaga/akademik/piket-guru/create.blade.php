<x-app-layout>
    <div class="mx-auto max-w-lg space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Jadwal Piket Mingguan</h1>
            <a href="{{ route('admin.piket-guru.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Kembali</a>
        </div>

        <form method="POST" action="{{ route('admin.piket-guru.store') }}" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            @csrf
            <div>
                <x-input-label value="Guru" />
                <select name="guru_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm">
                    @foreach ($guruList as $guru)
                        <option value="{{ $guru->id }}">{{ $guru->nama }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('guru_id')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Hari" />
                <select name="hari" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm">
                    @foreach ([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'] as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('hari')" class="mt-1" />
            </div>
            <input type="hidden" name="semester_id" value="{{ $semesterAktif?->id }}">
            @if (! $semesterAktif)
                <p class="text-sm text-error-600">Tidak ada semester aktif untuk lembaga ini. Aktifkan semester terlebih dahulu.</p>
            @endif
            <div class="flex justify-end pt-2">
                <x-primary-button type="submit" :disabled="! $semesterAktif">Simpan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>

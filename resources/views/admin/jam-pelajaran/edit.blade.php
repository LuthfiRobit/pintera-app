<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Jam Pelajaran: {{ $jamPelajaran->label }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.pola-jam.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Pola Jam</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit Jam Pelajaran</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.jam-pelajaran.update', $jamPelajaran) }}">
            @csrf
            @method('PUT')

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="schedule" class="h-[15px] w-[15px] text-gray-400" />
                    Detail Slot Jam Pelajaran
                </p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Hari" />
                        <select name="hari" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach (\App\Enums\Hari::cases() as $hari)
                                <option value="{{ $hari->value }}" @selected(old('hari', $jamPelajaran->hari->value) === $hari->value)>{{ $hari->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('hari')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label value="Urutan" />
                        <x-text-input type="number" name="urutan" min="1" placeholder="Contoh: 1" value="{{ old('urutan', $jamPelajaran->urutan) }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('urutan')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label value="Label" />
                        <x-text-input type="text" name="label" placeholder="Jam ke-1, Istirahat, ..." value="{{ old('label', $jamPelajaran->label) }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('label')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label value="Jenis Jam" />
                        <select name="is_pelajaran" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="1" @selected(old('is_pelajaran', $jamPelajaran->is_pelajaran ? '1' : '0') === '1')>Jam Belajar</option>
                            <option value="0" @selected(old('is_pelajaran', $jamPelajaran->is_pelajaran ? '1' : '0') === '0')>Non-belajar (istirahat/upacara/sholat)</option>
                        </select>
                        <x-input-error :messages="$errors->get('is_pelajaran')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label value="Jam Mulai" />
                        <x-text-input type="time" name="jam_mulai" value="{{ old('jam_mulai', $jamPelajaran->jam_mulai) }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('jam_mulai')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label value="Jam Selesai" />
                        <x-text-input type="time" name="jam_selesai" value="{{ old('jam_selesai', $jamPelajaran->jam_selesai) }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('jam_selesai')" class="mt-1.5" />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                <a href="{{ route('admin.pola-jam.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>

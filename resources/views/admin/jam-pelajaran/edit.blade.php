<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Jam Pelajaran</h1>
            <p class="text-sm text-gray-500">
                <a href="{{ route('admin.pola-jam.index') }}" class="text-gray-500 hover:text-gray-700">Pola Jam</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit Jam Pelajaran</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <form method="POST" action="{{ route('admin.jam-pelajaran.update', $jamPelajaran) }}" class="space-y-4">
                @csrf
                @method('PUT')

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
                    <x-text-input type="number" name="urutan" min="1" value="{{ old('urutan', $jamPelajaran->urutan) }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('urutan')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Label" />
                    <x-text-input type="text" name="label" placeholder="Jam ke-1, Istirahat, ..." value="{{ old('label', $jamPelajaran->label) }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('label')" class="mt-1.5" />
                </div>

                <div class="grid grid-cols-2 gap-4">
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

                <div>
                    <x-input-label value="Jenis" />
                    <select name="is_pelajaran" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="1" @selected(old('is_pelajaran', $jamPelajaran->is_pelajaran ? '1' : '0') === '1')>Jam Belajar</option>
                        <option value="0" @selected(old('is_pelajaran', $jamPelajaran->is_pelajaran ? '1' : '0') === '0')>Non-belajar (istirahat/upacara/sholat)</option>
                    </select>
                    <x-input-error :messages="$errors->get('is_pelajaran')" class="mt-1.5" />
                </div>

                <x-primary-button type="submit">Simpan</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>

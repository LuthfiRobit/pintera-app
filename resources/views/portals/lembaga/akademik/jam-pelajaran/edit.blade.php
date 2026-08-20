<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Jam Pelajaran: {{ $jamPelajaran->label }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.pola-jam.index') }}" class="font-semibold text-gray-700 transition-colors duration-200 hover:text-brand-600">Pola Jam</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit Jam Pelajaran</b>
            </p>
        </div>

        {{-- Card Form Structure --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-white px-6 py-4">
                <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
                    <x-icon name="schedule" class="h-4 w-4 text-brand-500" />
                    Detail Slot Jam Pelajaran
                </p>
                <p class="mt-0.5 text-xs text-gray-500">Sesuaikan hari, urutan, rentang waktu, serta atribut kegiatan belajar.</p>
            </div>

            <form method="POST" action="{{ route('admin.jam-pelajaran.update', $jamPelajaran) }}">
                @csrf
                @method('PUT')

                <div class="p-6 space-y-6">
                    {{-- Seksi 1: Waktu & Penjadwalan (Cognitive Time Grouping) --}}
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">Waktu &amp; Penjadwalan</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
                            <div class="sm:col-span-4">
                                <x-input-label value="Hari" />
                                <select name="hari" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                                    @foreach (\App\Enums\Hari::cases() as $hari)
                                        <option value="{{ $hari->value }}" @selected(old('hari', $jamPelajaran->hari->value) === $hari->value)>{{ $hari->label() }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('hari')" class="mt-1.5" />
                            </div>

                            <div class="sm:col-span-2">
                                <x-input-label value="Urutan" />
                                <x-text-input type="number" name="urutan" min="1" placeholder="Ex: 1" value="{{ old('urutan', $jamPelajaran->urutan) }}" class="mt-1.5 w-full transition duration-150" />
                                <x-input-error :messages="$errors->get('urutan')" class="mt-1.5" />
                            </div>

                            <div class="sm:col-span-3">
                                <x-input-label value="Jam Mulai" />
                                <x-text-input type="time" name="jam_mulai" value="{{ old('jam_mulai', $jamPelajaran->jam_mulai) }}" class="mt-1.5 w-full transition duration-150 font-mono text-sm" />
                                <x-input-error :messages="$errors->get('jam_mulai')" class="mt-1.5" />
                            </div>

                            <div class="sm:col-span-3">
                                <x-input-label value="Jam Selesai" />
                                <x-text-input type="time" name="jam_selesai" value="{{ old('jam_selesai', $jamPelajaran->jam_selesai) }}" class="mt-1.5 w-full transition duration-150 font-mono text-sm" />
                                <x-input-error :messages="$errors->get('jam_selesai')" class="mt-1.5" />
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100" />

                    {{-- Seksi 2: Identitas & Keterangan Sesi --}}
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">Identitas Sesi</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
                            <div class="sm:col-span-7">
                                <x-input-label value="Label Slot" />
                                <x-text-input type="text" name="label" placeholder="Contoh: Jam ke-1, Istirahat Pertama, Upacara..." value="{{ old('label', $jamPelajaran->label) }}" class="mt-1.5 w-full transition duration-150" />
                                <x-input-error :messages="$errors->get('label')" class="mt-1.5" />
                            </div>

                            <div class="sm:col-span-5">
                                <x-input-label value="Jenis Jam" />
                                <select name="is_pelajaran" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                                    <option value="1" @selected(old('is_pelajaran', (string) (int) $jamPelajaran->is_pelajaran) === '1')>Jam Belajar</option>
                                    <option value="0" @selected(old('is_pelajaran', (string) (int) $jamPelajaran->is_pelajaran) === '0')>Non-belajar (istirahat/upacara/sholat)</option>
                                </select>
                                <x-input-error :messages="$errors->get('is_pelajaran')" class="mt-1.5" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Card Footer Actions --}}
                <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <a href="{{ route('admin.pola-jam.index') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors duration-200 hover:bg-gray-200/50 hover:text-gray-900">
                        Batal
                    </a>
                    <x-primary-button type="submit" class="shadow-sm transition-all duration-200 active:scale-[0.98]">
                        Simpan Perubahan
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>


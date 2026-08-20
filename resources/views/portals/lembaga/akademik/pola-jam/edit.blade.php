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
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Pola Jam: {{ $polaJam->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.pola-jam.index') }}" class="font-semibold text-gray-700 transition-colors duration-200 hover:text-brand-600">Pola Jam</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.pola-jam.update', $polaJam) }}">
            @csrf
            @method('PUT')

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                {{-- Card Header --}}
                <div class="border-b border-gray-100 bg-white px-6 py-4">
                    <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
                        <x-icon name="schedule" class="h-4 w-4 text-brand-500" />
                        Identitas Pola Jam
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500">Tentukan nama identifikasi untuk pola sesi jam pembelajaran di lembaga Anda.</p>
                </div>

                {{-- Form Body (12-Column Grid) --}}
                <div class="p-6">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
                        <div class="sm:col-span-8">
                            <x-input-label value="Nama Pola Jam" />
                            <x-text-input type="text" name="nama" value="{{ old('nama', $polaJam->nama) }}" placeholder="Contoh: Kelas Rendah (1-3), Kelas Tinggi (4-6), Reguler Pagi" class="mt-1.5 w-full transition duration-150" />
                            <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                        </div>
                    </div>
                </div>

                {{-- Card Footer Action Bar --}}
                <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <a href="{{ route('admin.pola-jam.index') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors duration-200 hover:bg-gray-200/50 hover:text-gray-900">
                        Batal
                    </a>
                    <x-primary-button type="submit" class="shadow-sm transition-all duration-200 active:scale-[0.98]">
                        Simpan Perubahan
                    </x-primary-button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>


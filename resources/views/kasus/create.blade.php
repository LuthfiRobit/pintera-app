{{-- resources/views/kasus/create.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Ajukan Kasus Pendampingan</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('kasus.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Kasus Pendampingan</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Ajukan</b>
            </p>
        </div>

        <form method="POST" action="{{ route('kasus.store') }}" enctype="multipart/form-data" x-data="kasusForm()">
            @csrf

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                <div>
                    <x-input-label value="Siswa *" />
                    <select name="siswa_id" x-ref="siswaSelect" x-init="initSelect($refs.siswaSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value=""></option>
                        @foreach ($siswaList as $siswa)
                            <option value="{{ $siswa->id }}" @selected(old('siswa_id') == $siswa->id)>{{ $siswa->nama_lengkap }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('siswa_id')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Kategori Masalah *" />
                    <input type="text" name="kategori_masalah" value="{{ old('kategori_masalah') }}" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Mis. Perilaku, Akademik, Sosial">
                    <x-input-error :messages="$errors->get('kategori_masalah')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Deskripsi *" />
                    <textarea name="deskripsi" rows="5" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('deskripsi') }}</textarea>
                    <x-input-error :messages="$errors->get('deskripsi')" class="mt-1.5" />
                </div>

                @if (Auth::user()->hasRole('guru'))
                    <div>
                        <x-input-label value="Lampiran (opsional)" />
                        <input type="file" name="lampiran" class="mt-1.5 block w-full text-sm text-gray-700">
                        <x-input-error :messages="$errors->get('lampiran')" class="mt-1.5" />
                    </div>
                @endif
            </div>

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Ajukan Kasus</x-primary-button>
                <a href="{{ route('kasus.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>

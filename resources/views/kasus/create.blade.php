{{-- resources/views/kasus/create.blade.php --}}
<x-app-layout>
    @php
        $siswaOptions = $siswaList->map(function ($s) {
            return [
                'id' => $s->id,
                'nama' => $s->nama_lengkap,
                'nis' => $s->nis ?: '-',
                'nisn' => $s->nisn ?: '-',
                'display' => $s->nama_lengkap . ' (NIS: ' . ($s->nis ?: '-') . ' / NISN: ' . ($s->nisn ?: '-') . ')'
            ];
        })->values();
    @endphp

    <div class="mx-auto max-w-4xl space-y-6">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700 shadow-sm" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Top Navigation & Breadcrumbs --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-gray-500">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                    <a href="{{ route('kasus.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Kasus Pendampingan</a>
                    <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-900">Ajukan Kasus</b>
                </p>
            </div>
            <a href="{{ route('kasus.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
                <x-icon name="arrow_back" class="h-4 w-4 text-gray-500" />
                <span>Batal & Kembali</span>
            </a>
        </div>

        {{-- Hero Header --}}
        <div class="relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card md:p-8">
            <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-gradient-to-br from-brand-50 to-blue-50 opacity-60 blur-xl"></div>
            <div class="relative flex flex-col gap-4 md:flex-row md:items-center">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-600 to-indigo-700 text-white shadow-md">
                    <x-icon name="assignment_add" class="h-8 w-8" />
                </div>
                <div>
                    <h1 class="font-display text-2xl font-bold tracking-tight text-gray-900">Formulir Pengajuan Kasus</h1>
                    <p class="mt-1 text-sm text-gray-500">Laporkan kasus permasalahan atau ajukan pendampingan untuk siswa terkait secara rahasia dan aman.</p>
                </div>
            </div>
        </div>

        <div class="flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50/50 p-4 text-sm text-blue-800 shadow-sm">
            <x-icon name="info" class="h-5 w-5 shrink-0 text-blue-600 mt-0.5" />
            <div>
                <p class="font-semibold text-blue-900">Jaminan Kerahasiaan Data</p>
                <p class="text-xs text-blue-700 mt-0.5">Informasi yang Anda laporkan bersifat rahasia dan hanya dapat diakses oleh konselor penanggung jawab, orang tua/wali kontak utama, dan admin triase.</p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <form method="POST" action="{{ route('kasus.store') }}" enctype="multipart/form-data" class="p-6 md:p-8 space-y-6">
                @csrf
                
                {{-- Form Grid --}}
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="sm:col-span-2" x-data="tomSelectSiswa({ options: @js($siswaOptions), oldValue: @js(old('siswa_id', '')) })">
                        <x-input-label value="{{ Auth::user()->hasRole('orang_tua') ? 'Anak Terdaftar *' : 'Pilih Siswa *' }}" />
                        <select name="siswa_id" x-ref="selectElement" class="mt-1.5 block w-full" autocomplete="off" required>
                            <option value="">Cari nama siswa atau NIS/NISN...</option>
                        </select>
                        <x-input-error :messages="$errors->get('siswa_id')" class="mt-1.5" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label value="Kategori Masalah *" />
                        <x-text-input type="text" name="kategori_masalah" value="{{ old('kategori_masalah') }}" class="mt-1.5 block w-full" placeholder="Mis. Perilaku, Akademik, Sosial" required />
                        <x-input-error :messages="$errors->get('kategori_masalah')" class="mt-1.5" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label value="Deskripsi / Detail Permasalahan *" />
                        <x-textarea name="deskripsi" rows="5" class="mt-1.5 block w-full" placeholder="Jelaskan secara rinci apa yang terjadi, kronologi, atau mengapa siswa ini membutuhkan pendampingan..." required>{{ old('deskripsi') }}</x-textarea>
                        <x-input-error :messages="$errors->get('deskripsi')" class="mt-1.5" />
                    </div>

                    @if (Auth::user()->hasRole('guru'))
                        <div class="sm:col-span-2">
                            <x-input-label value="Lampiran Pendukung (opsional)" />
                            <div class="mt-1.5 rounded-xl border border-dashed border-gray-300 bg-gray-50/50 p-4 transition-colors hover:bg-gray-50 focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-500">
                                <input type="file" name="lampiran" class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 focus:outline-none">
                            </div>
                            <p class="mt-1.5 text-xs text-gray-500">Bisa berupa foto, dokumen, atau screenshot chat (Maksimal ukuran file disesuaikan dengan aturan server).</p>
                            <x-input-error :messages="$errors->get('lampiran')" class="mt-1.5" />
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-3 border-t border-gray-100 pt-6">
                    <x-primary-button type="submit" class="px-6 py-2.5">
                        Ajukan Kasus Pendampingan
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

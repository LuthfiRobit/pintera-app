<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-4">
        {{-- Flash Messages --}}
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">{{ $isEdit ? 'Edit Ruangan' : 'Tambah Ruangan Baru' }}</h1>
                <p class="text-xs text-gray-500 mt-0.5">Lengkapi informasi fisik ruangan, kapasitas, alokasi gedung, dan penanggung jawab.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Sarpras <span class="mx-1 text-gray-300">&rsaquo;</span> <a href="{{ route('admin.sarpras.ruangan.index') }}" class="hover:underline">Ruangan</a> <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">{{ $isEdit ? 'Edit' : 'Tambah' }}</b>
            </p>
        </div>

        <form action="{{ $isEdit ? route('admin.sarpras.ruangan.update', $ruangan) : route('admin.sarpras.ruangan.store') }}" method="POST" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-5">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Gedung / Bangunan <span class="text-rose-500">*</span></label>
                    <select name="gedung_id" required class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Pilih Gedung...</option>
                        @foreach($gedungOptions as $g)
                            <option value="{{ $g->id }}" {{ old('gedung_id', $ruangan->gedung_id) == $g->id ? 'selected' : '' }}>{{ $g->nama_gedung }} ({{ $g->kode_gedung }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('gedung_id')" class="mt-1" />
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Posisi Lantai <span class="text-rose-500">*</span></label>
                    <input type="number" name="lantai" value="{{ old('lantai', $ruangan->lantai ?? 1) }}" min="1" max="50" required class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <x-input-error :messages="$errors->get('lantai')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kode Ruangan <span class="text-rose-500">*</span></label>
                    <input type="text" name="kode_ruangan" value="{{ old('kode_ruangan', $ruangan->kode_ruangan) }}" required placeholder="Contoh: R-101, LAB-KOMP-1" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <x-input-error :messages="$errors->get('kode_ruangan')" class="mt-1" />
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Ruangan <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_ruangan" value="{{ old('nama_ruangan', $ruangan->nama_ruangan) }}" required placeholder="Contoh: Ruang Kelas 7A, Lab Komputer" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <x-input-error :messages="$errors->get('nama_ruangan')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Ruangan <span class="text-rose-500">*</span></label>
                    <select name="jenis_ruangan" required class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        @foreach($jenisOptions as $jenis)
                            <option value="{{ $jenis->value }}" {{ old('jenis_ruangan', $ruangan->jenis_ruangan?->value) == $jenis->value ? 'selected' : '' }}>{{ $jenis->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('jenis_ruangan')" class="mt-1" />
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kapasitas Siswa</label>
                    <input type="number" name="kapasitas_siswa" value="{{ old('kapasitas_siswa', $ruangan->kapasitas_siswa) }}" min="0" placeholder="Contoh: 36" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <x-input-error :messages="$errors->get('kapasitas_siswa')" class="mt-1" />
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Luas (m²)</label>
                    <input type="number" step="0.01" name="luas_m2" value="{{ old('luas_m2', $ruangan->luas_m2) }}" min="0" placeholder="Contoh: 54.00" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <x-input-error :messages="$errors->get('luas_m2')" class="mt-1" />
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Penanggung Jawab Ruangan (Guru / Staf)</label>
                <select name="penanggung_jawab_guru_id" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Belum Ditentukan</option>
                    @foreach($guruOptions as $guru)
                        <option value="{{ $guru->id }}" {{ old('penanggung_jawab_guru_id', $ruangan->penanggung_jawab_guru_id) == $guru->id ? 'selected' : '' }}>{{ $guru->nama }} ({{ $guru->nip ?? 'No NIP' }})</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('penanggung_jawab_guru_id')" class="mt-1" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 pt-1">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_shared" name="is_shared" value="1" {{ old('is_shared', $ruangan->is_shared ?? false) ? 'checked' : '' }} class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    <label for="is_shared" class="text-xs font-medium text-gray-700">Fasilitas Bersama (Shared Facility)</label>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_aktif" name="is_aktif" value="1" {{ old('is_aktif', $ruangan->is_aktif ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    <label for="is_aktif" class="text-xs font-medium text-gray-700">Status Ruangan Aktif</label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                <x-link-button variant="secondary" href="{{ route('admin.sarpras.ruangan.index') }}">
                    Batal
                </x-link-button>
                <x-primary-button type="submit">
                    {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Ruangan' }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>

<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="font-display text-xl font-bold text-gray-900">{{ $isEdit ? 'Edit Ruangan' : 'Tambah Ruangan Baru' }}</h1>
            <p class="text-xs text-gray-500 mt-1">Lengkapi informasi fisik ruangan, kapasitas, dan alokasi gedung.</p>
        </div>

        <form action="{{ $isEdit ? route('admin.sarpras.ruangan.update', $ruangan) : route('admin.sarpras.ruangan.store') }}" method="POST" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-5">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Gedung / Bangunan <span class="text-rose-500">*</span></label>
                    <select name="gedung_id" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="">Pilih Gedung...</option>
                        @foreach($gedungOptions as $g)
                            <option value="{{ $g->id }}" {{ old('gedung_id', $ruangan->gedung_id) == $g->id ? 'selected' : '' }}>{{ $g->nama_gedung }} ({{ $g->kode_gedung }})</option>
                        @endforeach
                    </select>
                    @error('gedung_id') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Posisi Lantai <span class="text-rose-500">*</span></label>
                    <input type="number" name="lantai" value="{{ old('lantai', $ruangan->lantai ?? 1) }}" min="1" max="50" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('lantai') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Kode Ruangan <span class="text-rose-500">*</span></label>
                    <input type="text" name="kode_ruangan" value="{{ old('kode_ruangan', $ruangan->kode_ruangan) }}" required placeholder="Contoh: R-101, LAB-KOMP-1" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('kode_ruangan') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Nama Ruangan <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_ruangan" value="{{ old('nama_ruangan', $ruangan->nama_ruangan) }}" required placeholder="Contoh: Ruang Kelas 7A, Lab Komputer" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('nama_ruangan') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Jenis Ruangan <span class="text-rose-500">*</span></label>
                    <select name="jenis_ruangan" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        @foreach($jenisOptions as $jenis)
                            <option value="{{ $jenis->value }}" {{ old('jenis_ruangan', $ruangan->jenis_ruangan?->value) == $jenis->value ? 'selected' : '' }}>{{ $jenis->label() }}</option>
                        @endforeach
                    </select>
                    @error('jenis_ruangan') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Kapasitas Siswa</label>
                    <input type="number" name="kapasitas_siswa" value="{{ old('kapasitas_siswa', $ruangan->kapasitas_siswa) }}" min="0" placeholder="Contoh: 36" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('kapasitas_siswa') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Luas (m²)</label>
                    <input type="number" step="0.01" name="luas_m2" value="{{ old('luas_m2', $ruangan->luas_m2) }}" min="0" placeholder="Contoh: 54.00" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('luas_m2') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Penanggung Jawab Ruangan (Guru / Staf)</label>
                <select name="penanggung_jawab_guru_id" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="">Belum Ditentukan</option>
                    @foreach($guruOptions as $guru)
                        <option value="{{ $guru->id }}" {{ old('penanggung_jawab_guru_id', $ruangan->penanggung_jawab_guru_id) == $guru->id ? 'selected' : '' }}>{{ $guru->nama }} ({{ $guru->nip ?? 'No NIP' }})</option>
                    @endforeach
                </select>
                @error('penanggung_jawab_guru_id') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_shared" name="is_shared" value="1" {{ old('is_shared', $ruangan->is_shared ?? false) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="is_shared" class="text-xs font-medium text-gray-700">Fasilitas Bersama (Shared Facility antar-lembaga)</label>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_aktif" name="is_aktif" value="1" {{ old('is_aktif', $ruangan->is_aktif ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="is_aktif" class="text-xs font-medium text-gray-700">Status Ruangan Aktif</label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('admin.sarpras.ruangan.index') }}" class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">Batal</a>
                <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-semibold text-white hover:bg-indigo-700 transition">Simpan Data</button>
            </div>
        </form>
    </div>
</x-app-layout>

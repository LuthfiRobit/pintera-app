{{-- resources/views/admin/kehadiran-sdm/create.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-0" x-data="attendanceManualForm()">
        
        {{-- Flash Notification --}}
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-xs font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-xs font-semibold text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Inline Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Catat Kehadiran Manual</h1>
                <p class="text-xs text-gray-500 mt-0.5">Input presensi pegawai secara manual oleh Admin SDM.</p>
            </div>
            <div class="flex items-center gap-2">
                <p class="hidden text-sm text-gray-500 sm:block mr-2">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> SDM <span class="mx-1 text-gray-300">&rsaquo;</span> Kehadiran <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Catat Manual</b>
                </p>
                <a href="{{ route('admin.kehadiran-sdm.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50">
                    &larr; <span>Kembali</span>
                </a>
            </div>
        </div>

        {{-- Main Form Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            <form 
                method="POST" 
                action="{{ route('admin.kehadiran-sdm.store') }}" 
                class="space-y-5"
                @submit.prevent="confirmDialog(
                    'Catat Kehadiran Manual?', 
                    'Apakah Anda yakin ingin menyimpan data presensi manual pegawai ini?', 
                    { confirmLabel: 'Ya, Simpan Kehadiran' }
                ).then(confirmed => { if (confirmed) $el.submit() })"
            >
                @csrf

                {{-- Jenis Pegawai Radio Pills --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Pilih Jenis Pegawai <span class="text-rose-500">*</span></label>
                    <div class="grid grid-cols-2 gap-3 max-w-sm">
                        <label 
                            :class="pegawaiTipe === 'guru' ? 'border-brand-300 bg-brand-50/60 text-brand-700 ring-2 ring-brand-500/20' : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100'" 
                            class="flex items-center justify-center gap-2 rounded-xl border p-3 text-xs font-bold transition cursor-pointer"
                        >
                            <input type="radio" name="pegawai_tipe" value="guru" x-model="pegawaiTipe" class="text-brand-600 focus:ring-brand-500">
                            <span>Guru</span>
                        </label>
                        <label 
                            :class="pegawaiTipe === 'karyawan' ? 'border-brand-300 bg-brand-50/60 text-brand-700 ring-2 ring-brand-500/20' : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100'" 
                            class="flex items-center justify-center gap-2 rounded-xl border p-3 text-xs font-bold transition cursor-pointer"
                        >
                            <input type="radio" name="pegawai_tipe" value="karyawan" x-model="pegawaiTipe" class="text-brand-600 focus:ring-brand-500">
                            <span>Karyawan</span>
                        </label>
                    </div>
                </div>

                {{-- Pegawai Dropdown (Guru) --}}
                <div x-show="pegawaiTipe === 'guru'">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Guru <span class="text-rose-500">*</span></label>
                    <div x-data="tomSelectPegawai({ options: @js($guruList), placeholder: 'Cari nama guru atau NIP/NUPTK...' })" class="mt-1">
                        <select 
                            name="pegawai_id" 
                            x-ref="selectElement" 
                            :disabled="pegawaiTipe !== 'guru'"
                            :required="pegawaiTipe === 'guru'"
                            class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500" 
                            autocomplete="off"
                        >
                            <option value="">Cari nama guru atau NIP/NUPTK...</option>
                        </select>
                    </div>
                </div>

                {{-- Pegawai Dropdown (Karyawan) --}}
                <div x-show="pegawaiTipe === 'karyawan'" x-cloak>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Karyawan <span class="text-rose-500">*</span></label>
                    <div x-data="tomSelectPegawai({ options: @js($karyawanList), placeholder: 'Cari nama karyawan...' })" class="mt-1">
                        <select 
                            name="pegawai_id" 
                            x-ref="selectElement" 
                            :disabled="pegawaiTipe !== 'karyawan'"
                            :required="pegawaiTipe === 'karyawan'"
                            class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500" 
                            autocomplete="off"
                        >
                            <option value="">Cari nama karyawan...</option>
                        </select>
                    </div>
                </div>

                {{-- Grid Arah & Status --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Arah Presensi <span class="text-rose-500">*</span></label>
                        <select name="arah" required class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="masuk">Masuk</option>
                            <option value="pulang">Pulang</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Status Kehadiran <span class="text-rose-500">*</span></label>
                        <select name="status" required class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($statusOptions as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Waktu & Titik Absen Grid --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Waktu Presensi <span class="text-rose-500">*</span></label>
                        <input 
                            type="datetime-local" 
                            name="waktu" 
                            required 
                            value="{{ now()->format('Y-m-d\TH:i') }}" 
                            class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 font-mono text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500"
                        >
                    </div>

                    @if ($titikAbsen->isNotEmpty())
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Titik Absen (opsional)</label>
                            <select name="attendance_point_id" class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                                <option value="">— Pilih Titik Absen —</option>
                                @foreach ($titikAbsen as $titik)
                                    <option value="{{ $titik->id }}">{{ $titik->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                {{-- Catatan Penjelasan --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Catatan Penjelasan (opsional)</label>
                    <textarea 
                        name="catatan" 
                        rows="2" 
                        placeholder="Tuliskan catatan alasan pencatatan manual..." 
                        class="w-full rounded-xl border-gray-200 bg-gray-50 p-3 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"
                    ></textarea>
                </div>

                {{-- Override Hari Libur Alert Card --}}
                <div class="rounded-xl border border-amber-100 bg-amber-50/50 p-3.5">
                    <label for="override_hari_libur" class="flex items-start gap-2 text-xs font-semibold text-amber-800 cursor-pointer">
                        <input type="checkbox" name="override_hari_libur" id="override_hari_libur" value="1" class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        <span>Tetap catat meski hari ini libur menurut kalender kerja SDM (misalnya lembur, tugas luar, atau acara khusus).</span>
                    </label>
                </div>

                {{-- Form Actions Footer --}}
                <div class="flex items-center justify-end gap-2.5 border-t border-gray-100 pt-4">
                    <a href="{{ route('admin.kehadiran-sdm.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50 active:scale-[0.98]">
                        Batal
                    </a>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-[0.98]">
                        <span>Simpan Kehadiran</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

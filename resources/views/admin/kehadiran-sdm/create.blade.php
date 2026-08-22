<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-6" x-data="attendanceManualForm()">
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">SDM &amp; Kepegawaian</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Catat Kehadiran Manual</h1>
        </div>

        <form method="POST" action="{{ route('admin.kehadiran-sdm.store') }}" class="space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            @csrf

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Pegawai</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm"><input type="radio" name="pegawai_tipe" value="guru" x-model="pegawaiTipe" checked> Guru</label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" name="pegawai_tipe" value="karyawan" x-model="pegawaiTipe"> Karyawan</label>
                </div>
            </div>

            <div x-show="pegawaiTipe === 'guru'">
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Guru</label>
                <select name="pegawai_id" x-ref="guruSelect" x-init="initSelect($refs.guruSelect)" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">Pilih guru...</option>
                    @foreach ($guruList as $guru)
                        <option value="{{ $guru->id }}">{{ $guru->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="pegawaiTipe === 'karyawan'" x-cloak>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Karyawan</label>
                <select name="pegawai_id" x-ref="karyawanSelect" x-init="initSelect($refs.karyawanSelect)" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">Pilih karyawan...</option>
                    @foreach ($karyawanList as $karyawan)
                        <option value="{{ $karyawan->id }}">{{ $karyawan->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Arah</label>
                    <select name="arah" required class="w-full rounded-lg border-gray-200 text-sm">
                        <option value="masuk">Masuk</option>
                        <option value="pulang">Pulang</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Status</label>
                    <select name="status" required class="w-full rounded-lg border-gray-200 text-sm">
                        @foreach ($statusOptions as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Waktu</label>
                <input type="datetime-local" name="waktu" required value="{{ now()->format('Y-m-d\TH:i') }}" class="w-full rounded-lg border-gray-200 text-sm">
            </div>

            @if ($titikAbsen->isNotEmpty())
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Titik Absen (opsional)</label>
                    <select name="attendance_point_id" class="w-full rounded-lg border-gray-200 text-sm">
                        <option value="">—</option>
                        @foreach ($titikAbsen as $titik)
                            <option value="{{ $titik->id }}">{{ $titik->nama }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Catatan (opsional)</label>
                <textarea name="catatan" rows="2" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                <a href="{{ route('admin.kehadiran-sdm.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">Batal</a>
                <x-primary-button type="submit">Simpan Kehadiran</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>

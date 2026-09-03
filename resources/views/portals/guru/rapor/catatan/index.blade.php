<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-5">
        @if (session('success'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Rapor Wali Kelas</h1>
                <p class="text-xs text-gray-500 mt-0.5">Isi catatan rapor tiap siswa, lalu ajukan rapor kelas untuk diverifikasi Waka Kurikulum.</p>
            </div>
            <p class="text-sm text-gray-500">
                Ruang Guru <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Rapor Wali Kelas</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <form method="GET" action="{{ route('guru.rapor.catatan.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Tahun Ajaran</label>
                    <select name="tahun_ajaran_id" onchange="this.form.submit()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        @foreach ($tahunAjaranList as $ta)
                            <option value="{{ $ta->id }}" @selected($tahunAjaranId == $ta->id)>{{ $ta->nama }} {{ $ta->status_aktif ? '(Aktif)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Semester</label>
                    <select name="semester_id" onchange="this.form.submit()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        <option value="">— Pilih Semester —</option>
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}" @selected($semesterId == $sem->id)>{{ $sem->nama }} {{ $sem->status_aktif ? '(Aktif)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Kelas</label>
                    <select name="kelas_id" onchange="this.form.submit()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        @if ($kelasList->isEmpty())
                            <option value="">— Anda Bukan Wali Kelas —</option>
                        @else
                            @foreach ($kelasList as $k)
                                <option value="{{ $k->id }}" @selected($kelas && $kelas->id == $k->id)>{{ $k->nama }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </form>
        </div>

        @if (! $kelas || ! $semester)
            <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500 shadow-card">
                <x-icon name="info" class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                <p class="font-medium text-gray-700">Pilih kelas dan semester untuk melihat daftar siswa.</p>
            </div>
        @else
            @if ($pengajuanRapor && $pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Ditolak)
                <div class="rounded-2xl border border-error-200 bg-error-50 p-5 text-sm text-error-700">
                    <p class="font-semibold">Pengajuan rapor kelas ini ditolak dan perlu direvisi.</p>
                    @if ($pengajuanRapor->catatan_revisi)
                        <p class="mt-1">Catatan: {{ $pengajuanRapor->catatan_revisi }}</p>
                    @endif
                </div>
            @endif

            @if ($pengajuanRapor && in_array($pengajuanRapor->status, [\App\Domains\Akademik\Enums\StatusPengajuanRapor::Diverifikasi, \App\Domains\Akademik\Enums\StatusPengajuanRapor::Disetujui]))
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm font-medium text-blue-700">
                    Rapor kelas ini sudah berstatus "{{ $pengajuanRapor->status->label() }}" sejak {{ $pengajuanRapor->diajukan_pada?->translatedFormat('d F Y, H:i') }}. Tidak bisa diajukan ulang dari halaman ini.
                </div>
            @endif

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3">Nama Siswa</th>
                                <th class="px-5 py-3 text-center">Status Catatan</th>
                                <th class="px-5 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($siswaList as $siswa)
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-5 py-3.5 font-medium text-gray-900">{{ $siswa->nama_lengkap }}</td>
                                    <td class="px-5 py-3.5 text-center">
                                        <x-badge :tone="$siswa->catatan_lengkap ? 'green' : 'amber'">
                                            {{ $siswa->catatan_lengkap ? 'Lengkap' : 'Belum Lengkap' }}
                                        </x-badge>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <a href="{{ route('guru.rapor.catatan.edit', ['siswa' => $siswa->id, 'semester_id' => $semester->id]) }}" class="font-semibold text-brand-600 hover:underline">
                                            Isi Catatan
                                        </a>
                                        <span class="text-gray-300 mx-1">|</span>
                                        <a href="{{ route('guru.rapor.cetak', ['siswa' => $siswa->id, 'semester_id' => $semester->id]) }}" target="_blank" class="font-semibold text-gray-500 hover:underline">
                                            Cetak PDF
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-5 py-10 text-center text-sm text-gray-500">Belum ada siswa terdaftar di kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($siswaList->isNotEmpty())
                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 px-5 py-4">
                        <form method="POST" action="{{ route('guru.rapor.pengajuan.submit') }}">
                            @csrf
                            <input type="hidden" name="kelas_id" value="{{ $kelas->id }}">
                            <input type="hidden" name="semester_id" value="{{ $semester->id }}">
                            <x-primary-button type="submit" :disabled="! $siswaList->every(fn($s) => $s->catatan_lengkap) || in_array($pengajuanRapor?->status, [\App\Domains\Akademik\Enums\StatusPengajuanRapor::Diverifikasi, \App\Domains\Akademik\Enums\StatusPengajuanRapor::Disetujui])">
                                Ajukan Rapor untuk Verifikasi
                            </x-primary-button>
                        </form>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>

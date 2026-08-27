<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-5">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Rekap Kehadiran Siswa</h1>
                <p class="text-xs text-gray-500 mt-0.5">Ringkasan agregasi kehadiran siswa untuk kelas yang Anda ampu sebagai wali kelas.</p>
            </div>
            <p class="text-sm text-gray-500">
                Ruang Guru <span class="mx-1 text-gray-300">&rsaquo;</span> Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Rekap Kehadiran</b>
            </p>
        </div>

        {{-- Filter Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <form method="GET" action="{{ route('guru.jurnal-kbm.rekap') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                {{-- Filter Tahun Ajaran --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Tahun Ajaran</label>
                    <select name="tahun_ajaran_id" onchange="this.form.submit()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        <option value="">— Semua TA —</option>
                        @foreach ($tahunAjaranList as $ta)
                            <option value="{{ $ta->id }}" @selected($tahunAjaranId == $ta->id)>
                                {{ $ta->nama }} {{ $ta->status_aktif ? '(Aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Semester --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Semester</label>
                    <select name="semester_id" onchange="this.form.submit()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        <option value="">— Semua Semester —</option>
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}" @selected($semesterId == $sem->id)>
                                {{ $sem->nama }} {{ $sem->status_aktif ? '(Aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Kelas --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Kelas</label>
                    <select name="kelas_id" onchange="this.form.submit()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        @if ($kelasList->isEmpty())
                            <option value="">— Tidak Ada Kelas —</option>
                        @else
                            @foreach ($kelasList as $k)
                                <option value="{{ $k->id }}" @selected($kelas && $kelas->id == $k->id)>
                                    {{ $k->nama }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </form>
        </div>

        {{-- Content Table / State --}}
        @if ($kelasList->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500 shadow-card">
                <x-icon name="info" class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                <p class="font-medium text-gray-700">Tidak ada kelas yang Anda ampu sebagai wali kelas pada Tahun Ajaran ini.</p>
                <p class="text-xs text-gray-400 mt-1">Coba ubah filter Tahun Ajaran di atas untuk melihat riwayat kelas lainnya.</p>
            </div>
        @elseif (! $kelas)
            <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500 shadow-card">
                <x-icon name="search_off" class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                <p class="font-medium text-gray-700">Kelas tidak ditemukan atau bukan kelas yang Anda wali-i.</p>
            </div>
        @else
            @if ($isSemuaSemester)
                <div class="mb-3 inline-flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">
                    <x-icon name="info" class="h-4 w-4" />
                    Menampilkan rekap gabungan SEMUA semester pada tahun ajaran ini.
                </div>
            @endif
            <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-card">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500">
                            <th class="px-5 py-3.5 w-36">NIS</th>
                            <th class="px-5 py-3.5">Nama Siswa</th>
                            <th class="px-5 py-3.5 text-center w-24">Hadir</th>
                            <th class="px-5 py-3.5 text-center w-24">Izin</th>
                            <th class="px-5 py-3.5 text-center w-24">Sakit</th>
                            <th class="px-5 py-3.5 text-center w-24">Alpa</th>
                            <th class="px-5 py-3.5 text-center w-24">Terlambat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rekap as $baris)
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-5 py-3.5 font-mono text-xs text-gray-500">{{ $baris['nis'] ?: '-' }}</td>
                                <td class="px-5 py-3.5 font-medium text-gray-900">{{ $baris['nama'] }}</td>
                                <td class="px-5 py-3.5 font-mono text-center font-semibold text-emerald-700">{{ $baris['hadir'] }}</td>
                                <td class="px-5 py-3.5 font-mono text-center font-semibold text-blue-700">{{ $baris['izin'] }}</td>
                                <td class="px-5 py-3.5 font-mono text-center font-semibold text-amber-700">{{ $baris['sakit'] }}</td>
                                <td class="px-5 py-3.5 font-mono text-center font-semibold text-rose-700">{{ $baris['alpa'] }}</td>
                                <td class="px-5 py-3.5 font-mono text-center font-semibold text-gray-600">{{ $baris['terlambat'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500">
                                    Belum ada data siswa aktif di kelas ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>

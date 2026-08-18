<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Rekap Kehadiran Semesteran</h1>
                <p class="text-xs text-gray-500 mt-0.5">Ringkasan kehadiran siswa untuk kelas yang Anda wali-i.</p>
            </div>
        </div>

        @if ($kelasList->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 shadow-card">
                Anda belum menjadi wali kelas untuk kelas manapun.
            </div>
        @else
            <form method="GET" class="flex items-center gap-2">
                <select name="kelas_id" onchange="this.form.submit()" class="rounded-lg border-gray-200 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($kelasList as $opsi)
                        <option value="{{ $opsi->id }}" @selected($kelas && $kelas->id === $opsi->id)>{{ $opsi->nama }}</option>
                    @endforeach
                </select>
            </form>

            @if (! $kelas)
                <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 shadow-card">
                    Kelas tidak ditemukan atau bukan kelas yang Anda wali-i.
                </div>
            @elseif (! $semester)
                <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 shadow-card">
                    Tidak ada semester aktif untuk kelas ini.
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-card">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-5 py-3">Nama Siswa</th>
                                <th class="px-5 py-3">Hadir</th>
                                <th class="px-5 py-3">Izin</th>
                                <th class="px-5 py-3">Sakit</th>
                                <th class="px-5 py-3">Alpa</th>
                                <th class="px-5 py-3">Terlambat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rekap as $baris)
                                <tr>
                                    <td class="px-5 py-3 font-medium text-gray-900">{{ $baris['nama'] }}</td>
                                    <td class="px-5 py-3 font-mono text-gray-600">{{ $baris['hadir'] }}</td>
                                    <td class="px-5 py-3 font-mono text-gray-600">{{ $baris['izin'] }}</td>
                                    <td class="px-5 py-3 font-mono text-gray-600">{{ $baris['sakit'] }}</td>
                                    <td class="px-5 py-3 font-mono text-gray-600">{{ $baris['alpa'] }}</td>
                                    <td class="px-5 py-3 font-mono text-gray-600">{{ $baris['terlambat'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-gray-500">Belum ada siswa aktif di kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>

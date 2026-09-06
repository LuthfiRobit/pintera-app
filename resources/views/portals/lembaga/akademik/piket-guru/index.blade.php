<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Jadwal Piket Mingguan</h1>
                <p class="text-xs text-gray-500 mt-0.5">Atur jadwal piket mingguan guru yang akan digenerate otomatis menjadi piket harian.</p>
            </div>
            <x-link-button href="{{ route('admin.piket-guru.create') }}">Tambah Jadwal</x-link-button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                        <th class="px-5 py-3">Guru</th>
                        <th class="px-5 py-3">Hari</th>
                        <th class="px-5 py-3">Semester</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-150 bg-white">
                    @php $namaHari = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu']; @endphp
                    @forelse ($jadwalList as $jadwal)
                        <tr>
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $jadwal->guru?->nama_lengkap ?? '-' }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $namaHari[$jadwal->hari] ?? $jadwal->hari }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $jadwal->semester?->nama ?? '-' }}</td>
                            <td class="px-5 py-3 text-right space-x-3">
                                <a href="{{ route('admin.piket-guru.edit', $jadwal) }}" class="text-brand-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.piket-guru.destroy', $jadwal) }}" class="inline" onsubmit="return confirm('Hapus jadwal piket ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-error-600 hover:underline ml-3">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-6 text-center text-gray-500">Belum ada jadwal piket mingguan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Seksi Override Manual Piket Harian --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
            <div class="border-b border-gray-150 pb-3">
                <h2 class="font-display text-base font-bold text-gray-900">Override Manual Piket Harian</h2>
                <p class="text-xs text-gray-500 mt-0.5">Tugaskan guru piket khusus untuk tanggal tertentu. Jadwal override ini bersifat permanen dan tidak akan tertimpa oleh sinkronisasi otomatis mingguan.</p>
            </div>

            <form method="POST" action="{{ route('admin.piket-harian.store') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
                @csrf
                <div>
                    <x-input-label value="Pilih Guru" />
                    <select name="guru_id" required class="mt-1.5 w-full rounded-lg border-gray-200 text-sm">
                        <option value="">-- Pilih Guru --</option>
                        @foreach ($guruList ?? [] as $guru)
                            <option value="{{ $guru->id }}">{{ $guru->nama_lengkap }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Tanggal Piket" />
                    <x-text-input type="date" name="tanggal" required class="mt-1.5 w-full text-sm" value="{{ now()->toDateString() }}" />
                </div>
                <div>
                    <x-primary-button type="submit" class="w-full justify-center">Tambah Override</x-primary-button>
                </div>
            </form>

            @if (isset($overrides) && $overrides->isNotEmpty())
                <div class="mt-4 overflow-hidden rounded-xl border border-gray-200">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 text-left text-gray-500 font-semibold border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-2.5">Tanggal</th>
                                <th class="px-4 py-2.5">Guru Piket</th>
                                <th class="px-4 py-2.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-150 bg-white">
                            @foreach ($overrides as $override)
                                <tr>
                                    <td class="px-4 py-2.5 font-medium text-gray-900">{{ \Carbon\Carbon::parse($override->tanggal)->isoFormat('dddd, D MMMM Y') }}</td>
                                    <td class="px-4 py-2.5 text-gray-700">{{ $override->guru?->nama_lengkap ?? '-' }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <form method="POST" action="{{ route('admin.piket-harian.destroy', $override) }}" class="inline" onsubmit="return confirm('Hapus override manual ini?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-error-600 hover:underline">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>


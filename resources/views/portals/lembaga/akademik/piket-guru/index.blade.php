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
    </div>
</x-app-layout>

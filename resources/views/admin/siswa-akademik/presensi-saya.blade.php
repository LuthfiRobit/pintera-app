<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Presensi Saya</h2>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <form method="GET" action="{{ route('admin.presensi-saya.index') }}" class="mb-4 flex gap-4">
            <div>
                <label for="dari_tanggal" class="block text-sm font-medium text-gray-700">Dari</label>
                <input type="date" id="dari_tanggal" name="dari_tanggal" value="{{ $dariTanggal }}" class="mt-1 block rounded-md border-gray-300">
            </div>
            <div>
                <label for="sampai_tanggal" class="block text-sm font-medium text-gray-700">Sampai</label>
                <input type="date" id="sampai_tanggal" name="sampai_tanggal" value="{{ $sampaiTanggal }}" class="mt-1 block rounded-md border-gray-300">
            </div>
            <div class="self-end">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Filter</button>
            </div>
        </form>

        <table class="min-w-full bg-white border">
            <thead>
                <tr>
                    <th class="border px-4 py-2">Tanggal</th>
                    <th class="border px-4 py-2">Mata Pelajaran</th>
                    <th class="border px-4 py-2">Status</th>
                    <th class="border px-4 py-2">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($riwayatList as $item)
                    <tr>
                        <td class="border px-4 py-2">{{ optional($item->sesiPembelajaran)->tanggal?->format('d/m/Y') ?? '-' }}</td>
                        <td class="border px-4 py-2">{{ $item->sesiPembelajaran?->mataPelajaran?->nama ?? optional(optional($item->sesiPembelajaran)->jadwalPelajaran)->mataPelajaran->nama ?? '-' }}</td>
                        <td class="border px-4 py-2">{{ $item->status?->label() ?? ucfirst((string) $item->status) }}</td>
                        <td class="border px-4 py-2">{{ $item->keterangan ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="border px-4 py-2 text-center text-gray-500">Tidak ada data presensi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">{{ $riwayatList->links() }}</div>
    </div>
</x-app-layout>

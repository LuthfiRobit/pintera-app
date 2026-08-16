<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-xl font-bold text-gray-900">Kartu Inventaris Ruangan (KIR)</h1>
                <p class="text-xs text-gray-500 mt-1">Pratinjau lembar inventaris resmi untuk ditempel pada ruangan {{ $ruangan->nama_ruangan }}.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.sarpras.kir.export', $ruangan) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                    <x-icon name="print" class="h-4 w-4" />
                    Unduh / Cetak PDF (Landscape)
                </a>
            </div>
        </div>

        {{-- KIR Sheet Container --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm space-y-6">
            <div class="text-center border-b-2 border-gray-900 pb-4">
                <h2 class="font-display text-lg font-bold text-gray-900 uppercase tracking-wide">KARTU INVENTARIS RUANGAN (KIR)</h2>
                <p class="text-xs text-gray-600 mt-0.5">{{ $ruangan->lembaga->nama ?? 'Unit Sekolah' }} — {{ $ruangan->lembaga->yayasan->nama ?? '' }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4 text-xs">
                <div>
                    <span class="text-gray-500">Kode Ruangan:</span> <strong class="text-gray-900">{{ $ruangan->kode_ruangan }}</strong><br>
                    <span class="text-gray-500">Nama Ruangan:</span> <strong class="text-gray-900">{{ $ruangan->nama_ruangan }}</strong><br>
                    <span class="text-gray-500">Jenis Ruangan:</span> <strong class="text-gray-900">{{ $ruangan->jenis_ruangan->label() }}</strong>
                </div>
                <div>
                    <span class="text-gray-500">Gedung / Lantai:</span> <strong class="text-gray-900">{{ $ruangan->gedung->nama_gedung ?? '-' }} (Lantai {{ $ruangan->lantai }})</strong><br>
                    <span class="text-gray-500">Kapasitas / Luas:</span> <strong class="text-gray-900">{{ $ruangan->kapasitas_siswa ?? '-' }} Siswa / {{ $ruangan->luas_m2 ? $ruangan->luas_m2 . ' m²' : '-' }}</strong><br>
                    <span class="text-gray-500">Penanggung Jawab:</span> <strong class="text-gray-900">{{ $ruangan->penanggungJawab->nama ?? 'Belum Ditentukan' }}</strong>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border border-gray-300">
                    <thead class="bg-gray-100 font-bold uppercase text-[10px] text-gray-700">
                        <tr>
                            <th class="border border-gray-300 px-3 py-2 text-center w-12">No</th>
                            <th class="border border-gray-300 px-3 py-2">Kode Inventaris</th>
                            <th class="border border-gray-300 px-3 py-2">Nama Barang / Merk</th>
                            <th class="border border-gray-300 px-3 py-2">Kategori</th>
                            <th class="border border-gray-300 px-3 py-2 text-center w-24">Jumlah</th>
                            <th class="border border-gray-300 px-3 py-2 text-center w-28">Kondisi</th>
                            <th class="border border-gray-300 px-3 py-2">Spesifikasi / Ket</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 font-medium">
                        @forelse($ruangan->aset as $index => $item)
                            <tr>
                                <td class="border border-gray-300 px-3 py-2 text-center">{{ $index + 1 }}</td>
                                <td class="border border-gray-300 px-3 py-2 font-mono font-bold">{{ $item->kode_inventaris }}</td>
                                <td class="border border-gray-300 px-3 py-2">
                                    {{ $item->nama_barang }}
                                    @if($item->merk) <br><small class="text-gray-400">Merk: {{ $item->merk }}</small> @endif
                                </td>
                                <td class="border border-gray-300 px-3 py-2">{{ $item->kategori->nama_kategori ?? '-' }}</td>
                                <td class="border border-gray-300 px-3 py-2 text-center">{{ $item->qty }} {{ $item->satuan }}</td>
                                <td class="border border-gray-300 px-3 py-2 text-center font-semibold">
                                    {{ $item->kondisi->label() }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2 text-gray-600">{{ $item->spesifikasi ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="border border-gray-300 px-4 py-8 text-center text-gray-400">
                                    Belum ada aset/inventaris terdaftar di ruangan ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

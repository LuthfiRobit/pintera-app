<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-700">
            <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-5 py-3">Aksi</th>
                    <th class="px-5 py-3">Kelas</th>
                    <th class="px-5 py-3">Semester</th>
                    <th class="px-5 py-3">Diajukan Pada</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($pengajuanList as $pengajuan)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('admin.rapor.persetujuan.show', $pengajuan) }}" class="font-semibold text-brand-600 hover:underline">Review & Keputusan</a>
                        </td>
                        <td class="px-5 py-3.5 font-medium text-gray-900">{{ $pengajuan->kelas->nama }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $pengajuan->semester->nama }}</td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $pengajuan->diajukan_pada?->format('d M Y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center text-gray-500">
                            <x-icon name="inbox" class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                            Tidak ada pengajuan rapor yang menunggu keputusan Anda saat ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

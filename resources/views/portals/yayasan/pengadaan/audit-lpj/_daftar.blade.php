<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-700">
            <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="px-6 py-4">Unit Sekolah</th>
                    <th scope="col" class="px-6 py-4">Pengajuan & Tanggal LPJ</th>
                    <th scope="col" class="px-6 py-4 text-right">Dana Cair Kas</th>
                    <th scope="col" class="px-6 py-4 text-right">Total Nota Riil</th>
                    <th scope="col" class="px-6 py-4 text-center">Status Audit</th>
                    <th scope="col" class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-normal">
                @forelse ($lpjList as $lpj)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 font-semibold text-gray-900">
                            {{ $lpj->proposal->lembaga->nama ?? 'Sekolah' }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $lpj->proposal->judul_pengajuan }}</div>
                            <div class="text-xs text-gray-500 font-mono mt-0.5">{{ $lpj->proposal->nomor_pengajuan }} &bull; {{ $lpj->created_at->format('d M Y') }}</div>
                        </td>
                        <td class="px-6 py-4 text-right font-medium text-gray-900">
                            Rp {{ number_format($lpj->proposal->nominal_pencairan, 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 text-right font-bold text-gray-900">
                            Rp {{ number_format($lpj->total_realisasi, 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if ($lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::Verified)
                                <x-badge tone="green">Terverifikasi</x-badge>
                            @elseif ($lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::Submitted)
                                <x-badge tone="amber">Menunggu Audit</x-badge>
                            @else
                                <x-badge tone="rose">Perlu Revisi</x-badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <x-link-button href="{{ route('admin.pengadaan.audit-lpj.show', $lpj) }}">
                                <x-icon name="visibility" class="h-4 w-4" /> Periksa Bukti
                            </x-link-button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <p class="font-medium text-gray-900">Belum ada dokumen LPJ yang masuk untuk diaudit</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($lpjList->hasPages() || $lpjList->total() > 0)
        <div class="border-t border-gray-200 px-6 py-4">
            {{ $lpjList->links('pagination.tailadmin') }}
        </div>
    @endif
</div>

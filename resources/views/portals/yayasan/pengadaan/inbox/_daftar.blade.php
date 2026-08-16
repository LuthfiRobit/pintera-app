<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-700">
            <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="px-6 py-4">Unit Sekolah</th>
                    <th scope="col" class="px-6 py-4">Pengajuan</th>
                    <th scope="col" class="px-6 py-4">Urgensi</th>
                    <th scope="col" class="px-6 py-4">Item Belanja</th>
                    <th scope="col" class="px-6 py-4 text-right">Total Anggaran</th>
                    <th scope="col" class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-normal">
                @forelse ($proposals as $p)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center rounded-lg bg-gray-100 px-2 py-1 text-xs font-bold text-gray-800">
                                {{ $p->lembaga->nama ?? 'Unit Sekolah' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $p->judul_pengajuan }}</div>
                            <div class="text-xs text-gray-500 font-mono mt-0.5">{{ $p->nomor_pengajuan }} &bull; {{ $p->created_at->format('d M Y') }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <x-badge :tone="$p->tingkat_urgensi->badgeTone()">
                                {{ $p->tingkat_urgensi->label() }}
                            </x-badge>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-600">
                            {{ $p->items->count() }} item barang
                        </td>
                        <td class="px-6 py-4 text-right font-bold text-gray-900">
                            Rp {{ number_format($p->total_estimasi, 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <x-link-button href="{{ route('admin.pengadaan.inbox.review', $p) }}">
                                <x-icon name="rate_review" class="h-4 w-4 mr-1" /> Review & Putuskan
                            </x-link-button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
                                <x-icon name="inbox" class="h-6 w-6" />
                            </div>
                            <p class="font-medium text-gray-900">Tidak ada pengajuan yang menunggu persetujuan</p>
                            <p class="text-xs text-gray-400 mt-1">Semua proposal pengadaan saat ini telah diproses.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($proposals->hasPages() || $proposals->total() > 0)
        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 px-6 py-4">
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span>Tampilkan</span>
                <select x-model="perPage" @change="gantiPerPage($event.target.value)" class="rounded-lg border-gray-200 bg-gray-50 py-1 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <span>data per halaman &bull; Total <b>{{ $proposals->total() }}</b> usulan</span>
            </div>
            <div>
                {{ $proposals->links('pagination.tailadmin') }}
            </div>
        </div>
    @endif
</div>

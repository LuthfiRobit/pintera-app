<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-700">
            <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="px-6 py-4">Unit Sekolah</th>
                    <th scope="col" class="px-6 py-4">Pengajuan</th>
                    <th scope="col" class="px-6 py-4 text-right">Anggaran Disetujui</th>
                    <th scope="col" class="px-6 py-4 text-center">Status Kas</th>
                    <th scope="col" class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-normal">
                @forelse ($proposals as $p)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 font-semibold text-gray-900">
                            {{ $p->lembaga->nama ?? 'Sekolah' }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $p->judul_pengajuan }}</div>
                            <div class="text-xs text-gray-500 font-mono mt-0.5">{{ $p->nomor_pengajuan }}</div>
                        </td>
                        <td class="px-6 py-4 text-right font-bold text-gray-900">
                            Rp {{ number_format($p->total_estimasi, 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if ($p->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Disbursed)
                                <x-badge tone="green">Sudah Dicairkan</x-badge>
                                <div class="text-[11px] text-gray-500 mt-0.5">Rp {{ number_format($p->nominal_pencairan, 0, ',', '.') }}</div>
                            @else
                                <x-badge tone="amber">Siap Dicairkan</x-badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if ($p->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Approved)
                                <button
                                    type="button"
                                    @click="selectedProposal = @js($p); $dispatch('open-modal', 'modal-pencairan-kas')"
                                    class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 shadow-sm"
                                >
                                    <x-icon name="payments" class="h-4 w-4" /> Catat Pencairan
                                </button>
                            @else
                                <span class="text-xs text-gray-400">Tercatat ({{ $p->tanggal_pencairan?->format('d/m/y') }})</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <p class="font-medium text-gray-900">Belum ada proposal yang menunggu pencairan dana</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($proposals->hasPages() || $proposals->total() > 0)
        <div class="border-t border-gray-200 px-6 py-4">
            {{ $proposals->links('pagination.tailadmin') }}
        </div>
    @endif
</div>

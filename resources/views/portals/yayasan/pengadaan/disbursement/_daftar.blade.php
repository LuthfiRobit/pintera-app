<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-700">
            <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="sticky left-0 z-10 bg-gray-50/75 px-5 py-3">Aksi</th>
                    <th scope="col" class="px-5 py-3">Unit Sekolah</th>
                    <th scope="col" class="px-5 py-3">Pengajuan</th>
                    <th scope="col" class="px-5 py-3 text-right">Anggaran Disetujui</th>
                    <th scope="col" class="px-5 py-3 text-center">Status Kas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-normal">
                @forelse ($proposals as $p)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            <x-table-actions>
                                @if ($p->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Approved)
                                    <button
                                        type="button"
                                        @click="selectedProposal = @js($p); $dispatch('open-modal', 'modal-pencairan-kas')"
                                        class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm font-semibold text-brand-600 hover:bg-brand-50 transition"
                                    >
                                        <x-icon name="payments" class="h-4 w-4 text-brand-500" />
                                        Catat Pencairan Kas
                                    </button>
                                @endif
                                <x-dropdown-link :href="route('admin.pengadaan.proposal.show', $p)">
                                    <span class="inline-flex items-center gap-2.5 text-gray-700">
                                        <x-icon name="visibility" class="h-4 w-4 text-gray-500" />
                                        Lihat Rincian Usulan
                                    </span>
                                </x-dropdown-link>
                            </x-table-actions>
                        </td>
                        <td class="px-5 py-3.5 font-semibold text-gray-900">
                            {{ $p->lembaga->nama ?? 'Sekolah' }}
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="font-medium text-gray-900">{{ $p->judul_pengajuan }}</div>
                            <div class="text-xs text-gray-500 font-mono mt-0.5">{{ $p->nomor_pengajuan }}</div>
                        </td>
                        <td class="px-5 py-3.5 text-right font-bold text-gray-900">
                            Rp {{ number_format($p->total_estimasi, 0, ',', '.') }}
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            @if ($p->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Disbursed)
                                <x-badge tone="green">Sudah Dicairkan</x-badge>
                                <div class="text-[11px] text-gray-500 mt-0.5">Rp {{ number_format($p->nominal_pencairan, 0, ',', '.') }}</div>
                            @else
                                <x-badge tone="amber">Siap Dicairkan</x-badge>
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
        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 px-5 py-4">
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

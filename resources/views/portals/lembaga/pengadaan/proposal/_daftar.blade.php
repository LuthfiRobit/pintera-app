<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-700">
            <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="sticky left-0 z-10 bg-gray-50/75 px-5 py-3">Aksi</th>
                    <th scope="col" class="px-5 py-3">Pengajuan</th>
                    <th scope="col" class="px-5 py-3">Urgensi</th>
                    <th scope="col" class="px-5 py-3">Rincian Item</th>
                    <th scope="col" class="px-5 py-3 text-right">Total Anggaran</th>
                    <th scope="col" class="px-5 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-normal">
                @forelse ($proposals as $p)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            <x-table-actions>
                                <x-dropdown-link :href="route('admin.pengadaan.proposal.show', $p)">
                                    <span class="inline-flex items-center gap-2.5">
                                        <x-icon name="visibility" class="h-4 w-4 text-gray-500" />
                                        Detail & Lacak Status
                                    </span>
                                </x-dropdown-link>

                                @if ($p->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Draft)
                                    <x-dropdown-link :href="route('admin.pengadaan.proposal.edit', $p)">
                                        <span class="inline-flex items-center gap-2.5">
                                            <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                            Edit Usulan
                                        </span>
                                    </x-dropdown-link>
                                @endif

                                @if ($p->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Disbursed && auth()->user()->can('pengadaan.lpj.submit'))
                                    <x-dropdown-link :href="route('admin.pengadaan.lpj.create', $p)">
                                        <span class="inline-flex items-center gap-2.5 text-emerald-600 font-semibold">
                                            <x-icon name="receipt_long" class="h-4 w-4 text-emerald-500" />
                                             Isi LPJ Belanja
                                        </span>
                                    </x-dropdown-link>
                                @endif

                                @if ($p->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Completed && $p->lpj && $p->lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::Verified)
                                    <x-dropdown-link :href="route('admin.pengadaan.lpj.staging-inventory', $p->lpj)">
                                        <span class="inline-flex items-center gap-2.5 text-brand-600 font-semibold">
                                            <x-icon name="inventory" class="h-4 w-4 text-brand-500" />
                                            Konversi ke Sarpras
                                        </span>
                                    </x-dropdown-link>
                                @endif
                            </x-table-actions>
                        </td>
                        <td class="px-5 py-3.5">
                            <a href="{{ route('admin.pengadaan.proposal.show', $p) }}" class="font-medium text-gray-900 hover:text-brand-600 hover:underline">
                                {{ $p->judul_pengajuan }}
                            </a>
                            <div class="text-xs text-gray-500 font-mono mt-0.5">{{ $p->nomor_pengajuan }} &bull; {{ $p->created_at->format('d M Y') }}</div>
                        </td>
                        <td class="px-5 py-3.5">
                            <x-badge :tone="$p->tingkat_urgensi->badgeTone()">
                                {{ $p->tingkat_urgensi->label() }}
                            </x-badge>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="text-sm text-gray-800">{{ $p->items->count() }} macam item</div>
                            <div class="text-xs text-gray-500 truncate max-w-xs">{{ $p->items->pluck('nama_barang')->join(', ') }}</div>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="font-semibold text-gray-900">Rp {{ number_format($p->total_estimasi, 0, ',', '.') }}</div>
                            @if ($p->nominal_pencairan)
                                <div class="text-xs text-indigo-600 font-medium">Cair: Rp {{ number_format($p->nominal_pencairan, 0, ',', '.') }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <x-badge :tone="$p->status->badgeTone()">
                                {{ $p->status->label() }}
                            </x-badge>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-500">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
                                <x-icon name="assignment" class="h-6 w-6" />
                            </div>
                            <p class="font-medium text-gray-900">Belum ada usulan pengadaan</p>
                            <p class="text-xs text-gray-400 mt-1">Gunakan tombol "Buat Pengajuan Baru" untuk mengusulkan belanja sarpras.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination Footer --}}
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
                <span>data per halaman &bull; Total <b>{{ $proposals->total() }}</b> pengajuan</span>
            </div>
            <div>
                {{ $proposals->links('pagination.tailadmin') }}
            </div>
        </div>
    @endif
</div>

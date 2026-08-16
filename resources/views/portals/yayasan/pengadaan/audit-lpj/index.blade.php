<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('success') }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Audit Laporan Pertanggungjawaban (LPJ)</h1>
                <p class="text-xs text-gray-500 mt-0.5">Verifikasi keabsahan nota belanja fisik, foto barang, dan rekonsiliasi sisa kas dari unit sekolah.</p>
            </div>
            <p class="text-sm text-gray-500">
                Yayasan <span class="mx-1 text-gray-300">&rsaquo;</span> Pengadaan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Audit LPJ</b>
            </p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-700">
                    <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th scope="col" class="px-6 py-4">Unit Sekolah</th>
                            <th scope="col" class="px-6 py-4">Proposal</th>
                            <th scope="col" class="px-6 py-4 text-right">Dana Cair</th>
                            <th scope="col" class="px-6 py-4 text-right">Realisasi Belanja</th>
                            <th scope="col" class="px-6 py-4 text-right">Selisih Kas</th>
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
                                    <div class="text-xs text-gray-500 font-mono mt-0.5">{{ $lpj->proposal->nomor_pengajuan }}</div>
                                </td>
                                <td class="px-6 py-4 text-right font-medium text-gray-700">
                                    Rp {{ number_format($lpj->proposal->nominal_pencairan, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-gray-900">
                                    Rp {{ number_format($lpj->total_realisasi, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-right font-bold {{ $lpj->selisih_dana >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    Rp {{ number_format(abs($lpj->selisih_dana), 0, ',', '.') }}
                                    <span class="text-[10px] font-medium block text-gray-400">{{ $lpj->selisih_dana >= 0 ? '(Sisa/Surplus)' : '(Kurang)' }}</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <x-badge :tone="$lpj->status_lpj->badgeTone()">
                                        {{ $lpj->status_lpj->label() }}
                                    </x-badge>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <x-link-button href="{{ route('admin.pengadaan.audit-lpj.show', $lpj) }}">
                                        <x-icon name="fact_check" class="h-4 w-4 mr-1" /> Periksa Nota
                                    </x-link-button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <p class="font-medium text-gray-900">Belum ada LPJ yang perlu diaudit</p>
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
    </div>
</x-app-layout>

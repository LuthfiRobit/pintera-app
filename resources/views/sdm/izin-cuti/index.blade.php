{{-- resources/views/sdm/izin-cuti/index.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0" x-data="riwayatIzinCutiSPA()">
        
        {{-- Flash Notification --}}
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-xs font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-xs font-semibold text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Inline Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Riwayat Izin/Cuti</h1>
                <p class="text-xs text-gray-500 mt-0.5">Daftar riwayat pengajuan izin, sakit, dan cuti pribadi Anda.</p>
            </div>
            <div class="flex items-center gap-2">
                <p class="hidden text-sm text-gray-500 sm:block mr-2">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> SDM &amp; Kepegawaian <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Riwayat Izin/Cuti</b>
                </p>
                @can('kehadiran-sdm.izin.ajukan')
                    <a href="{{ route('sdm.izin-cuti.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-[0.98]">
                        <span>+ Ajukan Baru</span>
                    </a>
                @endcan
            </div>
        </div>

        {{-- Compact 3-Column Statistic Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Pengajuan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="items.length"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Akumulasi</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Menunggu Approval</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="countPending"></p>
                    </div>
                </div>
                <span class="text-[11px] font-semibold text-amber-600">Dalam Proses</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Disetujui</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="countApproved"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-emerald-600 font-semibold">Selesai</span>
            </div>
        </div>

        {{-- Filter Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                {{-- Search Input --}}
                <div class="lg:col-span-5">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Pengajuan</label>
                    <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input x-model="searchQuery" type="text" placeholder="Cari berdasarkan kategori / tanggal..." class="w-full border-0 bg-transparent p-0 text-xs text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>

                {{-- Pill Tabs Filters --}}
                <div class="lg:col-span-7 flex items-center justify-start lg:justify-end gap-2 overflow-x-auto scrollbar-none pb-1 sm:pb-0">
                    <button 
                        @click="activeFilter = 'semua'" 
                        type="button" 
                        :class="activeFilter === 'semua' ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Semua Status</span>
                        <span :class="activeFilter === 'semua' ? 'bg-brand-100 text-brand-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="items.length"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'pending'" 
                        type="button" 
                        :class="activeFilter === 'pending' ? 'bg-amber-50 font-semibold text-amber-700 border-amber-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Menunggu Approval</span>
                        <span :class="activeFilter === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countPending"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'approved'" 
                        type="button" 
                        :class="activeFilter === 'approved' ? 'bg-emerald-50 font-semibold text-emerald-700 border-emerald-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Disetujui</span>
                        <span :class="activeFilter === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countApproved"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Table Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            {{-- Table Header Bar --}}
            <div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <h2 class="font-display text-sm font-bold text-gray-900">Daftar Permohonan Saya</h2>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600" x-text="filteredItems.length + ' Entri'"></span>
                </div>
            </div>

            {{-- Table Data --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3 w-28 text-center">Aksi</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Tanggal</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Langkah Saat Ini</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($riwayat as $item)
                            @php 
                                $ar = $item->approvalRequest; 
                                $statusValue = $ar?->status->value ?? '—';
                                $canCancel = $ar && in_array($statusValue, ['pending', 'in_review'], true);
                            @endphp
                            <tr class="transition hover:bg-gray-50">
                                <td class="px-5 py-3.5 text-center">
                                    @if ($canCancel)
                                        <form 
                                            method="POST" 
                                            action="{{ route('sdm.izin-cuti.destroy', $item) }}" 
                                            x-data 
                                            @submit.prevent="confirmDialog(
                                                'Batalkan Pengajuan Izin/Cuti?', 
                                                'Apakah Anda yakin ingin membatalkan pengajuan ini?', 
                                                { confirmLabel: 'Ya, Batalkan Pengajuan' }
                                            ).then(confirmed => { if (confirmed) $el.submit() })"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <div class="relative group inline-block">
                                                <button 
                                                    type="submit" 
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-rose-200 bg-rose-50 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition shadow-2xs"
                                                >
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    <span>Batalkan</span>
                                                </button>
                                                <span class="absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 transition-opacity duration-200 group-hover:opacity-100 pointer-events-none shadow-md">
                                                    Batalkan Pengajuan
                                                </span>
                                            </div>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">
                                    {{ $item->kategori->label() }}
                                </td>
                                <td class="px-5 py-3.5 font-mono text-xs font-bold text-gray-800">
                                    {{ $item->tanggal_mulai->format('d M Y') }} — {{ $item->tanggal_selesai->format('d M Y') }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center rounded-full bg-{{ $ar?->status->badgeTone() ?? 'gray' }}-100 px-2.5 py-1 text-xs font-semibold text-{{ $ar?->status->badgeTone() ?? 'gray' }}-800">
                                        {{ $ar?->status->label() ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600">
                                    {{ $ar?->currentStep?->step_name ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-700">Belum ada pengajuan izin/cuti.</p>
                                    <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">Klik tombol "+ Ajukan Baru" di atas untuk mengajukan permohonan izin atau cuti.</p>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Inline SPA Script --}}
    <script>
        function riwayatIzinCutiSPA() {
            return {
                items: @js($riwayat->map(function ($item) {
                    $ar = $item->approvalRequest;
                    return [
                        'id' => $item->id,
                        'kategori' => $item->kategori->label(),
                        'status' => $ar?->status->value ?? 'none',
                        'periode' => $item->tanggal_mulai->format('d M Y') . ' — ' . $item->tanggal_selesai->format('d M Y'),
                    ];
                })->values()->all()),
                searchQuery: '',
                activeFilter: 'semua',

                get filteredItems() {
                    return this.items.filter(i => {
                        const q = this.searchQuery.toLowerCase();
                        const matchSearch = i.kategori.toLowerCase().includes(q) || i.periode.toLowerCase().includes(q);
                        if (this.activeFilter === 'semua') return matchSearch;
                        if (this.activeFilter === 'pending') return matchSearch && ['pending', 'in_review'].includes(i.status);
                        if (this.activeFilter === 'approved') return matchSearch && i.status === 'approved';
                        return matchSearch;
                    });
                },

                get countPending() {
                    return this.items.filter(i => ['pending', 'in_review'].includes(i.status)).length;
                },

                get countApproved() {
                    return this.items.filter(i => i.status === 'approved').length;
                }
            }
        }
    </script>
</x-app-layout>

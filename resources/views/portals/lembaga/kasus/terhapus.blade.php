{{-- resources/views/admin/kasus/terhapus.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="{ search: @js($search ?? ''), perPage: @js((string) ($perPage ?? 20)) }">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Kasus Terhapus</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kasus yang sudah dihapus (soft-delete) dan dapat dipulihkan kembali.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Kasus Terhapus</b>
            </p>
        </div>

        {{-- Statistic Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600">
                        <x-icon name="delete" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="font-display text-xs font-semibold uppercase tracking-wider text-gray-500">Total Kasus Terhapus</p>
                        <p class="font-display text-2xl font-bold text-gray-900 leading-tight">{{ number_format($totalTerhapus ?? 0) }}</p>
                    </div>
                </div>
                <span class="text-xs font-medium text-gray-400">Sepanjang Waktu</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-error-50 text-error-600">
                        <x-icon name="calendar_month" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="font-display text-xs font-semibold uppercase tracking-wider text-error-600">Baru Dihapus</p>
                        <p class="font-display text-2xl font-bold text-gray-900 leading-tight">{{ number_format($dihapusBulanIni ?? 0) }}</p>
                    </div>
                </div>
                <span class="text-xs font-bold text-error-600">Bulan Ini</span>
            </div>
        </div>

        {{-- Filter & Search Form --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Pencarian Data
                </p>
            </div>

            <form method="GET" action="{{ route('admin.kasus.terhapus') }}" x-ref="filterForm" class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                <input type="hidden" name="per_page" x-bind:value="perPage">
                <div class="lg:col-span-12 max-w-lg">
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Siswa atau Kategori Masalah</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 transition-colors focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-500">
                        <x-icon name="search" class="h-4 w-4 shrink-0 text-gray-400" />
                        <input x-model="search" type="text" name="search" id="search" placeholder="Ketik nama atau kategori untuk mencari..." 
                               class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                        
                        <button type="button" x-show="search.length > 0" x-on:click="search = ''; $refs.filterForm.submit()" style="display: none;" class="text-gray-400 hover:text-gray-600">
                            <x-icon name="close" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Table Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
            <div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Kasus Terhapus</p>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ $kasusList->total() }} Data</span>
                </div>
                <div class="flex items-center gap-2">
                    <label for="per_page" class="text-xs font-medium text-gray-500">Tampilkan:</label>
                    <select
                        id="per_page"
                        x-model="perPage"
                        @change="$refs.filterForm.submit()"
                        class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500"
                    >
                        @foreach ([10, 20, 25, 50] as $n)
                            <option value="{{ $n }}">{{ $n }} / hal</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/75 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="sticky left-0 z-10 bg-gray-50/75 px-5 py-3 w-32 border-r border-gray-100">Aksi</th>
                            <th class="px-5 py-3">Siswa Terkait</th>
                            <th class="px-5 py-3">Kategori Masalah</th>
                            <th class="px-5 py-3 text-right">Dihapus Pada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($kasusList as $kasus)
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3.5 border-r border-gray-100">
                                    @can('kasus.pulihkan')
                                        <x-table-actions>
                                            <form method="POST" action="{{ route('admin.kasus.restore', $kasus) }}" x-data @submit.prevent="confirmDialog('Pulihkan Kasus?', 'Apakah Anda yakin ingin memulihkan kasus ini ke daftar aktif?', { confirmLabel: 'Ya, Pulihkan' }).then(confirmed => { if (confirmed) $el.submit() })">
                                                @csrf
                                                <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                                    <x-icon name="settings_backup_restore" class="h-4 w-4 text-brand-500" />
                                                    <span class="font-medium text-brand-600">Pulihkan Data</span>
                                                </button>
                                            </form>
                                        </x-table-actions>
                                    @endcan
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-bold text-gray-900">{{ $kasus->siswa?->nama_lengkap ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                                        {{ $kasus->kategori_masalah }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <p class="font-bold text-gray-900">{{ $kasus->deleted_at->diffForHumans() }}</p>
                                    <p class="text-gray-500 text-[11px] mt-0.5">{{ $kasus->deleted_at->format('d M Y, H:i') }}</p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-16 text-center text-gray-400">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-50 text-gray-400">
                                        <x-icon name="fact_check" class="h-7 w-7" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-700">Tidak Ada Kasus Terhapus</p>
                                    <p class="mx-auto mt-1 max-w-sm text-xs text-gray-400">Tidak ada riwayat kasus pendampingan yang dihapus saat ini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($kasusList->hasPages())
                <div class="border-t border-gray-200 px-5 py-4">
                    {{ $kasusList->links('pagination.tailadmin') }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

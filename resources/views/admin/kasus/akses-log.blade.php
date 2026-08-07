{{-- resources/views/admin/kasus/akses-log.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="{ search: @js($search ?? ''), perPage: @js((string) ($perPage ?? 20)) }">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Log Akses Klinis</h1>
                <p class="text-xs text-gray-500 mt-0.5">Riwayat siapa yang membuka catatan klinis kasus pendampingan, dan kapan.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Log Akses Klinis</b>
            </p>
        </div>

        {{-- Statistic Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600">
                        <x-icon name="history" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="font-display text-xs font-semibold uppercase tracking-wider text-gray-500">Total Akses Riwayat</p>
                        <p class="font-display text-2xl font-bold text-gray-900 leading-tight">{{ number_format($totalAkses ?? 0) }}</p>
                    </div>
                </div>
                <span class="text-xs font-medium text-gray-400">Sepanjang Waktu</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <x-icon name="bolt" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="font-display text-xs font-semibold uppercase tracking-wider text-brand-600">Akses Hari Ini</p>
                        <p class="font-display text-2xl font-bold text-gray-900 leading-tight">{{ number_format($aksesHariIni ?? 0) }}</p>
                    </div>
                </div>
                <span class="text-xs font-bold text-brand-600">Terbaru</span>
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

            <form method="GET" action="{{ route('admin.kasus.log-akses') }}" x-ref="filterForm" class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                <input type="hidden" name="per_page" x-bind:value="perPage">
                <div class="lg:col-span-12 max-w-lg">
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Pengakses atau Siswa</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 transition-colors focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-500">
                        <x-icon name="search" class="h-4 w-4 shrink-0 text-gray-400" />
                        <input x-model="search" type="text" name="search" id="search" placeholder="Ketik nama untuk mencari..." 
                               class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                        
                        <button type="button" x-show="search.length > 0" x-on:click="search = ''; $refs.filterForm.submit()" style="display: none;" class="text-gray-400 hover:text-gray-600">
                            <x-icon name="close" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Table Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Riwayat</p>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ $logs->total() }} Data</span>
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
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-4">Pengakses</th>
                            <th class="px-5 py-4">Siswa Terkait</th>
                            <th class="px-5 py-4">Kategori Masalah</th>
                            <th class="px-5 py-4 text-right">Waktu Akses</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($logs as $log)
                            <tr class="transition hover:bg-gray-50">
                                <td class="px-5 py-4">
                                    <p class="font-bold text-gray-900">{{ $causers[$log->causer_id]?->name ?? 'Pengguna tidak diketahui' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                                        {{ $log->subject?->siswa?->nama_lengkap ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-gray-600">
                                    {{ $log->subject?->kategori_masalah ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <p class="font-bold text-gray-900">{{ $log->created_at->diffForHumans() }}</p>
                                    <p class="text-gray-500 text-[11px] mt-0.5">{{ $log->created_at->format('d M Y, H:i') }}</p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-16 text-center text-gray-400">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-50 text-gray-400">
                                        <x-icon name="search_off" class="h-7 w-7" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-700">Belum Ada Akses Tercatat</p>
                                    <p class="mx-auto mt-1 max-w-sm text-xs text-gray-400">Tidak ada riwayat akses klinis yang sesuai dengan pencarian atau memang belum ada catatan tersedia.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($logs->hasPages())
                <div class="border-t border-gray-200 px-5 py-4">
                    {{ $logs->links('pagination.tailadmin') }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

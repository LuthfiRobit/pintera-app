<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Siswa</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Siswa</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            {{-- Header --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Siswa</p>
                <div class="flex flex-wrap items-center gap-2">
                    @can('siswa.spmb-daftar')
                        <x-link-button variant="ghost" href="{{ route('admin.siswa.spmb-daftar.index') }}">
                            <x-icon name="check_circle" class="h-4 w-4" /> Daftarkan dari SPMB
                        </x-link-button>
                    @endcan
                    @can('siswa.import')
                        <x-link-button variant="ghost" href="{{ route('admin.siswa.import.index') }}">
                            <x-icon name="upload" class="h-4 w-4" /> Import Siswa
                        </x-link-button>
                    @endcan
                    <x-link-button href="{{ route('admin.siswa.create') }}">
                        <span class="text-base leading-none">+</span> Tambah Siswa
                    </x-link-button>
                </div>
            </div>

            {{-- Filter Bar --}}
            <div
                class="border-b border-gray-100 bg-gray-50/60 px-5 py-3"
                x-data="{
                    search: '{{ request('search') }}',
                    kelasId: '{{ request('kelas_id') }}',
                    status: '{{ request('status') }}',
                    perPage: '{{ $perPage }}',
                    searchTimer: null,

                    submitSearch() {
                        clearTimeout(this.searchTimer);
                        this.searchTimer = setTimeout(() => this.$refs.filterForm.submit(), 500);
                    },
                    submitNow() {
                        clearTimeout(this.searchTimer);
                        this.$refs.filterForm.submit();
                    }
                }"
            >
                <form method="GET" action="{{ route('admin.siswa.index') }}" x-ref="filterForm" class="flex flex-wrap items-center gap-2">
                    {{-- Search input --}}
                    <div class="relative min-w-48 flex-1">
                        <span class="absolute inset-y-0 left-2.5 flex items-center text-gray-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                            </svg>
                        </span>
                        <input
                            type="text"
                            name="search"
                            x-model="search"
                            @input="submitSearch()"
                            placeholder="Cari nama atau NIS…"
                            class="w-full rounded-lg border-gray-200 py-1.5 pl-8 pr-3 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        >
                    </div>

                    {{-- Filter Kelas --}}
                    <select
                        name="kelas_id"
                        x-model="kelasId"
                        @change="submitNow()"
                        class="rounded-lg border-gray-200 py-1.5 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    >
                        <option value="">Semua Kelas</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" @selected(request('kelas_id') == $kelas->id)>{{ $kelas->nama }}</option>
                        @endforeach
                    </select>

                    {{-- Filter Status --}}
                    <select
                        name="status"
                        x-model="status"
                        @change="submitNow()"
                        class="rounded-lg border-gray-200 py-1.5 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    >
                        <option value="">Semua Status</option>
                        @foreach ($statusList as $s)
                            <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>

                    {{-- Per page --}}
                    <select
                        name="per_page"
                        x-model="perPage"
                        @change="submitNow()"
                        class="rounded-lg border-gray-200 py-1.5 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    >
                        <option value="10">10 / hal</option>
                        <option value="20" @selected($perPage == 20)>20 / hal</option>
                        <option value="25">25 / hal</option>
                        <option value="50">50 / hal</option>
                    </select>

                    {{-- Reset link --}}
                    @if (request()->anyFilled(['search', 'kelas_id', 'status']))
                        <a href="{{ route('admin.siswa.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">
                            Reset
                        </a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                            <th class="px-5 py-3">NIS</th>
                            <th class="px-5 py-3">Nama</th>
                            <th class="px-5 py-3">Kelas</th>
                            <th class="px-5 py-3">Asal Data</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($siswaList as $siswa)
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <x-table-actions>
                                        <x-dropdown-link :href="route('admin.siswa.edit', $siswa)">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                Edit Siswa
                                            </span>
                                        </x-dropdown-link>
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-gray-500">{{ $siswa->nis }}</td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $siswa->nama_lengkap }}</td>
                                <td class="px-5 py-3.5 text-gray-600">
                                    @if ($siswa->kelas)
                                        {{ $siswa->kelas->nama }}
                                    @else
                                        <x-badge tone="amber">Belum ditempatkan</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <x-badge tone="slate">{{ $siswa->sumber_data->label() }}</x-badge>
                                </td>
                                <td class="px-5 py-3.5">
                                    <x-badge :tone="$siswa->status === \App\Enums\StatusSiswa::Aktif ? 'green' : 'slate'">{{ $siswa->status->label() }}</x-badge>
                                </td>
                            </tr>
                        @endforeach

                        @if ($siswaList->isEmpty())
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-gray-500">
                                    @if (request()->anyFilled(['search', 'kelas_id', 'status']))
                                        Tidak ada siswa yang cocok dengan filter.
                                    @else
                                        Belum ada siswa.
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- Pagination Footer --}}
            @if ($siswaList->hasPages())
                <div class="flex items-center justify-between border-t border-gray-100 px-5 py-3">
                    <p class="text-sm text-gray-500">
                        Menampilkan {{ $siswaList->firstItem() }}–{{ $siswaList->lastItem() }} dari {{ $siswaList->total() }} siswa
                    </p>
                    {{ $siswaList->links() }}
                </div>
            @else
                <div class="border-t border-gray-100 px-5 py-3">
                    <p class="text-sm text-gray-500">
                        Menampilkan {{ $siswaList->count() }} dari {{ $siswaList->total() }} siswa
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

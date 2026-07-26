<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Mata Pelajaran</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola daftar mata pelajaran dan aspek perkembangan untuk kurikulum lembaga.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Mata Pelajaran</b>
            </p>
        </div>

        {{-- Filter Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter Data
                </p>
                <x-link-button href="{{ route('admin.mata-pelajaran.create') }}">
                    <span class="text-base leading-none">+</span> Tambah Mata Pelajaran
                </x-link-button>
            </div>

            <form method="GET" action="{{ route('admin.mata-pelajaran.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Search --}}
                <div>
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input
                            type="text" name="search" id="search"
                            value="{{ request('search') }}"
                            placeholder="Nama mata pelajaran"
                            @input.debounce.500ms="$el.form.submit()"
                            class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                        >
                    </div>
                </div>

                {{-- Filter Tipe --}}
                <div>
                    <label for="tipe" class="mb-1.5 block text-xs font-semibold text-gray-500">Tipe</label>
                    <select name="tipe" id="tipe" @change="$el.form.submit()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Tipe</option>
                        @foreach ($tipeList as $t)
                            <option value="{{ $t->value }}" @selected(request('tipe') === $t->value)>{{ $t->label() }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Reset --}}
                <div class="flex items-end">
                    @if (request()->anyFilled(['search', 'tipe']))
                        <a href="{{ route('admin.mata-pelajaran.index') }}" class="flex h-[42px] w-full items-center justify-center rounded-lg border border-gray-200 px-3 text-sm text-gray-500 transition hover:bg-gray-50">
                            Reset Filter
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Table Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Mata Pelajaran</p>
                <form method="GET" action="{{ route('admin.mata-pelajaran.index') }}">
                    @foreach (request()->except('per_page', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <label for="per_page" class="shrink-0">Tampilkan</label>
                        <select name="per_page" id="per_page" @change="$el.form.submit()" class="rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="10" @selected($perPage == 10)>10</option>
                            <option value="20" @selected($perPage == 20)>20</option>
                            <option value="25" @selected($perPage == 25)>25</option>
                            <option value="50" @selected($perPage == 50)>50</option>
                        </select>
                        <span class="shrink-0">per halaman</span>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                            <th class="px-5 py-3">Nama</th>
                            <th class="px-5 py-3">Tipe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($mataPelajaranList as $mapel)
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <x-table-actions>
                                        <x-dropdown-link :href="route('admin.mata-pelajaran.edit', $mapel)">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                Edit Mata Pelajaran
                                            </span>
                                        </x-dropdown-link>
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $mapel->nama }}</td>
                                <td class="px-5 py-3.5">
                                    <x-badge :tone="$mapel->tipe === \App\Enums\TipeMataPelajaran::Mapel ? 'brass' : 'blue'">
                                        {{ $mapel->tipe->label() }}
                                    </x-badge>
                                </td>
                            </tr>
                        @endforeach

                        @if ($mataPelajaranList->isEmpty())
                            <tr>
                                <td colspan="3" class="px-5 py-10 text-center text-gray-500">
                                    @if (request()->anyFilled(['search', 'tipe']))
                                        Tidak ada mata pelajaran yang cocok dengan filter ini.
                                    @else
                                        Belum ada mata pelajaran yang didaftarkan.
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-5 py-4">
                {{ $mataPelajaranList->links('pagination.tailadmin') }}
            </div>
        </div>
    </div>
</x-app-layout>


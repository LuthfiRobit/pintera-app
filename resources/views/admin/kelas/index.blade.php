<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Kelas</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Kelas</b>
            </p>
        </div>

        {{-- Filter Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter Data
                </p>
                <x-link-button href="{{ route('admin.kelas.create') }}">
                    <span class="text-base leading-none">+</span> Tambah Kelas
                </x-link-button>
            </div>

            <form method="GET" action="{{ route('admin.kelas.index') }}" class="flex flex-wrap items-end gap-3">
                {{-- Search --}}
                <div class="min-w-48 flex-1">
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input
                            type="text" name="search" id="search"
                            value="{{ request('search') }}"
                            placeholder="Nama kelas"
                            @input.debounce.500ms="$el.form.submit()"
                            class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                        >
                    </div>
                </div>

                {{-- Filter Tahun Ajaran --}}
                <div class="shrink-0">
                    <label for="tahun_ajaran_id" class="mb-1.5 block text-xs font-semibold text-gray-500">Tahun Ajaran</label>
                    <select name="tahun_ajaran_id" id="tahun_ajaran_id" @change="$el.form.submit()" class="rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Tahun Ajaran</option>
                        @foreach ($tahunAjaranList as $ta)
                            <option value="{{ $ta->id }}" @selected(request('tahun_ajaran_id') == $ta->id)>
                                {{ $ta->nama }}{{ $ta->status_aktif ? ' (Aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Reset --}}
                @if (request()->anyFilled(['search', 'tahun_ajaran_id']))
                    <div class="shrink-0">
                        <a href="{{ route('admin.kelas.index') }}" class="flex h-[42px] items-center justify-center rounded-lg border border-gray-200 px-3 text-sm text-gray-500 transition hover:bg-gray-50">
                            Reset
                        </a>
                    </div>
                @endif
            </form>
        </div>

        {{-- Table Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Kelas</p>
                <form method="GET" action="{{ route('admin.kelas.index') }}">
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
                            <th class="px-5 py-3">Nama Kelas</th>
                            <th class="px-5 py-3">Tahun Ajaran</th>
                            <th class="px-5 py-3">Wali Kelas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($kelasList as $kelas)
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <x-table-actions>
                                        <x-dropdown-link :href="route('admin.kelas.edit', $kelas)">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                Edit Kelas
                                            </span>
                                        </x-dropdown-link>
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">
                                    {{ $kelas->nama }}
                                    @if ($kelas->tingkat)
                                        <span class="ml-1 text-xs font-normal text-gray-400">(Tingkat {{ $kelas->tingkat }})</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-gray-600">
                                    {{ $kelas->tahunAjaran->nama }}
                                    @if ($kelas->tahunAjaran->status_aktif)
                                        <x-badge tone="green">Aktif</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-gray-600">
                                    @if ($kelas->waliKelas)
                                        {{ $kelas->waliKelas->nama }}
                                    @else
                                        <x-badge tone="slate">Belum ditentukan</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        @if ($kelasList->isEmpty())
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-gray-500">
                                    @if (request()->anyFilled(['search', 'tahun_ajaran_id']))
                                        Tidak ada kelas yang cocok dengan filter ini.
                                    @else
                                        Belum ada kelas.
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-5 py-4">
                {{ $kelasList->links('pagination.tailadmin') }}
            </div>
        </div>
    </div>
</x-app-layout>

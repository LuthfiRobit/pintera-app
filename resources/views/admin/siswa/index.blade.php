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

        {{-- Filter Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter Data
                </p>
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

            <form method="GET" action="{{ route('admin.siswa.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                {{-- Search --}}
                <div class="lg:col-span-2">
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input
                            type="text" name="search" id="search"
                            value="{{ request('search') }}"
                            placeholder="Nama atau NIS"
                            @input.debounce.500ms="$el.form.submit()"
                            class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                        >
                    </div>
                </div>

                {{-- Filter Kelas --}}
                <div>
                    <label for="kelas_id" class="mb-1.5 block text-xs font-semibold text-gray-500">Kelas</label>
                    <select name="kelas_id" id="kelas_id" @change="$el.form.submit()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Kelas</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" @selected(request('kelas_id') == $kelas->id)>{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Status --}}
                <div>
                    <label for="status" class="mb-1.5 block text-xs font-semibold text-gray-500">Status</label>
                    <select name="status" id="status" @change="$el.form.submit()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Status</option>
                        @foreach ($statusList as $s)
                            <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Per Page + Reset --}}
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <label for="per_page" class="mb-1.5 block text-xs font-semibold text-gray-500">Per Halaman</label>
                        <select name="per_page" id="per_page" @change="$el.form.submit()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="10" @selected($perPage == 10)>10</option>
                            <option value="20" @selected($perPage == 20)>20</option>
                            <option value="25" @selected($perPage == 25)>25</option>
                            <option value="50" @selected($perPage == 50)>50</option>
                        </select>
                    </div>
                    @if (request()->anyFilled(['search', 'kelas_id', 'status']))
                        <a href="{{ route('admin.siswa.index') }}" class="flex h-[42px] items-center justify-center rounded-lg border border-gray-200 px-3 text-sm text-gray-500 transition hover:bg-gray-50">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Table Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Siswa</p>
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
                                        Tidak ada siswa yang cocok dengan filter ini.
                                    @else
                                        Belum ada siswa.
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-5 py-4">
                {{ $siswaList->links('pagination.tailadmin') }}
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Siswa</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola data induk siswa, penempatan rombel kelas, serta pemantauan status aktif siswa.</p>
            </div>
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
                    @if (($siswaTanpaAkunCount ?? 0) > 0 && auth()->user()->can('siswa.edit'))
                        <x-tooltip text="Terdapat {{ $siswaTanpaAkunCount }} siswa aktif tanpa akun login. Klik untuk membangkitkan username login baru dan password default (berdasarkan NIS) secara massal.">
                            <form
                                method="POST"
                                action="{{ route('admin.siswa.generate-akun-massal') }}"
                                x-data
                                @submit.prevent="confirmDialog('Generate Akun Massal?', 'Bangkitkan akun login baru secara massal untuk {{ $siswaTanpaAkunCount }} siswa aktif yang belum memiliki akun?', { confirmLabel: 'Ya, Generate Massal' }).then(confirmed => { if (confirmed) $el.submit() })"
                                class="inline-block"
                            >
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition active:scale-[0.98] hover:bg-gray-50"
                                >
                                    <x-icon name="person_add" class="h-4 w-4" />
                                    <span>Generate Akun Massal ({{ $siswaTanpaAkunCount }})</span>
                                </button>
                            </form>
                        </x-tooltip>
                    @endif
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

            <form method="GET" action="{{ route('admin.siswa.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Search --}}
                <div>
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

                {{-- Reset --}}
                <div class="flex items-end">
                    @if (request()->anyFilled(['search', 'kelas_id', 'status']))
                        <a href="{{ route('admin.siswa.index') }}" class="flex h-[42px] w-full items-center justify-center rounded-lg border border-gray-200 px-3 text-sm text-gray-500 transition hover:bg-gray-50">
                            Reset Filter
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Table Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            {{-- Table header with per-page selector --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Siswa</p>
                <form method="GET" action="{{ route('admin.siswa.index') }}" id="per-page-form">
                    @foreach (request()->except('per_page', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <label for="per_page" class="shrink-0">Tampilkan</label>
                        <select
                            name="per_page" id="per_page"
                            @change="$el.form.submit()"
                            class="rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500"
                        >
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
                                        @foreach (\App\Enums\StatusSiswa::cases() as $statusOption)
                                            @if ($statusOption !== $siswa->status)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.siswa.update-status', $siswa) }}"
                                                    x-data
                                                    @submit.prevent="confirmDialog('Ubah Status Siswa?', @js('Ubah status \"' . $siswa->nama_lengkap . '\" menjadi \"' . $statusOption->label() . '\"?'), { confirmLabel: 'Ya, Ubah' }).then(confirmed => { if (confirmed) $el.submit() })"
                                                >
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ $statusOption->value }}">
                                                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                                        <x-icon name="autorenew" class="h-4 w-4 text-gray-500" />
                                                        Jadikan {{ $statusOption->label() }}
                                                    </button>
                                                </form>
                                            @endif
                                        @endforeach
                                        @if ($siswa->user_id)
                                            <form
                                                method="POST"
                                                action="{{ route('admin.siswa.reset-password', $siswa) }}"
                                                x-data
                                                @submit.prevent="confirmDialog('Reset Password Siswa?', @js('Reset password \"' . $siswa->nama_lengkap . '\" kembali ke NIS (' . $siswa->nis . ')? Siswa wajib menggantinya saat login berikutnya.'), { confirmLabel: 'Ya, Reset' }).then(confirmed => { if (confirmed) $el.submit() })"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                                    <x-icon name="autorenew" class="h-4 w-4 text-gray-500" />
                                                    Reset Password ke NIS
                                                </button>
                                            </form>
                                        @else
                                            <form
                                                method="POST"
                                                action="{{ route('admin.siswa.generate-akun', $siswa) }}"
                                                x-data
                                                @submit.prevent="confirmDialog('Buat Akun Login?', @js('Buat akun login untuk siswa \"' . $siswa->nama_lengkap . '\" dengan username berdasarkan NIS (' . $siswa->nis . ')?'), { confirmLabel: 'Ya, Buat Akun' }).then(confirmed => { if (confirmed) $el.submit() })"
                                            >
                                                @csrf
                                                <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-brand-700 font-semibold transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                                    <x-icon name="person_add" class="h-4 w-4 text-brand-600" />
                                                    Buat Akun Login
                                                </button>
                                            </form>
                                        @endif
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
                                        Belum ada siswa yang didaftarkan.
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

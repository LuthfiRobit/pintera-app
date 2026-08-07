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
                <h1 class="font-display text-lg font-bold text-gray-900">Guru</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola data induk guru dan akun login masing-masing.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Guru</b>
            </p>
        </div>

        {{-- Filter Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter Data
                </p>
                <x-link-button href="{{ route('admin.guru.create') }}">
                    <span class="text-base leading-none">+</span> Tambah Data Guru
                </x-link-button>
            </div>

            <form method="GET" action="{{ route('admin.guru.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input
                            type="text" name="search" id="search"
                            value="{{ $search }}"
                            placeholder="Nama atau NIP"
                            @input.debounce.500ms="$el.form.submit()"
                            class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                        >
                    </div>
                </div>

                <div>
                    <label for="jenis_ptk" class="mb-1.5 block text-xs font-semibold text-gray-500">Jenis PTK</label>
                    <select name="jenis_ptk" id="jenis_ptk" @change="$el.form.submit()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Jenis PTK</option>
                        @foreach ($jenisPtkOptions as $value => $label)
                            <option value="{{ $value }}" @selected($jenisPtk === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status_aktif" class="mb-1.5 block text-xs font-semibold text-gray-500">Status Aktif</label>
                    <select name="status_aktif" id="status_aktif" @change="$el.form.submit()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Status</option>
                        @foreach ($statusAktifOptions as $value => $label)
                            <option value="{{ $value }}" @selected($statusAktif === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end">
                    @if (request()->anyFilled(['search', 'jenis_ptk', 'status_aktif']))
                        <a href="{{ route('admin.guru.index') }}" class="flex h-[42px] w-full items-center justify-center rounded-lg border border-gray-200 px-3 text-sm text-gray-500 transition hover:bg-gray-50">
                            Reset Filter
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Table Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Guru</p>
                <x-badge tone="brass" class="text-xs font-semibold px-2.5 py-0.5">{{ $guruList->count() }} Data</x-badge>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                            <th class="px-5 py-3">Nama</th>
                            <th class="px-5 py-3">NIP</th>
                            <th class="px-5 py-3">Jenis PTK</th>
                            <th class="px-5 py-3">Status Kepegawaian</th>
                            <th class="px-5 py-3">Status Aktif</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($guruList as $item)
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <x-table-actions>
                                        <x-dropdown-link :href="route('admin.guru.edit', $item)">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                Edit Guru
                                            </span>
                                        </x-dropdown-link>
                                        @foreach ($statusAktifOptions as $value => $label)
                                            @if ($value !== $item->status_aktif)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.guru.update-status', $item) }}"
                                                    x-data
                                                    @submit.prevent="confirmDialog('Ubah Status Guru?', @js('Ubah status \"' . $item->nama . '\" menjadi \"' . $label . '\"?'), { confirmLabel: 'Ya, Ubah' }).then(confirmed => { if (confirmed) $el.submit() })"
                                                >
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status_aktif" value="{{ $value }}">
                                                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                                        <x-icon name="autorenew" class="h-4 w-4 text-gray-500" />
                                                        Jadikan {{ $label }}
                                                    </button>
                                                </form>
                                            @endif
                                        @endforeach
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $item->nama }}</td>
                                <td class="px-5 py-3.5 font-mono text-xs text-gray-600">{{ $item->nip ?: '—' }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $jenisPtkOptions[$item->jenis_ptk] ?? $item->jenis_ptk }}</td>
                                <td class="px-5 py-3.5">
                                    @if (in_array($item->status_kepegawaian, ['PNS', 'PPPK']))
                                        <x-badge tone="brass">{{ $item->status_kepegawaian }}</x-badge>
                                    @else
                                        <x-badge tone="slate">{{ $item->status_kepegawaian }}</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <x-badge tone="{{ $item->status_aktif === 'aktif' ? 'green' : 'amber' }}">
                                        {{ $statusAktifOptions[$item->status_aktif] ?? $item->status_aktif }}
                                    </x-badge>
                                </td>
                            </tr>
                        @endforeach

                        @if ($guruList->isEmpty())
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <x-icon name="school" class="h-7 w-7" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-700">Belum Ada Data Guru</p>
                                    <p class="mx-auto mt-0.5 max-w-sm text-xs text-gray-400">Tambahkan data guru pertama untuk lembaga ini.</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

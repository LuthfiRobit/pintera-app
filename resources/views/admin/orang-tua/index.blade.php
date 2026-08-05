<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Orang Tua</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola data induk orang tua/wali dan akun login masing-masing.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Orang Tua</b>
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-600">
                        <x-icon name="group" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-600">Total Orang Tua</p>
                        <p class="font-display text-lg font-bold leading-tight text-gray-900">{{ $totalOrangTua }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Terdaftar</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <x-icon name="check-circle" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-green-600">Akun Aktif</p>
                        <p class="font-display text-lg font-bold leading-tight text-gray-900">{{ $totalAktif }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Login siap</span>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter & Aksi Data
                </p>
                <x-link-button href="{{ route('admin.orang-tua.create') }}">
                    <span class="text-base leading-none">+</span> Tambah Data Orang Tua
                </x-link-button>
            </div>

            <form method="GET" action="{{ route('admin.orang-tua.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input
                            type="text" name="search" id="search"
                            value="{{ $search }}"
                            placeholder="Nama atau NIK"
                            @input.debounce.500ms="$el.form.submit()"
                            class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                        >
                    </div>
                </div>
                <div class="flex items-end">
                    @if (request()->filled('search'))
                        <a href="{{ route('admin.orang-tua.index') }}" class="flex h-[42px] w-full items-center justify-center rounded-lg border border-gray-200 px-3 text-sm text-gray-500 transition hover:bg-gray-50">
                            Reset Filter
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Orang Tua</p>
                <x-badge tone="brass" class="text-xs font-semibold px-2.5 py-0.5">{{ $orangTuaList->count() }} Data</x-badge>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                            <th class="px-5 py-3">Nama</th>
                            <th class="px-5 py-3">NIK</th>
                            <th class="px-5 py-3">No. HP</th>
                            <th class="px-5 py-3">Anak Tertaut</th>
                            <th class="px-5 py-3">Status Akun</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($orangTuaList as $item)
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <x-table-actions>
                                        <x-dropdown-link :href="route('admin.orang-tua.edit', $item)">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                Edit Orang Tua
                                            </span>
                                        </x-dropdown-link>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.orang-tua.update-status', $item) }}"
                                            x-data
                                            @submit.prevent="confirmDialog('Ubah Status Akun?', @js('Ubah status akun \"' . $item->nama_lengkap . '\" menjadi \"' . ($item->user?->is_active ? 'Non-aktif' : 'Aktif') . '\"?'), { confirmLabel: 'Ya, Ubah' }).then(confirmed => { if (confirmed) $el.submit() })"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_active" value="{{ $item->user?->is_active ? '0' : '1' }}">
                                            <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                                <x-icon name="autorenew" class="h-4 w-4 text-gray-500" />
                                                Jadikan {{ $item->user?->is_active ? 'Non-aktif' : 'Aktif' }}
                                            </button>
                                        </form>
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $item->nama_lengkap }}</td>
                                <td class="px-5 py-3.5 font-mono text-xs text-gray-600">{{ $item->nik }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $item->no_hp }}</td>
                                <td class="px-5 py-3.5">
                                    <x-badge tone="{{ $item->siswa_count > 0 ? 'blue' : 'gray' }}">{{ $item->siswa_count }} Anak</x-badge>
                                </td>
                                <td class="px-5 py-3.5">
                                    <x-badge tone="{{ $item->user?->is_active ? 'green' : 'amber' }}">{{ $item->user?->is_active ? 'Aktif' : 'Non-aktif' }}</x-badge>
                                </td>
                            </tr>
                        @endforeach

                        @if ($orangTuaList->isEmpty())
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <x-icon name="group" class="h-7 w-7" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-700">Belum Ada Data Orang Tua</p>
                                    <p class="mx-auto mt-0.5 max-w-sm text-xs text-gray-400">Tambahkan data orang tua/wali pertama.</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

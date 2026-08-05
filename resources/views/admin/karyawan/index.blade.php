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
                <h1 class="font-display text-lg font-bold text-gray-900">Karyawan</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola data karyawan (mis. konselor BK/psikolog pool yayasan).</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Karyawan</b>
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-600">
                        <x-icon name="group" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-600">Total Karyawan</p>
                        <p class="font-display text-lg font-bold leading-tight text-gray-900">{{ $totalKaryawan }}</p>
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
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-green-600">Aktif</p>
                        <p class="font-display text-lg font-bold leading-tight text-gray-900">{{ $totalAktif }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Siap bertugas</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="work_outline" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Pool Yayasan</p>
                        <p class="font-display text-lg font-bold leading-tight text-gray-900">{{ $totalPool }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Lintas lembaga</span>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter & Aksi Data
                </p>
                <x-link-button href="{{ route('admin.karyawan.create') }}">
                    <span class="text-base leading-none">+</span> Tambah Data Karyawan
                </x-link-button>
            </div>

            <form method="GET" action="{{ route('admin.karyawan.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input
                            type="text" name="search" id="search"
                            value="{{ $search }}"
                            placeholder="Nama"
                            @input.debounce.500ms="$el.form.submit()"
                            class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                        >
                    </div>
                </div>
                <div class="flex items-end">
                    @if (request()->filled('search'))
                        <a href="{{ route('admin.karyawan.index') }}" class="flex h-[42px] w-full items-center justify-center rounded-lg border border-gray-200 px-3 text-sm text-gray-500 transition hover:bg-gray-50">
                            Reset Filter
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Karyawan</p>
                <x-badge tone="brass" class="text-xs font-semibold px-2.5 py-0.5">{{ $karyawanList->count() }} Data</x-badge>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                            <th class="px-5 py-3">Nama</th>
                            <th class="px-5 py-3">Jenis</th>
                            <th class="px-5 py-3">Lembaga</th>
                            <th class="px-5 py-3">Kapasitas</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($karyawanList as $item)
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <x-table-actions>
                                        <x-dropdown-link :href="route('admin.karyawan.edit', $item)">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                Edit Karyawan
                                            </span>
                                        </x-dropdown-link>
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $item->nama }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $item->jenisKaryawan->nama }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $item->lembaga?->nama ?? 'Pool Yayasan' }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $item->kapasitas_kasus_aktif ? $item->kapasitas_kasus_aktif . ' Kasus Maks' : '-' }}</td>
                                <td class="px-5 py-3.5">
                                    <x-badge tone="{{ $item->status_aktif === 'aktif' ? 'green' : 'amber' }}">{{ $item->status_aktif === 'aktif' ? 'Aktif' : 'Non-aktif' }}</x-badge>
                                </td>
                            </tr>
                        @endforeach

                        @if ($karyawanList->isEmpty())
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <x-icon name="group" class="h-7 w-7" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-700">Belum Ada Data Karyawan</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

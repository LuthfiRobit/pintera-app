<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">
                Gelombang PPDB
                @if ($tahunAjaranTerpilih)
                    <span class="text-sm font-normal text-gray-500">&mdash; {{ $tahunAjaranTerpilih->nama }}</span>
                @endif
            </h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Gelombang PPDB</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter Data
                </p>
                @if ($tahunAjaranAktif)
                    <x-link-button href="{{ route('admin.gelombang-ppdb.create') }}">
                        <span class="text-base leading-none">+</span> Tambah Gelombang
                    </x-link-button>
                @endif
            </div>

            <form method="GET" action="{{ route('admin.gelombang-ppdb.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="cari" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input
                            type="text" name="cari" id="cari" value="{{ request('cari') }}"
                            placeholder="Nama gelombang"
                            @input.debounce.500ms="$el.form.submit()"
                            class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                        >
                    </div>
                </div>

                <div>
                    <label for="tahun_ajaran" class="mb-1.5 block text-xs font-semibold text-gray-500">Tahun Ajaran</label>
                    <select name="tahun_ajaran" id="tahun_ajaran" @change="$el.form.submit()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($tahunAjaranOptions as $option)
                            <option value="{{ $option->id }}" @selected($tahunAjaranTerpilih?->id === $option->id)>
                                {{ $option->nama }} @if ($option->status_aktif) (Aktif) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end">
                    @if (request()->filled('cari') || (request()->filled('tahun_ajaran') && (int) request('tahun_ajaran') !== $tahunAjaranAktif?->id))
                        <a href="{{ route('admin.gelombang-ppdb.index') }}" class="flex h-[42px] w-full items-center justify-center rounded-lg border border-gray-200 px-3 text-sm text-gray-500 transition hover:bg-gray-50">Reset Filter</a>
                    @endif
                </div>
            </form>
        </div>

        @if (! $tahunAjaranTerpilih)
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 shadow-card">
                Aktifkan tahun ajaran terlebih dahulu di menu
                <a href="{{ route('admin.tahun-ajaran.index') }}" class="font-semibold text-brand-600 hover:underline">Tahun Ajaran</a>
                sebelum mengatur gelombang PPDB.
            </div>
        @elseif ($gelombangList->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <p class="text-sm text-gray-500">Belum ada gelombang untuk {{ $tahunAjaranTerpilih->nama }}.</p>
                @if ($tahunAjaranSebelumnya && $tahunAjaranTerpilih->id === $tahunAjaranAktif?->id)
                    <form method="POST" action="{{ route('admin.spmb-konfigurasi.duplikasi') }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="tahun_ajaran_sumber_id" value="{{ $tahunAjaranSebelumnya->id }}">
                        <button type="submit" class="rounded-lg bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-600 transition hover:bg-brand-100">
                            Salin dari {{ $tahunAjaranSebelumnya->nama }}
                        </button>
                    </form>
                @endif
            </div>
        @else
            <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="border-b border-gray-200 px-5 py-4">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Gelombang</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                                <th class="px-5 py-3">Nama</th>
                                <th class="px-5 py-3">Tanggal Buka</th>
                                <th class="px-5 py-3">Tanggal Tutup</th>
                                <th class="px-5 py-3">Kuota</th>
                                <th class="px-5 py-3">Jalur</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($gelombangList as $gelombang)
                                <tr class="transition hover:bg-gray-50">
                                    <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                        <x-table-actions>
                                            <x-dropdown-link :href="route('admin.gelombang-ppdb.edit', $gelombang)">
                                                <span class="inline-flex items-center gap-2.5">
                                                    <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                    Edit Gelombang
                                                </span>
                                            </x-dropdown-link>
                                        </x-table-actions>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-gray-900">{{ $gelombang->nama }}</p>
                                        <p class="mt-0.5 flex items-center gap-1 text-xs text-gray-500">
                                            <x-icon name="apartment" class="h-3 w-3 shrink-0 text-gray-400" />
                                            {{ $gelombang->lembaga->nama }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-600">{{ $gelombang->tanggal_buka->format('d M Y') }}</td>
                                    <td class="px-5 py-3.5 text-gray-600">{{ $gelombang->tanggal_tutup->format('d M Y') }}</td>
                                    <td class="px-5 py-3.5 font-mono text-gray-600">{{ $gelombang->kuota }}</td>
                                    <td class="px-5 py-3.5">
                                        @php
                                            // A gelombang nobody has saved through this form yet
                                            // has zero pivot rows (legacy "unrestricted" state) —
                                            // treat that as using every currently active jalur.
                                            $jalurDipakai = $gelombang->jalur_count > 0 ? $gelombang->jalur_count : $totalJalurAktif;
                                        @endphp
                                        @if ($jalurDipakai < $totalJalurAktif)
                                            <x-badge tone="brass">{{ $jalurDipakai }} Jalur Dibatasi</x-badge>
                                        @else
                                            <x-badge tone="slate">{{ $jalurDipakai }} Jalur Aktif</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-5 py-4">
                    {{ $gelombangList->links('pagination.tailadmin') }}
                </div>
            </div>
        @endif
    </div>
</x-app-layout>

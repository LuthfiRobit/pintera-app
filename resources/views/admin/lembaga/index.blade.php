<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Lembaga</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Lembaga</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter Data
                </p>
                @if (auth()->user()->widestScopeLevel() === 'yayasan')
                    <x-link-button href="{{ route('admin.lembaga.create') }}">
                        <span class="text-base leading-none">+</span> Tambah Lembaga
                    </x-link-button>
                @endif
            </div>

            <form method="GET" action="{{ route('admin.lembaga.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="cari" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input
                            type="text" name="cari" id="cari" value="{{ request('cari') }}"
                            placeholder="Nama atau NPSN"
                            @input.debounce.500ms="$el.form.submit()"
                            class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                        >
                    </div>
                </div>

                <div>
                    <label for="bentuk" class="mb-1.5 block text-xs font-semibold text-gray-500">Bentuk Pendidikan</label>
                    <select name="bentuk" id="bentuk" @change="$el.form.submit()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Bentuk</option>
                        @foreach (['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB'] as $bentuk)
                            <option value="{{ $bentuk }}" @selected(request('bentuk') === $bentuk)>{{ $bentuk }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="mb-1.5 block text-xs font-semibold text-gray-500">Status Sekolah</label>
                    <select name="status" id="status" @change="$el.form.submit()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Status</option>
                        <option value="negeri" @selected(request('status') === 'negeri')>Negeri</option>
                        <option value="swasta" @selected(request('status') === 'swasta')>Swasta</option>
                    </select>
                </div>

                <div class="flex items-end">
                    @if (request()->anyFilled(['cari', 'bentuk', 'status']))
                        <a href="{{ route('admin.lembaga.index') }}" class="flex h-[42px] w-full items-center justify-center rounded-lg border border-gray-200 px-3 text-sm text-gray-500 transition hover:bg-gray-50">Reset Filter</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Lembaga</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                            <th class="px-5 py-3">NPSN</th>
                            <th class="px-5 py-3">Nama</th>
                            <th class="px-5 py-3">Bentuk</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($lembaga as $item)
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <x-table-actions>
                                        <x-dropdown-link :href="route('admin.lembaga.edit', $item)">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                Edit Lembaga
                                            </span>
                                        </x-dropdown-link>
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-gray-500">{{ $item->npsn }}</td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $item->nama }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $item->bentuk_pendidikan }}</td>
                                <td class="px-5 py-3.5">
                                    @if ($item->status_sekolah === 'negeri')
                                        <x-badge tone="brass">Negeri</x-badge>
                                    @else
                                        <x-badge tone="slate">Swasta</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        @if ($lembaga->isEmpty())
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-gray-500">Tidak ada lembaga yang cocok dengan filter ini.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-5 py-4">
                {{ $lembaga->links('pagination.tailadmin') }}
            </div>
        </div>
    </div>
</x-app-layout>

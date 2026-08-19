<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('success'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('success') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Persetujuan Rapor</h1>
                <p class="text-xs text-gray-500 mt-0.5">Daftar kelas yang menunggu keputusan Anda pada alur persetujuan rapor semester.</p>
            </div>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Persetujuan Rapor</b>
            </p>
        </div>

        <div
            class="space-y-4"
            x-data="dataTableFilter({
                filters: { search: @js(request('search', '')) },
                indexUrlBase: @js(route('admin.rapor.persetujuan.index')),
            })"
        >
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Kelas</label>
                <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                    <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                    <input type="text" x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()" placeholder="Nama kelas..." class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                </div>
            </div>

            <div x-ref="tableContainer">
                @include('portals.lembaga.rapor.persetujuan._daftar', ['pengajuanList' => $pengajuanList])
            </div>
        </div>
    </div>
</x-app-layout>

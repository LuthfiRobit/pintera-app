<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Mata Pelajaran</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Mata Pelajaran</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Mata Pelajaran</p>
                <x-link-button href="{{ route('admin.mata-pelajaran.create') }}">
                    <span class="text-base leading-none">+</span> Tambah Mata Pelajaran
                </x-link-button>
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
                                    <x-badge :tone="$mapel->tipe === \App\Enums\TipeMataPelajaran::Mapel ? 'brass' : 'blue'">{{ $mapel->tipe->label() }}</x-badge>
                                </td>
                            </tr>
                        @endforeach

                        @if ($mataPelajaranList->isEmpty())
                            <tr>
                                <td colspan="3" class="px-5 py-10 text-center text-gray-500">Belum ada mata pelajaran.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

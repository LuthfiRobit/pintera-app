<div class="space-y-4">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Tujuan Pembelajaran</p>
                    <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $komponenList->count() }}</p>
                </div>
                <div class="rounded-xl bg-brand-50 p-3 text-brand-600">
                    <x-icon name="checklist" class="h-6 w-6" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Mapel Tercover</p>
                    <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $komponenList->pluck('mata_pelajaran_id')->unique()->count() }}</p>
                </div>
                <div class="rounded-xl bg-blue-50 p-3 text-blue-600">
                    <x-icon name="menu_book" class="h-6 w-6" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Dengan KKTP Spesifik</p>
                    <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $komponenList->filter(fn($k) => !empty($k->kktp))->count() }}</p>
                </div>
                <div class="rounded-xl bg-amber-50 p-3 text-amber-600">
                    <x-icon name="fact_check" class="h-6 w-6" />
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
        <div class="flex flex-wrap items-center justify-between border-b border-gray-100 bg-white px-6 py-4 gap-3">
            <p class="font-display text-sm font-bold text-gray-900">Daftar Komponen &amp; Tujuan Pembelajaran</p>
            <div class="flex items-center gap-2">
                <x-badge tone="brand" class="text-xs font-semibold px-2.5 py-0.5">{{ $komponenList->count() }} Data</x-badge>
                <x-link-button href="{{ route('admin.komponen-penilaian.create') }}">
                    <span class="text-base leading-none mr-1.5">+</span> Tambah TP Baru
                </x-link-button>
            </div>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse ($komponenList as $komponen)
                <div class="p-6 transition hover:bg-gray-50/60 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            @if ($komponen->kode)
                                <span class="inline-flex items-center rounded-md border border-brand-500/30 bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700">
                                    {{ $komponen->kode }}
                                </span>
                            @endif
                            <span class="font-bold text-gray-900 text-base">{{ $komponen->mataPelajaran->nama }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-badge tone="slate" class="text-xs font-medium">{{ $komponen->semester->nama }} — {{ $komponen->semester->tahunAjaran->nama }}</x-badge>
                            @can('komponen-penilaian.kelola')
                                <a href="{{ route('admin.komponen-penilaian.edit', $komponen) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-900 transition-colors">Edit</a>
                                <form method="POST" action="{{ route('admin.komponen-penilaian.destroy', $komponen) }}" x-data @submit.prevent="confirmDialog('Hapus Komponen Penilaian?', @js('Apakah Anda yakin ingin menghapus TP ' . ($komponen->kode ?: $komponen->deskripsi) . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-error-500 hover:text-error-700 transition-colors">Hapus</button>
                                </form>
                            @endcan
                        </div>
                    </div>

                    <p class="text-sm text-gray-800 leading-relaxed font-medium">
                        {{ $komponen->deskripsi }}
                    </p>

                    @if ($komponen->kktp)
                        <div class="rounded-xl bg-amber-50/60 p-3.5 border border-amber-200/60 text-xs text-amber-900 space-y-1">
                            <p class="font-bold text-amber-800 uppercase tracking-wide text-[10px] flex items-center gap-1">
                                <x-icon name="fact_check" class="h-3.5 w-3.5 text-amber-600" />
                                KKTP (Kriteria Ketercapaian Tujuan Pembelajaran):
                            </p>
                            <p class="text-amber-900 leading-relaxed font-medium pl-4 border-l-2 border-amber-400">{{ $komponen->kktp }}</p>
                        </div>
                    @endif
                </div>
            @empty
                <div class="py-12 text-center text-gray-400 space-y-3">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                        <x-icon name="checklist" class="h-7 w-7" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Belum Ada Tujuan Pembelajaran</p>
                        <p class="text-xs text-gray-400 max-w-sm mx-auto mt-0.5">Tambahkan Tujuan Pembelajaran (TP) untuk mempermudah guru merujuk indikator penilaian saat menginput nilai asesmen.</p>
                    </div>
                    <x-link-button href="{{ route('admin.komponen-penilaian.create') }}" class="inline-flex justify-center">
                        <span class="text-base leading-none mr-1.5">+</span> Tambah TP Pertama
                    </x-link-button>
                </div>
            @endforelse
        </div>
    </div>
</div>

<x-app-layout>
    {{-- Top Navigation & Breadcrumbs --}}
    <div class="mx-auto max-w-5xl mb-6 flex flex-wrap items-center justify-between gap-3 px-4 sm:px-0">
        <div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <b class="font-semibold text-gray-900">Jenis Tagihan</b>
            </p>
        </div>
        @can('jenis-tagihan.create')
            <a href="{{ route('admin.jenis-tagihan.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                <x-icon name="add" class="h-4 w-4" />
                <span>Tambah Jenis Tagihan</span>
            </a>
        @endcan
    </div>

    <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-0">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700 shadow-sm">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-sm font-medium text-error-700 shadow-sm">{{ $errors->first() }}</div>
        @endif

        <div
            x-data="jenisTagihanTable({
                initialItems: @js($jenisTagihanList),
                deleteUrlTemplate: @js(route('admin.jenis-tagihan.destroy', ['jenisTagihan' => '__ID__'])),
                nominalUrlTemplate: @js(route('admin.jenis-tagihan.nominal', ['jenisTagihan' => '__ID__'])),
                editUrlTemplate: @js(route('admin.jenis-tagihan.edit', ['jenisTagihan' => '__ID__'])),
                prosesUrlTemplate: @js(route('admin.jenis-tagihan.proses', ['jenisTagihan' => '__ID__'])),
                monitoringUrlTemplate: @js(route('admin.jenis-tagihan.monitoring.index', ['jenisTagihan' => '__ID__'])),
            })"
            class="space-y-5"
        >
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50/50 px-5 py-4">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Jenis Tagihan</p>
                    {{-- Future extension: Search bar can be placed here --}}
                </div>
                
                {{-- Partial SPA Container --}}
                <div id="table-container">
                    @include('admin.jenis-tagihan._daftar')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

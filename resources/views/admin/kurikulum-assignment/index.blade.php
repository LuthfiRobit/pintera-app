<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Kurikulum</h1>
                    @if ($isYayasan ?? false)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                            <x-icon name="apartment" class="h-3.5 w-3.5" />
                            {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500">Kurikulum yang berlaku per jenjang, tingkat, dan tahun ajaran. Kelas baru mengikuti ini otomatis saat dibuat.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('kurikulum-assignment.view')
                    <x-link-button href="{{ route('admin.kurikulum-assignment.resync') }}" variant="ghost">
                        <x-icon name="sync" class="h-4 w-4" />
                        Sinkronisasi Kurikulum Kelas
                    </x-link-button>
                @endcan
                @can('kurikulum-assignment.create')
                    <x-link-button href="{{ route('admin.kurikulum-assignment.create') }}">
                        <x-icon name="plus" class="h-4 w-4" />
                        Tambah Aturan Kurikulum
                    </x-link-button>
                @endcan
            </div>
        </div>

        {{-- KPI Cards --}}
        @php
            $totalAturan = $assignmentList->count();
            $aturanMandiri = $assignmentList->whereNotNull('lembaga_id')->count();
            $standarPlatform = $assignmentList->whereNull('lembaga_id')->count();
        @endphp
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="assignment" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Aturan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalAturan }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Aktif</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                        <x-icon name="apartment" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-purple-600">Aturan Lembaga</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $aturanMandiri }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-purple-600">Khusus</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <x-icon name="verified" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-blue-600">Standar Platform</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $standarPlatform }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-blue-600">Cadangan</span>
            </div>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card"
            x-data="dataTableFilter({
                filters: {
                    tahun_ajaran_id: @js($filters['tahun_ajaran_id'] ?? ''),
                    bentuk_pendidikan: @js($filters['bentuk_pendidikan'] ?? '')
                },
                perPage: 20,
                indexUrlBase: @js(route('admin.kurikulum-assignment.index'))
            })"
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter Data
                </p>
                <button
                    type="button"
                    x-show="filters.tahun_ajaran_id || filters.bentuk_pendidikan"
                    @click="filters.tahun_ajaran_id = ''; filters.bentuk_pendidikan = ''; for (let key in tomSelects) { if (tomSelects[key]) tomSelects[key].clear(true); } muatUlangDaftar();"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-800 transition"
                >
                    <x-icon name="close" class="h-3.5 w-3.5" />
                    Reset Filter
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Tahun Ajaran</label>
                    <select x-ref="taSelect" x-init="initFilterSelect($refs.taSelect, 'tahun_ajaran_id', true)" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Tahun Ajaran</option>
                        @foreach ($tahunAjaranList as $ta)
                            <option value="{{ $ta->id }}" @selected(($filters['tahun_ajaran_id'] ?? null) == $ta->id)>{{ $ta->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Bentuk Pendidikan</label>
                    <select x-ref="bpSelect" x-init="initFilterSelect($refs.bpSelect, 'bentuk_pendidikan', false)" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Bentuk Pendidikan</option>
                        @foreach ($bentukPendidikanList as $bp)
                            <option value="{{ $bp->value }}" @selected(($filters['bentuk_pendidikan'] ?? null) === $bp->value)>{{ $bp->value }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div x-ref="tableContainer" class="mt-4">
                @include('admin.kurikulum-assignment._daftar')
            </div>
        </div>
    </div>
</x-app-layout>

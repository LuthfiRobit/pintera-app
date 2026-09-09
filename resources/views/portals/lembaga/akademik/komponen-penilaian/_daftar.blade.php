@php
    $groupedKomponen = $komponenList->groupBy(fn($k) => $k->subjek_type . '-' . $k->subjek_id . '-' . $k->semester_id);
    $totalTpCount = $komponenList->count();
    $totalSubjectsCount = $groupedKomponen->count();
    $completeSubjectsCount = $groupedKomponen->filter(fn($g) => $g->sum('bobot') == 100)->count();
    $incompleteSubjectsCount = $totalSubjectsCount - $completeSubjectsCount;
@endphp

<div class="space-y-5">
    {{-- KPI STATS BAR (4 Pillars) --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Total TP</p>
                    <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $totalTpCount }}</p>
                </div>
                <div class="rounded-xl bg-brand-50 p-2.5 text-brand-600">
                    <x-icon name="checklist" class="h-5 w-5" />
                </div>
            </div>
            <p class="mt-1.5 text-[11px] text-gray-500">Tujuan pembelajaran aktif</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Mata Pelajaran</p>
                    <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $totalSubjectsCount }}</p>
                </div>
                <div class="rounded-xl bg-blue-50 p-2.5 text-blue-600">
                    <x-icon name="menu_book" class="h-5 w-5" />
                </div>
            </div>
            <p class="mt-1.5 text-[11px] text-gray-500">Subjek ber-TP</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Siap Rapor (100%)</p>
                    <p class="mt-1 font-display text-2xl font-bold text-emerald-700">{{ $completeSubjectsCount }}</p>
                </div>
                <div class="rounded-xl bg-emerald-50 p-2.5 text-emerald-600">
                    <x-icon name="check_circle" class="h-5 w-5" />
                </div>
            </div>
            <p class="mt-1.5 text-[11px] text-emerald-600 font-medium">Bobot sudah genap</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-600">Perlu Dilengkapi</p>
                    <p class="mt-1 font-display text-2xl font-bold text-amber-700">{{ $incompleteSubjectsCount }}</p>
                </div>
                <div class="rounded-xl bg-amber-50 p-2.5 text-amber-600">
                    <x-icon name="warning" class="h-5 w-5" />
                </div>
            </div>
            <p class="mt-1.5 text-[11px] text-amber-600 font-medium">Bobot belum 100%</p>
        </div>
    </div>

    {{-- SECTION TITLE BAR --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white px-5 py-3.5 shadow-card">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Komponen &amp; Tujuan Pembelajaran</p>
        <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
            {{ $totalTpCount }} Data
        </span>
    </div>

    {{-- Controls Row: Status Filter Tabs & Bulk Expand/Collapse --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-3.5 shadow-card">
        {{-- Status Filter Tabs --}}
        <div class="inline-flex rounded-xl bg-gray-100 p-1 text-xs font-semibold">
            <button
                type="button"
                @click="statusFilter = 'all'"
                :class="statusFilter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                class="rounded-lg px-3 py-1.5 transition"
            >
                Semua Mapel ({{ $totalSubjectsCount }})
            </button>
            <button
                type="button"
                @click="statusFilter = 'incomplete'"
                :class="statusFilter === 'incomplete' ? 'bg-white text-amber-800 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                class="rounded-lg px-3 py-1.5 transition flex items-center gap-1.5"
            >
                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                Perlu Dilengkapi ({{ $incompleteSubjectsCount }})
            </button>
            <button
                type="button"
                @click="statusFilter = 'complete'"
                :class="statusFilter === 'complete' ? 'bg-white text-emerald-800 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                class="rounded-lg px-3 py-1.5 transition flex items-center gap-1.5"
            >
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                Siap Rapor 100% ({{ $completeSubjectsCount }})
            </button>
        </div>

        {{-- Bulk Expand/Collapse Actions --}}
        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="expandAll()"
                class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 transition shadow-xs"
            >
                <x-icon name="expand_more" class="h-3.5 w-3.5" />
                <span>Buka Semua</span>
            </button>
            <button
                type="button"
                @click="collapseAll()"
                class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 transition shadow-xs"
            >
                <x-icon name="expand_more" class="h-3.5 w-3.5 rotate-180" />
                <span>Tutup Semua</span>
            </button>
        </div>
    </div>

    {{-- SUBJECT ACCORDION CARDS LIST --}}
    <div class="space-y-4">
        @forelse ($groupedKomponen as $groupKey => $tps)
            @php
                $first = $tps->first();
                $subjectName = $first->subjek->nama ?? 'Mata Pelajaran';
                $totalBobot = (int) $tps->sum('bobot');
                $isComplete = $totalBobot === 100;
                $isOver = $totalBobot > 100;
                $tpData = $tps->map(fn($t) => [
                    'id' => $t->id,
                    'kode' => $t->kode,
                    'deskripsi' => $t->deskripsi,
                    'bobot' => (int) $t->bobot,
                    'kktp' => $t->kktp,
                ]);
            @endphp

            <div
                data-accordion-key="{{ $groupKey }}"
                x-show="isCardVisible(@js($groupKey), @js($subjectName), @js($totalBobot), @js($tpData))"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="overflow-hidden rounded-2xl border transition-all duration-200 {{ $isComplete ? 'border-gray-200 bg-white shadow-card hover:border-emerald-200' : 'border-amber-200/90 bg-white shadow-card hover:border-amber-300' }}"
            >
                {{-- ACCORDION HEADER (Clickable to Toggle) --}}
                <div
                    @click="toggleCard(@js($groupKey))"
                    role="button"
                    tabindex="0"
                    class="cursor-pointer select-none border-b border-gray-100 p-4 sm:p-5 transition hover:bg-gray-50/70 {{ $isComplete ? 'bg-white' : 'bg-amber-50/20' }}"
                >
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        {{-- Left: Subject Info --}}
                        <div class="flex items-start gap-3.5">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $isComplete ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' }}">
                                <x-icon name="menu_book" class="h-6 w-6" />
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-display text-base font-bold text-gray-900 leading-tight">
                                        {{ $subjectName }}
                                    </h3>
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                                        {{ $tps->count() }} TP
                                    </span>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                    <span class="font-medium text-gray-700">{{ $first->semester->nama }} — {{ $first->semester->tahunAjaran->nama }}</span>
                                    @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                                        <span>&bull;</span>
                                        <span class="inline-flex items-center gap-1 font-medium text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full text-[11px]">
                                            <x-icon name="apartment" class="h-3 w-3" />
                                            {{ $first->lembaga->nama ?? '-' }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Right: Weight Bar & Status & Chevron --}}
                        <div class="flex items-center justify-between md:justify-end gap-3.5 pt-2 md:pt-0 border-t md:border-t-0 border-gray-100">
                            {{-- Mini Progress Bar --}}
                            <div class="w-36 sm:w-44 space-y-1">
                                <div class="flex items-center justify-between text-[11px] font-semibold">
                                    <span class="text-gray-500">Total Bobot:</span>
                                    <span class="{{ $isComplete ? 'text-emerald-700' : ($isOver ? 'text-error-700' : 'text-amber-700') }}">
                                        {{ $totalBobot }}% / 100%
                                    </span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                    <div
                                        class="h-full rounded-full transition-all duration-500 {{ $isComplete ? 'bg-emerald-500' : ($isOver ? 'bg-error-500' : 'bg-amber-500') }}"
                                        style="width: {{ min($totalBobot, 100) }}%"
                                    ></div>
                                </div>
                            </div>

                            {{-- Status Badge --}}
                            <div>
                                @if ($isComplete)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-bold text-emerald-700">
                                        <x-icon name="check_circle" class="h-3.5 w-3.5 text-emerald-600" />
                                        <span>100% Siap Rapor</span>
                                    </span>
                                @elseif ($isOver)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-3 py-1 text-xs font-bold text-rose-700">
                                        <x-icon name="warning" class="h-3.5 w-3.5 text-rose-600" />
                                        <span>Lebih {{ $totalBobot - 100 }}%</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-bold text-amber-800">
                                        <x-icon name="warning" class="h-3.5 w-3.5 text-amber-600" />
                                        <span>Kurang {{ 100 - $totalBobot }}%</span>
                                    </span>
                                @endif
                            </div>

                            {{-- Animated Chevron --}}
                            <div class="text-gray-400 transition-transform duration-200" :class="expandedCards[@js($groupKey)] ? 'rotate-180' : ''">
                                <x-icon name="expand_more" class="h-5 w-5" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ACCORDION BODY (List of TP Rows) --}}
                <div
                    x-show="expandedCards[@js($groupKey)]"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                >
                    <div class="divide-y divide-gray-100">
                        @foreach ($tps as $komponen)
                            @php
                                $singleTpData = [
                                    'id' => $komponen->id,
                                    'kode' => $komponen->kode,
                                    'deskripsi' => $komponen->deskripsi,
                                    'bobot' => (int) $komponen->bobot,
                                    'kktp' => $komponen->kktp,
                                ];
                            @endphp
                            <div
                                x-show="isTpVisible(@js($singleTpData), @js($subjectName))"
                                class="p-4 sm:p-5 transition hover:bg-gray-50/70"
                            >
                                {{-- DESKTOP LAYOUT (sm and up) --}}
                                <div class="hidden sm:flex sm:items-start sm:justify-between sm:gap-4">
                                    <div class="flex items-start gap-3 flex-1">
                                        {{-- Kode & Bobot Pills --}}
                                        <div class="flex flex-col items-start gap-1.5 shrink-0 pt-0.5">
                                            @if ($komponen->kode)
                                                <span class="inline-flex items-center rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700">
                                                    {{ $komponen->kode }}
                                                </span>
                                            @endif
                                            <span class="inline-flex items-center rounded-lg border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-extrabold text-amber-800">
                                                {{ $komponen->bobot ?? 100 }}%
                                            </span>
                                            <x-badge tone="slate" class="text-[10px] font-medium">{{ $komponen->semester->nama }} — {{ $komponen->semester->tahunAjaran->nama }}</x-badge>
                                        </div>

                                        {{-- Content --}}
                                        <div class="space-y-2 flex-1">
                                            <p class="text-sm font-medium text-gray-800 leading-relaxed">
                                                {{ $komponen->deskripsi }}
                                            </p>

                                            @if ($komponen->kktp)
                                                <div class="rounded-xl border border-amber-200/80 bg-amber-50/40 p-3 text-xs text-amber-900 space-y-1">
                                                    <div class="flex items-center gap-1.5 font-bold uppercase tracking-wide text-[10px] text-amber-800">
                                                        <x-icon name="fact_check" class="h-3.5 w-3.5 text-amber-600" />
                                                        <span>KKTP (Kriteria Ketercapaian Tujuan Pembelajaran):</span>
                                                    </div>
                                                    <p class="font-normal text-amber-900/90 pl-5 leading-relaxed">
                                                        {{ $komponen->kktp }}
                                                    </p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Actions --}}
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @can('komponen-penilaian.kelola')
                                            <a
                                                href="{{ route('admin.komponen-penilaian.edit', $komponen) }}"
                                                class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:text-brand-600 transition shadow-sm"
                                            >
                                                <x-icon name="edit" class="h-3.5 w-3.5 text-gray-500" />
                                                <span>Edit</span>
                                            </a>

                                            <form
                                                method="POST"
                                                action="{{ route('admin.komponen-penilaian.destroy', $komponen) }}"
                                                x-data
                                                @submit.prevent="confirmDialog('Hapus Komponen Penilaian?', @js('Apakah Anda yakin ingin menghapus TP ' . ($komponen->kode ?: $komponen->deskripsi) . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-error-600 hover:bg-error-50 hover:border-error-200 transition shadow-sm"
                                                >
                                                    <x-icon name="delete" class="h-3.5 w-3.5 text-error-500" />
                                                    <span>Hapus</span>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </div>

                                {{-- MOBILE TOUCH-FRIENDLY LAYOUT (< sm) --}}
                                <div class="block sm:hidden space-y-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            @if ($komponen->kode)
                                                <span class="inline-flex items-center rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700">
                                                    {{ $komponen->kode }}
                                                </span>
                                            @endif
                                            <span class="inline-flex items-center rounded-lg border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-extrabold text-amber-800">
                                                Bobot: {{ $komponen->bobot ?? 100 }}%
                                            </span>
                                            <x-badge tone="slate" class="text-[10px] font-medium">{{ $komponen->semester->nama }} — {{ $komponen->semester->tahunAjaran->nama }}</x-badge>
                                        </div>
                                    </div>

                                    <p class="text-sm font-medium text-gray-800 leading-relaxed">
                                        {{ $komponen->deskripsi }}
                                    </p>

                                    @if ($komponen->kktp)
                                        <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-2.5 text-xs text-amber-900 space-y-1">
                                            <p class="font-bold text-[10px] uppercase text-amber-800 flex items-center gap-1">
                                                <x-icon name="fact_check" class="h-3 w-3 text-amber-600" />
                                                KKTP:
                                            </p>
                                            <p class="text-xs text-amber-900/90 pl-3 leading-relaxed">
                                                {{ $komponen->kktp }}
                                            </p>
                                        </div>
                                    @endif

                                    {{-- Mobile Action Buttons (Full Width, 44px Touch target) --}}
                                    @can('komponen-penilaian.kelola')
                                        <div class="grid grid-cols-2 gap-2 pt-1">
                                            <a
                                                href="{{ route('admin.komponen-penilaian.edit', $komponen) }}"
                                                class="flex h-10 items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-gray-50 text-xs font-bold text-gray-700 active:bg-gray-100"
                                            >
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                <span>Edit TP</span>
                                            </a>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.komponen-penilaian.destroy', $komponen) }}"
                                                class="w-full"
                                                x-data
                                                @submit.prevent="confirmDialog('Hapus Komponen Penilaian?', @js('Apakah Anda yakin ingin menghapus TP ' . ($komponen->kode ?: $komponen->deskripsi) . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="flex h-10 w-full items-center justify-center gap-1.5 rounded-xl border border-error-200 bg-error-50 text-xs font-bold text-error-700 active:bg-error-100"
                                                >
                                                    <x-icon name="delete" class="h-4 w-4 text-error-600" />
                                                    <span>Hapus</span>
                                                </button>
                                            </form>
                                        </div>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- ACCORDION FOOTER / INLINE ACTION BAR --}}
                    <div class="border-t border-gray-100 bg-gray-50/50 p-4 sm:px-6">
                        @if ($isComplete)
                            <div class="flex items-center justify-between gap-3 text-xs text-emerald-700">
                                <div class="flex items-center gap-2">
                                    <x-icon name="check_circle" class="h-4 w-4 text-emerald-600 shrink-0" />
                                    <span>Mata pelajaran ini telah genap 100% dan siap digunakan untuk pengisian nilai rapor.</span>
                                </div>
                                @if (! ($isYayasan ?? false) || ($activeLembaga ?? null))
                                    <a
                                        href="{{ route('admin.komponen-penilaian.create', ['subjek_id' => $first->subjek_id, 'semester_id' => $first->semester_id, 'tahun_ajaran_id' => $first->semester->tahun_ajaran_id]) }}"
                                        class="text-xs font-semibold text-gray-500 hover:text-brand-600 transition shrink-0"
                                    >
                                        + Tambah TP Lain
                                    </a>
                                @else
                                    <x-tooltip text="Pilih 1 lembaga aktif dulu untuk menambah TP.">
                                        <span class="text-xs font-semibold text-gray-300 cursor-not-allowed shrink-0">+ Tambah TP Lain</span>
                                    </x-tooltip>
                                @endif
                            </div>
                        @elseif ($isOver)
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-rose-700">
                                <div class="flex items-center gap-2">
                                    <x-icon name="warning" class="h-4 w-4 text-rose-600 shrink-0" />
                                    <span>Total bobot saat ini {{ $totalBobot }}% (melebihi 100%). Silakan sesuaikan bobot masing-masing TP di atas.</span>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-2 text-xs text-amber-800">
                                    <x-icon name="warning" class="h-4 w-4 text-amber-600 shrink-0" />
                                    <span>
                                        Bobot saat ini <b>{{ $totalBobot }}%</b>. Dibutuhkan <b>{{ 100 - $totalBobot }}%</b> lagi agar genap 100%.
                                    </span>
                                </div>
                                @if (! ($isYayasan ?? false) || ($activeLembaga ?? null))
                                    <a
                                        href="{{ route('admin.komponen-penilaian.create', ['subjek_id' => $first->subjek_id, 'semester_id' => $first->semester_id, 'tahun_ajaran_id' => $first->semester->tahun_ajaran_id]) }}"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm transition"
                                    >
                                        <span class="text-sm leading-none">+</span>
                                        <span>Tambah TP (Sisa: {{ 100 - $totalBobot }}%)</span>
                                    </a>
                                @else
                                    <x-tooltip text="Pilih 1 lembaga aktif dulu untuk menambah TP.">
                                        <span class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-100 px-3.5 py-2 text-xs font-bold text-amber-400 cursor-not-allowed">
                                            <span class="text-sm leading-none">+</span>
                                            <span>Tambah TP (Sisa: {{ 100 - $totalBobot }}%)</span>
                                        </span>
                                    </x-tooltip>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-gray-200 bg-white p-12 text-center text-gray-400 space-y-3 shadow-card">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                    <x-icon name="checklist" class="h-7 w-7" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-700">Belum Ada Tujuan Pembelajaran</p>
                    <p class="text-xs text-gray-400 max-w-sm mx-auto mt-0.5">Tambahkan Tujuan Pembelajaran (TP) untuk mempermudah guru merujuk indikator penilaian saat menginput nilai asesmen.</p>
                </div>
                <div class="pt-2">
                    @if (! ($isYayasan ?? false) || ($activeLembaga ?? null))
                        <x-link-button href="{{ route('admin.komponen-penilaian.create') }}" class="inline-flex justify-center shadow-sm">
                            <span class="text-base leading-none mr-1.5">+</span> Tambah TP Pertama
                        </x-link-button>
                    @else
                        <x-tooltip text="Pilih 1 lembaga aktif dulu untuk menambah TP.">
                            <x-secondary-button type="button" disabled class="inline-flex justify-center cursor-not-allowed">
                                <span class="text-base leading-none mr-1.5">+</span> Tambah TP Pertama
                            </x-secondary-button>
                        </x-tooltip>
                    @endif
                </div>
            </div>
        @endforelse
    </div>
</div>

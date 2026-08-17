<x-app-layout>
    <div
        class="mx-auto max-w-6xl space-y-4"
        x-data="rppPageManager({
            filters: {
                tab: @js($tab),
                search: @js(request('search', '')),
                tahun_ajaran_id: @js($tahunAjaranId),
                semester_id: @js($semesterId ?? ''),
                kelas_id: @js($kelasId ?? ''),
                mata_pelajaran_id: @js($mapelId ?? ''),
                status: @js($status ?? '')
            },
            perPage: @js($perPage ?? 20),
            indexUrlBase: @js(route('admin.rpp.index'))
        })"
    >
        {{-- Flash Messages & Toast Notifications --}}
        @if (session('success') || session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('success') ?? session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Perangkat Ajar (RPP / Modul Ajar)</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola penyusunan dokumen perencanaan pembelajaran, pengajuan, dan verifikasi kurikulum.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Perangkat Ajar (RPP)</b>
            </p>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="description" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Dokumen</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($stats['total'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Berkas</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="schedule" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Menunggu Review</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($stats['diajukan'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-amber-400">Proses</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <x-icon name="verified" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Telah Disetujui</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($stats['disetujui'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-emerald-400">Sah</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                        <x-icon name="history_edu" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-rose-600">Perlu Perbaikan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($stats['perlu_revisi'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-rose-400">Revisi</span>
            </div>
        </div>

        {{-- Filter Container & Actions --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
            {{-- Tabs & Action Button --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.rpp.index', ['tab' => 'saya']) }}"
                       class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition-all duration-200 {{ $tab === 'saya' ? 'bg-brand-50 text-brand-700 border border-brand-200 shadow-xs' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100' }}">
                        <x-icon name="description" class="h-4 w-4" />
                        <span>Perangkat Ajar Saya</span>
                    </a>

                    @can('rpp.verify')
                        <a href="{{ route('admin.rpp.index', ['tab' => 'verifikasi']) }}"
                           class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition-all duration-200 {{ $tab === 'verifikasi' ? 'bg-brand-50 text-brand-700 border border-brand-200 shadow-xs' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100' }}">
                            <x-icon name="verified" class="h-4 w-4" />
                            <span>Inbox Verifikasi Kurikulum</span>
                            @if (($stats['diajukan'] ?? 0) > 0)
                                <span class="ml-1 rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-extrabold text-white">
                                    {{ $stats['diajukan'] }}
                                </span>
                            @endif
                        </a>
                    @endcan
                </div>

                @can('rpp.kelola')
                    <div class="flex items-center gap-2">
                        <x-primary-button type="button" @click="openCreateModal()" class="gap-1.5 shadow-sm">
                            <span class="text-base leading-none font-bold">+</span>
                            <span>Unggah Dokumen RPP</span>
                        </x-primary-button>
                    </div>
                @endcan
            </div>

            {{-- Grid Filter Form --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                {{-- Search Box --}}
                <div class="lg:col-span-2">
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Topik / Dokumen</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input
                            type="text" x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()"
                            placeholder="Judul topik materi, berkas, guru..."
                            class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                        >
                    </div>
                </div>

                {{-- Tahun Ajaran --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Tahun Ajaran</label>
                    <select x-model="filters.tahun_ajaran_id" @change="muatUlangDaftar()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        <option value="">— Semua TA —</option>
                        @foreach ($tahunAjaranList as $ta)
                            <option value="{{ $ta->id }}">
                                {{ $ta->nama }} {{ $ta->status_aktif ? '(Aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Semester --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Semester</label>
                    <select x-model="filters.semester_id" @change="muatUlangDaftar()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        <option value="">— Semua Semester —</option>
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}">{{ $sem->nama }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Kelas --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Kelas</label>
                    <select x-model="filters.kelas_id" @change="muatUlangDaftar()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        <option value="">— Semua Kelas —</option>
                        @foreach ($kelasList as $k)
                            <option value="{{ $k->id }}">{{ $k->nama }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Status</label>
                    <select x-model="filters.status" @change="muatUlangDaftar()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        <option value="">— Semua Status —</option>
                        @foreach (\App\Domains\Akademik\Enums\StatusRpp::cases() as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Mata Pelajaran Filter (Row 2) --}}
            <div class="pt-2 border-t border-gray-100 flex items-center gap-3">
                <span class="text-xs font-semibold text-gray-500 whitespace-nowrap">Mata Pelajaran:</span>
                <select x-model="filters.mata_pelajaran_id" @change="muatUlangDaftar()" class="block w-full max-w-sm rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-1.5">
                    <option value="">— Semua Mata Pelajaran (Termasuk PAUD) —</option>
                    @foreach ($mataPelajaranList as $m)
                        <option value="{{ $m->id }}">{{ $m->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Table Container for AJAX Data --}}
        <div x-ref="tableContainer">
            @include('portals.lembaga.akademik.rpp._daftar')
        </div>

        {{-- Include Modals --}}
        @include('portals.lembaga.akademik.rpp._modal-form')
        @include('portals.lembaga.akademik.rpp._modal-verify')
    </div>
</x-app-layout>

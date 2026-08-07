<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6" x-data="{
        activeTab: window.location.hash ? window.location.hash.replace('#', '') : 'profil',
        mode: {{ $errors->any() ? "'edit'" : "'view'" }},
        init() {
            window.addEventListener('hashchange', () => {
                this.activeTab = window.location.hash.replace('#', '') || 'profil';
            });
            if (!window.location.hash) {
                window.history.replaceState(null, null, '#profil');
            }
        },
        setTab(tab) {
            this.activeTab = tab;
            window.history.replaceState(null, null, '#' + tab);
        },
        toggleMode() {
            this.mode = (this.mode === 'view' ? 'edit' : 'view');
            if (this.mode === 'edit' && this.activeTab !== 'profil') {
                this.setTab('profil');
            }
        }
    }">
        {{-- Flash Messages --}}
        @if (session('status') || session('success'))
            <div class="rounded-lg bg-success-50 p-4 text-sm font-medium text-success-700 shadow-sm">
                {{ session('status') ?? session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-lg bg-error-50 p-4 text-sm font-medium text-error-700 shadow-sm">
                {{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm font-medium text-error-700 shadow-sm" x-init="$store.toast ? $store.toast.push('error', @js($errors->first())) : null">
                Terdapat kesalahan pengisian pada formulir, silakan periksa kembali isian di bawah.
            </div>
        @endif

        {{-- Top Navigation & Breadcrumbs --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-gray-500">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                    <a href="{{ route('admin.lembaga.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Lembaga</a>
                    <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-900">Portal Kelembagaan &amp; Relasional</b>
                </p>
            </div>
            <div class="flex items-center gap-2">
                @can('lembaga.edit')
                    <button type="button" @click="toggleMode()" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95" title="Aktifkan atau nonaktifkan formulir perubahan profil utama">
                        <x-icon name="edit" class="h-4 w-4 text-brand-600" x-show="mode === 'view'" />
                        <x-icon name="visibility" class="h-4 w-4 text-indigo-600" x-show="mode === 'edit'" style="display: none;" />
                        <span x-text="mode === 'view' ? 'Mode Edit Profil' : 'Mode Lihat Profil'">Mode Edit Profil</span>
                    </button>
                @endcan
                <a href="{{ route('admin.lembaga.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95" title="Kembali ke halaman daftar lembaga">
                    <x-icon name="arrow_back" class="h-4 w-4 text-gray-500" />
                    <span>Kembali ke Daftar</span>
                </a>
            </div>
        </div>

        {{-- Hero Summary Profile Card (Premium Museum Quality UX) --}}
        <div class="relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card md:p-8">
            <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-50 to-teal-50 opacity-60 blur-xl"></div>
            <div class="relative flex flex-col gap-6 md:flex-row md:items-center justify-between">
                <div class="flex items-center gap-6">
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-600 via-teal-700 to-emerald-800 text-white shadow-md">
                        <x-icon name="apartment" class="h-10 w-10" />
                    </div>
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="font-display text-2xl font-bold tracking-tight text-gray-900">{{ $lembaga->nama }}</h1>
                            @if($lembaga->status_aktif)
                                <span class="rounded-full bg-emerald-50 border border-emerald-200 px-3 py-0.5 text-xs font-semibold text-emerald-700 flex items-center gap-1">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Aktif
                                </span>
                            @else
                                <span class="rounded-full bg-rose-50 border border-rose-200 px-3 py-0.5 text-xs font-semibold text-rose-700">
                                    Non-Aktif
                                </span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-600">
                            <span class="flex items-center gap-1.5 font-mono">
                                <x-icon name="badge" class="h-4 w-4 text-gray-400" />
                                NPSN: <strong class="text-indigo-700 font-extrabold">{{ $lembaga->npsn ?: 'Belum Ada' }}</strong>
                            </span>
                            <span class="flex items-center gap-1.5 font-mono">
                                <x-icon name="tag" class="h-4 w-4 text-gray-400" />
                                Kode: <strong class="text-emerald-700 font-extrabold">{{ $lembaga->kode_lembaga ?: '-' }}</strong>
                            </span>
                            <span class="flex items-center gap-1.5">
                                <x-icon name="school" class="h-4 w-4 text-gray-400" />
                                Bentuk: <strong class="text-gray-900 uppercase">{{ $lembaga->bentuk_pendidikan ?: '-' }}</strong>
                            </span>
                            <span class="flex items-center gap-1.5">
                                <x-icon name="stars" class="h-4 w-4 text-amber-500" />
                                Akreditasi: <strong class="text-brand-600 font-bold">{{ $lembaga->akreditasi ?: 'Belum' }}</strong>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Navigation Tabs Header --}}
            <div class="mt-8 flex border-b border-gray-200 overflow-x-auto text-sm font-semibold text-gray-500 scrollbar-none">
                <button type="button" @click="setTab('profil');" :class="activeTab === 'profil' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <x-icon name="apartment" class="h-4 w-4" />
                    <span>Profil &amp; Identitas</span>
                </button>
                <button type="button" @click="setTab('data-periodik');" :class="activeTab === 'data-periodik' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <x-icon name="analytics" class="h-4 w-4" />
                    <span>Data Periodik &amp; Fasilitas</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 font-mono" :class="activeTab === 'data-periodik' ? 'bg-brand-100 text-brand-800' : ''">{{ $lembaga->dataPeriodik->count() }}</span>
                </button>
                <button type="button" @click="setTab('ekstrakurikuler');" :class="activeTab === 'ekstrakurikuler' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <x-icon name="sports_basketball" class="h-4 w-4" />
                    <span>Ekstrakurikuler</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 font-mono" :class="activeTab === 'ekstrakurikuler' ? 'bg-brand-100 text-brand-800' : ''">{{ $lembaga->ekstrakurikuler->count() }}</span>
                </button>
                <button type="button" @click="setTab('layanan-khusus');" :class="activeTab === 'layanan-khusus' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <x-icon name="support_agent" class="h-4 w-4" />
                    <span>Layanan Khusus</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 font-mono" :class="activeTab === 'layanan-khusus' ? 'bg-brand-100 text-brand-800' : ''">{{ $lembaga->layananKhusus->count() }}</span>
                </button>
                <button type="button" @click="setTab('program-inklusi');" :class="activeTab === 'program-inklusi' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <x-icon name="diversity_1" class="h-4 w-4" />
                    <span>Program Inklusi</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 font-mono" :class="activeTab === 'program-inklusi' ? 'bg-brand-100 text-brand-800' : ''">{{ $lembaga->programInklusi->count() }}</span>
                </button>
            </div>
        </div>

        {{-- Content Area for Tabs --}}
        <div class="mt-6">
            @include('admin.lembaga.tabs.profil')
            @include('admin.lembaga.tabs.data-periodik')
            @include('admin.lembaga.tabs.ekstrakurikuler')
            @include('admin.lembaga.tabs.layanan-khusus')
            @include('admin.lembaga.tabs.program-inklusi')
        </div>
    </div>
</x-app-layout>

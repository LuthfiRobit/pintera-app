<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-5" x-data="{ activeTab: '{{ old('frekuensi') !== null ? 'tugas' : 'info' }}', showSesiForm: false, showTugasForm: false }">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast ? $store.toast.push('error', @js($errors->first())) : null">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Rincian Kasus: {{ $kasus->siswa->nama_lengkap }}</h1>
                <p class="text-xs text-gray-500 mt-0.5">Pantau perkembangan bimbingan, jadwal sesi, penegakan informed consent, serta evaluasi kasus.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('kasus.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Kasus Pendampingan</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Detail</b>
            </p>
        </div>

        {{-- Hero Summary Profile Card (Premium Museum Quality UX) --}}
        <div class="relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card md:p-8">
            <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-gradient-to-br from-brand-50 to-purple-50 opacity-60 blur-xl"></div>
            
            <div class="relative flex flex-col gap-6 md:flex-row md:items-start justify-between">
                <div class="flex flex-col gap-6 md:flex-row md:items-center">
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-600 to-indigo-700 text-2xl font-bold text-white shadow-md">
                        {{ strtoupper(substr($kasus->siswa->nama_lengkap ?? 'S', 0, 2)) }}
                    </div>
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="font-display text-2xl font-bold tracking-tight text-gray-900">{{ $kasus->siswa->nama_lengkap }}</h1>
                            @if ($kasus->tingkat_urgensi === 'tinggi')
                                <span class="rounded bg-red-100 px-2 py-0.5 text-[10px] font-extrabold text-red-800 border border-red-200">URGENSI TINGGI</span>
                            @elseif ($kasus->tingkat_urgensi === 'sedang')
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200">Urgensi Sedang</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-600">
                            <span class="flex items-center gap-1.5 font-mono text-xs">
                                <x-icon name="badge" class="h-4 w-4 text-gray-400" />
                                Kategori: <strong class="text-gray-900">{{ $kasus->kategori_masalah }}</strong>
                            </span>
                            <span class="flex items-center gap-1.5 text-xs">
                                <x-icon name="support_agent" class="h-4 w-4 text-brand-500" />
                                Konselor: <strong class="text-gray-900">{{ $kasus->konselorGuru?->nama ?? $kasus->konselorKaryawan?->nama ?? 'Menunggu Triase' }}</strong>
                            </span>
                            <span class="flex items-center gap-1.5 text-xs">
                                <x-icon name="apartment" class="h-4 w-4 text-gray-400" />
                                <strong class="text-gray-800">{{ $kasus->siswa->lembaga->nama ?? '-' }}</strong>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col items-end gap-2 shrink-0">
                    <x-badge :tone="$kasus->status->badgeTone()" class="px-3 py-1 text-xs font-bold uppercase tracking-wider shadow-sm">
                        {{ $kasus->status->label() }}
                    </x-badge>
                    @can('kasus.hapus')
                        @if ($kasus->status === \App\Domains\Kasus\Enums\StatusKasus::Selesai)
                            <form method="POST" action="{{ route('admin.kasus.destroy', $kasus) }}" x-data @submit.prevent="confirmDialog('Hapus Kasus?', 'Apakah Anda yakin ingin menghapus kasus ini? Seluruh sesi, tugas, dan evaluasi terkait juga akan terhapus permanen.', { confirmLabel: 'Ya, Hapus Kasus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-error-200 bg-error-50 px-3 py-1.5 text-xs font-bold text-error-600 transition hover:bg-error-100" title="Hapus Kasus">
                                    <x-icon name="delete" class="h-4 w-4" />
                                    <span>Hapus Kasus</span>
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>

            {{-- Navigation Tabs Header --}}
            <div class="relative mt-8 flex border-b border-gray-200 overflow-x-auto text-sm font-semibold text-gray-500 scrollbar-none">
                <button type="button" @click="activeTab = 'info'" :class="activeTab === 'info' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <x-icon name="info" class="h-4 w-4" />
                    <span>Info & Consent</span>
                    @if ($isKontakUtama && $kasus->consents->where('status', '!=', 'disetujui')->isNotEmpty())
                        <span class="inline-flex h-2 w-2 rounded-full bg-amber-400 animate-ping"></span>
                    @endif
                </button>

                @if ($isKonselor || $isSiswaTerkait || $isKontakUtama || $isTriaseAdmin)
                    <button type="button" @click="activeTab = 'sesi'" :class="activeTab === 'sesi' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                        <x-icon name="event" class="h-4 w-4" />
                        <span>Sesi Pendampingan</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 font-mono" :class="activeTab === 'sesi' ? 'bg-brand-100 text-brand-800' : ''">{{ $kasus->sesi->count() }}</span>
                    </button>

                    <button type="button" @click="activeTab = 'tugas'" :class="activeTab === 'tugas' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                        <x-icon name="assignment" class="h-4 w-4" />
                        <span>Tugas & Refleksi</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 font-mono" :class="activeTab === 'tugas' ? 'bg-brand-100 text-brand-800' : ''">{{ $kasus->tugas->count() }}</span>
                    </button>
                @endif

                @if ($isKonselor || $isTriaseAdmin)
                    <button type="button" @click="activeTab = 'evaluasi'" :class="activeTab === 'evaluasi' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                        <x-icon name="fact_check" class="h-4 w-4" />
                        <span>Evaluasi Kasus</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 font-mono" :class="activeTab === 'evaluasi' ? 'bg-brand-100 text-brand-800' : ''">{{ $kasus->evaluasi->count() }}</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Content Area for Tabs --}}
        <div class="mt-6">
            {{-- Tab Partials Inclusions --}}
            <div x-show="activeTab === 'info'" x-transition:enter="transition ease-out duration-200">
                @include('portals.kasus.partials._tab-info')
            </div>

            @if ($isKonselor || $isSiswaTerkait || $isKontakUtama || $isTriaseAdmin)
                <div x-show="activeTab === 'sesi'" style="display: none;" x-transition:enter="transition ease-out duration-200">
                    @include('portals.kasus.partials._tab-sesi')
                </div>
                <div x-show="activeTab === 'tugas'" style="display: none;" x-transition:enter="transition ease-out duration-200">
                    @include('portals.kasus.partials._tab-tugas')
                </div>
            @endif

            @if ($isKonselor || $isTriaseAdmin)
                <div x-show="activeTab === 'evaluasi'" style="display: none;" x-transition:enter="transition ease-out duration-200">
                    @include('portals.kasus.partials._tab-evaluasi')
                </div>
            @endif
        </div>


    </div>
</x-app-layout>

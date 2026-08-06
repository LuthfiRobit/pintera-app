<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-5">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast ? $store.toast.push('success', @js(session('status'))) : null">{{ session('status') }}</div>
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

        {{-- Always-Visible Header Overview Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-100 pb-4">
                <div class="flex items-center gap-3.5">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 font-bold text-lg shadow-2xs">
                        {{ strtoupper(substr($kasus->siswa->nama_lengkap ?? 'S', 0, 1)) }}
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-display text-base font-bold text-gray-900">{{ $kasus->siswa->nama_lengkap }}</h2>
                            @if ($kasus->tingkat_urgensi === 'tinggi')
                                <span class="rounded bg-red-100 px-2 py-0.5 text-[10px] font-extrabold text-red-800 border border-red-200">URGENSI TINGGI</span>
                            @elseif ($kasus->tingkat_urgensi === 'sedang')
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200">Urgensi Sedang</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Kategori: <b class="text-gray-700 font-semibold">{{ $kasus->kategori_masalah }}</b> &bull; Diajukan pada <b class="text-gray-700 font-semibold">{{ $kasus->created_at->format('d M Y') }}</b></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <x-badge :tone="$kasus->status->badgeTone()" class="px-3 py-1 text-xs font-bold uppercase tracking-wider">
                        {{ $kasus->status->label() }}
                    </x-badge>
                    @can('kasus.hapus')
                        @if ($kasus->status === \App\Enums\StatusKasus::Selesai)
                            <form method="POST" action="{{ route('admin.kasus.destroy', $kasus) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kasus ini? Seluruh sesi, tugas, dan evaluasi terkait juga akan dihapus.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-error-200 bg-error-50 px-3 py-1.5 text-xs font-bold text-error-600 transition hover:bg-error-100" title="Hapus Kasus">
                                    <x-icon name="delete" class="h-4 w-4" />
                                    <span>Hapus Kasus</span>
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-y-2 gap-x-6 pt-3 text-xs text-gray-600">
                <div class="flex items-center gap-1.5">
                    <x-icon name="support_agent" class="h-4 w-4 text-brand-500" />
                    <span>Konselor Penanggung Jawab:</span>
                    <b class="font-bold text-gray-900">{{ $kasus->konselorGuru?->nama ?? $kasus->konselorKaryawan?->nama ?? 'Belum Ditugaskan / Menunggu Triase' }}</b>
                </div>
                <div class="flex items-center gap-1.5">
                    <x-icon name="apartment" class="h-4 w-4 text-gray-400" />
                    <span>Institusi:</span>
                    <b class="font-semibold text-gray-800">{{ $kasus->siswa->lembaga->nama ?? '-' }}</b>
                </div>
            </div>
        </div>

        {{-- Tab Navigation & Modular Content Container --}}
        <div x-data="{ activeTab: '{{ old('frekuensi') !== null ? 'tugas' : 'info' }}', showSesiForm: false, showTugasForm: false }" class="space-y-6">
            {{-- Navigation Pill Bar --}}
            <div class="flex items-center gap-2 overflow-x-auto rounded-xl border border-gray-200 bg-white p-2 shadow-2xs scrollbar-none">
                <button
                    type="button"
                    @click="activeTab = 'info'"
                    :class="activeTab === 'info' ? 'bg-brand-500 text-white font-bold shadow-sm' : 'bg-transparent text-gray-600 hover:bg-gray-50 font-semibold'"
                    class="flex items-center gap-2 rounded-lg px-4 py-2 text-xs sm:text-sm transition whitespace-nowrap"
                >
                    <x-icon name="info" class="h-4 w-4" />
                    <span>Info & Consent</span>
                    @if ($isKontakUtama && $kasus->consents->where('status', '!=', 'disetujui')->isNotEmpty())
                        <span class="inline-flex h-2 w-2 rounded-full bg-amber-400 animate-ping"></span>
                    @endif
                </button>

                @if ($isKonselor || $isSiswaTerkait || $isKontakUtama || $isTriaseAdmin)
                    <button
                        type="button"
                        @click="activeTab = 'sesi'"
                        :class="activeTab === 'sesi' ? 'bg-brand-500 text-white font-bold shadow-sm' : 'bg-transparent text-gray-600 hover:bg-gray-50 font-semibold'"
                        class="flex items-center gap-2 rounded-lg px-4 py-2 text-xs sm:text-sm transition whitespace-nowrap"
                    >
                        <x-icon name="event" class="h-4 w-4" />
                        <span>Sesi Pendampingan</span>
                        @if ($kasus->sesi->isNotEmpty())
                            <span :class="activeTab === 'sesi' ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-700'" class="rounded-full px-2 py-0.5 text-[10px] font-extrabold">{{ $kasus->sesi->count() }}</span>
                        @endif
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'tugas'"
                        :class="activeTab === 'tugas' ? 'bg-brand-500 text-white font-bold shadow-sm' : 'bg-transparent text-gray-600 hover:bg-gray-50 font-semibold'"
                        class="flex items-center gap-2 rounded-lg px-4 py-2 text-xs sm:text-sm transition whitespace-nowrap"
                    >
                        <x-icon name="assignment" class="h-4 w-4" />
                        <span>Tugas & Refleksi</span>
                        @if ($kasus->tugas->isNotEmpty())
                            <span :class="activeTab === 'tugas' ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-700'" class="rounded-full px-2 py-0.5 text-[10px] font-extrabold">{{ $kasus->tugas->count() }}</span>
                        @endif
                    </button>
                @endif

                @if ($isKonselor || $isTriaseAdmin)
                    <button
                        type="button"
                        @click="activeTab = 'evaluasi'"
                        :class="activeTab === 'evaluasi' ? 'bg-brand-500 text-white font-bold shadow-sm' : 'bg-transparent text-gray-600 hover:bg-gray-50 font-semibold'"
                        class="flex items-center gap-2 rounded-lg px-4 py-2 text-xs sm:text-sm transition whitespace-nowrap"
                    >
                        <x-icon name="fact_check" class="h-4 w-4" />
                        <span>Evaluasi Kasus</span>
                        @if ($kasus->evaluasi->isNotEmpty())
                            <span :class="activeTab === 'evaluasi' ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-700'" class="rounded-full px-2 py-0.5 text-[10px] font-extrabold">{{ $kasus->evaluasi->count() }}</span>
                        @endif
                    </button>
                @endif
            </div>

            {{-- Tab Partials Inclusions --}}
            <div x-show="activeTab === 'info'" x-transition:enter="transition ease-out duration-200">
                @include('kasus.partials._tab-info')
            </div>

            @if ($isKonselor || $isSiswaTerkait || $isKontakUtama || $isTriaseAdmin)
                <div x-show="activeTab === 'sesi'" style="display: none;" x-transition:enter="transition ease-out duration-200">
                    @include('kasus.partials._tab-sesi')
                </div>
                <div x-show="activeTab === 'tugas'" style="display: none;" x-transition:enter="transition ease-out duration-200">
                    @include('kasus.partials._tab-tugas')
                </div>
            @endif

            @if ($isKonselor || $isTriaseAdmin)
                <div x-show="activeTab === 'evaluasi'" style="display: none;" x-transition:enter="transition ease-out duration-200">
                    @include('kasus.partials._tab-evaluasi')
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

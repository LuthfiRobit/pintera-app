{{-- resources/views/kasus/show.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Kasus: {{ $kasus->siswa->nama_lengkap }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('kasus.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Kasus Pendampingan</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Detail</b>
            </p>
        </div>

        <div x-data="{ tab: 'overview' }" class="space-y-4">
            {{-- Navigation Pills / Tabs --}}
            <div class="flex flex-wrap gap-2 rounded-xl border border-gray-200 bg-white p-2 shadow-sm">
                <button
                    type="button"
                    @click="tab = 'overview'"
                    :class="tab === 'overview' ? 'bg-brand-500 text-white font-semibold shadow-sm' : 'bg-transparent text-gray-600 hover:bg-gray-50 font-medium'"
                    class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm transition"
                >
                    <x-icon name="info" class="h-4 w-4" />
                    Ikhtisar & Consent
                </button>
                @if ($isKonselor || $isSiswaTerkait || $isKontakUtama || $isTriaseAdmin)
                    <button
                        type="button"
                        @click="tab = 'sesi'"
                        :class="tab === 'sesi' ? 'bg-brand-500 text-white font-semibold shadow-sm' : 'bg-transparent text-gray-600 hover:bg-gray-50 font-medium'"
                        class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm transition"
                    >
                        <x-icon name="event" class="h-4 w-4" />
                        Sesi Pendampingan
                        @if ($kasus->sesi->isNotEmpty())
                            <span :class="tab === 'sesi' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-0.5 text-xs font-semibold">{{ $kasus->sesi->count() }}</span>
                        @endif
                    </button>
                    <button
                        type="button"
                        @click="tab = 'tugas'"
                        :class="tab === 'tugas' ? 'bg-brand-500 text-white font-semibold shadow-sm' : 'bg-transparent text-gray-600 hover:bg-gray-50 font-medium'"
                        class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm transition"
                    >
                        <x-icon name="assignment" class="h-4 w-4" />
                        Tugas Pendampingan
                        @if ($kasus->tugas->isNotEmpty())
                            <span :class="tab === 'tugas' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-0.5 text-xs font-semibold">{{ $kasus->tugas->count() }}</span>
                        @endif
                    </button>
                @endif
                @if ($isKonselor || $isTriaseAdmin)
                    <button
                        type="button"
                        @click="tab = 'evaluasi'"
                        :class="tab === 'evaluasi' ? 'bg-brand-500 text-white font-semibold shadow-sm' : 'bg-transparent text-gray-600 hover:bg-gray-50 font-medium'"
                        class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm transition"
                    >
                        <x-icon name="fact_check" class="h-4 w-4" />
                        Evaluasi
                        @if ($kasus->evaluasi->isNotEmpty())
                            <span :class="tab === 'evaluasi' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-0.5 text-xs font-semibold">{{ $kasus->evaluasi->count() }}</span>
                        @endif
                    </button>
                @endif
            </div>

            <div x-show="tab === 'overview'">
                @include('kasus.partials.overview')
            </div>

            @if ($isKonselor || $isSiswaTerkait || $isKontakUtama || $isTriaseAdmin)
                <div x-show="tab === 'sesi'" style="display: none;" x-show.transition="tab === 'sesi'">
                    @include('kasus.partials.sesi')
                </div>
                <div x-show="tab === 'tugas'" style="display: none;" x-show.transition="tab === 'tugas'">
                    @include('kasus.partials.tugas')
                </div>
            @endif

            @if ($isKonselor || $isTriaseAdmin)
                <div x-show="tab === 'evaluasi'" style="display: none;" x-show.transition="tab === 'evaluasi'">
                    @include('kasus.partials.evaluasi')
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

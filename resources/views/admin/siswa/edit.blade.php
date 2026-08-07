<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6" x-data="{
        activeTab: 'profil',
        editMode: {{ $errors->any() ? 'true' : 'false' }}
    }">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm font-medium text-success-700 shadow-sm">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm font-medium text-error-700 shadow-sm" x-init="$store.toast ? $store.toast.push('error', @js($errors->first())) : null">Terdapat kesalahan pengisian data, silakan periksa kembali formulir di bawah.</div>
        @endif

        {{-- Top Navigation & Breadcrumbs --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-gray-500">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                    <a href="{{ route('admin.siswa.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Siswa</a>
                    <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-900">Detail Profil Siswa</b>
                </p>
            </div>
            <a href="{{ route('admin.siswa.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
                <x-icon name="arrow_back" class="h-4 w-4 text-gray-500" />
                <span>Kembali ke Daftar</span>
            </a>
        </div>

        {{-- Hero Summary Profile Card --}}
        <div class="relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card md:p-8">
            <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-gradient-to-br from-brand-50 to-blue-50 opacity-60 blur-xl"></div>
            <div class="relative flex flex-col gap-6 md:flex-row md:items-center">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-blue-500 text-2xl font-bold text-white shadow-md">
                    {{ strtoupper(substr($siswa->nama_lengkap, 0, 2)) }}
                </div>
                <div class="flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="font-display text-2xl font-bold tracking-tight text-gray-900">{{ $siswa->nama_lengkap }}</h1>
                        @php
                            $statusBadge = match($siswa->status->value) {
                                'aktif' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'lulus' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'pindah' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'keluar' => 'bg-red-50 text-red-700 border-red-200',
                                default => 'bg-gray-50 text-gray-700 border-gray-200',
                            };
                        @endphp
                        <span class="rounded-full border px-3 py-0.5 text-xs font-semibold {{ $statusBadge }}">
                            {{ ucfirst($siswa->status->value) }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-600">
                        <span class="flex items-center gap-1.5 font-mono">
                            <x-icon name="badge" class="h-4 w-4 text-gray-400" />
                            NIS: <strong class="text-gray-900">{{ $siswa->nis ?: '-' }}</strong>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <x-icon name="school" class="h-4 w-4 text-gray-400" />
                            Kelas: <strong class="text-gray-900">{{ $siswa->kelas?->nama ?? 'Belum ditempatkan' }}</strong>
                        </span>
                        @if ($siswa->user)
                            <span class="flex items-center gap-1.5">
                                <x-icon name="{{ $siswa->user->is_active ? 'check_circle' : 'cancel' }}" class="h-4 w-4 {{ $siswa->user->is_active ? 'text-brand-600' : 'text-gray-400' }}" />
                                <span class="text-xs text-gray-500">Akun: {{ $siswa->user->is_active ? 'Aktif' : 'Non-aktif' }}</span>
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Navigation Tabs Header --}}
            <div class="mt-8 flex border-b border-gray-200 overflow-x-auto text-sm font-semibold text-gray-500 scrollbar-none">
                <button type="button" @click="activeTab = 'profil'" :class="activeTab === 'profil' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <x-icon name="person" class="h-4 w-4" />
                    <span>Profil & Identitas</span>
                </button>
                <button type="button" @click="activeTab = 'orang-tua'; editMode = false" :class="activeTab === 'orang-tua' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <x-icon name="family_restroom" class="h-4 w-4" />
                    <span>Orang Tua Tertaut</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 font-mono" :class="activeTab === 'orang-tua' ? 'bg-brand-100 text-brand-800' : ''">{{ $siswa->orangTua->count() }}</span>
                </button>
            </div>
        </div>

        {{-- Content Area for Tabs --}}
        <div class="mt-6">
            @include('admin.siswa.tabs.profil')
            @include('admin.siswa.tabs.orang-tua')
        </div>
    </div>
</x-app-layout>


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
                    <a href="{{ route('admin.orang-tua.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Orang Tua</a>
                    <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-900">Detail Profil Orang Tua</b>
                </p>
            </div>
            <a href="{{ route('admin.orang-tua.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
                <x-icon name="arrow_back" class="h-4 w-4 text-gray-500" />
                <span>Kembali ke Daftar</span>
            </a>
        </div>

        {{-- Hero Summary Profile Card --}}
        <div class="relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card md:p-8">
            <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-gradient-to-br from-brand-50 to-orange-50 opacity-60 blur-xl"></div>
            <div class="relative flex flex-col gap-6 md:flex-row md:items-center">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-orange-500 text-2xl font-bold text-white shadow-md">
                    {{ strtoupper(substr($orangTua->nama_lengkap, 0, 2)) }}
                </div>
                <div class="flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="font-display text-2xl font-bold tracking-tight text-gray-900">{{ $orangTua->nama_lengkap }}</h1>
                        @php
                            $statusBadge = $orangTua->user->is_active
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                : 'bg-amber-50 text-amber-700 border-amber-200';
                        @endphp
                        <span class="rounded-full border px-3 py-0.5 text-xs font-semibold {{ $statusBadge }}">
                            {{ $orangTua->user->is_active ? 'Akun Aktif' : 'Akun Non-aktif' }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-600">
                        <span class="flex items-center gap-1.5 font-mono">
                            <x-icon name="badge" class="h-4 w-4 text-gray-400" />
                            NIK: <strong class="text-gray-900">{{ $orangTua->nik ?: '-' }}</strong>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <x-icon name="work" class="h-4 w-4 text-gray-400" />
                            Pekerjaan: <strong class="text-gray-900">{{ $orangTua->pekerjaan ?: '-' }}</strong>
                        </span>
                        @if ($orangTua->user)
                            <span class="flex items-center gap-1.5">
                                <x-icon name="check_circle" class="h-4 w-4 text-brand-600" />
                                <span class="text-xs text-gray-500">Username: {{ $orangTua->user->username }}</span>
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
                <button type="button" @click="activeTab = 'siswa'; editMode = false" :class="activeTab === 'siswa' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <x-icon name="groups" class="h-4 w-4" />
                    <span>Anak Tertaut</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 font-mono" :class="activeTab === 'siswa' ? 'bg-brand-100 text-brand-800' : ''">{{ $orangTua->siswa->count() }}</span>
                </button>
            </div>
        </div>

        {{-- Content Area for Tabs --}}
        <div class="mt-6">
            @include('admin.orang-tua.tabs.profil')
            @include('admin.orang-tua.tabs.siswa')
        </div>
    </div>
</x-app-layout>

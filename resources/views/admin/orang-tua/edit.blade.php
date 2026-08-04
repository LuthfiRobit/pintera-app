<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Orang Tua: {{ $orangTua->nama_lengkap }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.orang-tua.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Orang Tua</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700">
                <x-icon name="lock" class="h-[15px] w-[15px] text-gray-400" />
                Info Akun Login
            </p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold text-gray-500">Username</p>
                    <p class="mt-1 font-mono text-sm text-gray-900">{{ $orangTua->user->username }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500">Status Akun</p>
                    <div class="mt-1 flex items-center gap-3">
                        <x-badge tone="{{ $orangTua->user->is_active ? 'green' : 'amber' }}">{{ $orangTua->user->is_active ? 'Aktif' : 'Non-aktif' }}</x-badge>
                        <form
                            method="POST"
                            action="{{ route('admin.orang-tua.update-status', $orangTua) }}"
                            x-data
                            @submit.prevent="confirmDialog('Ubah Status Akun?', @js('Ubah status akun \"' . $orangTua->nama_lengkap . '\" menjadi \"' . ($orangTua->user->is_active ? 'Non-aktif' : 'Aktif') . '\"?'), { confirmLabel: 'Ya, Ubah' }).then(confirmed => { if (confirmed) $el.submit() })"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="is_active" value="{{ $orangTua->user->is_active ? '0' : '1' }}">
                            <button type="submit" class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                                Jadikan {{ $orangTua->user->is_active ? 'Non-aktif' : 'Aktif' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if ($orangTua->siswa->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="groups" class="h-[15px] w-[15px] text-gray-400" />
                    Siswa Tertaut
                </p>
                <ul class="space-y-1 text-sm text-gray-700">
                    @foreach ($orangTua->siswa as $siswa)
                        <li class="flex items-center gap-2">
                            <span>{{ $siswa->nama_lengkap }}</span>
                            <x-badge tone="slate" class="text-xs">{{ ucfirst($siswa->pivot->hubungan) }}</x-badge>
                            @if ($siswa->pivot->is_kontak_utama)
                                <x-badge tone="green" class="text-xs">Kontak Utama</x-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.orang-tua.update', $orangTua) }}">
            @csrf
            @method('PUT')
            @include('admin.orang-tua._form', ['orangTua' => $orangTua])

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                <a href="{{ route('admin.orang-tua.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>

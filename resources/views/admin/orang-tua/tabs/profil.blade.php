<div x-show="activeTab === 'profil'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
    {{-- Mode Lihat (View Mode) --}}
    <div x-show="!editMode" class="space-y-6">
        <div class="flex items-center justify-between rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Informasi Profil Lengkap</h2>
                <p class="text-sm text-gray-500">Data identitas resmi dan kontak orang tua/wali.</p>
            </div>
            @can('orang-tua.edit')
                <button type="button" @click="editMode = true" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                    <x-icon name="edit" class="h-4 w-4" />
                    <span>Edit Profil</span>
                </button>
            @endcan
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            {{-- Card Akun & Identitas --}}
            <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card transition-all hover:border-gray-300">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                            <x-icon name="person" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Identitas & Kontak</h3>
                            <p class="text-xs text-gray-400">Data resmi dan cara menghubungi</p>
                        </div>
                    </div>
                </div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Nama Lengkap</dt>
                        <dd class="font-medium text-gray-900">{{ $orangTua->nama_lengkap }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">NIK</dt>
                        <dd class="font-mono text-gray-900">{{ $orangTua->nik ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">No. HP / WhatsApp</dt>
                        <dd class="font-mono text-gray-900">{{ $orangTua->no_hp ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Email Akses</dt>
                        <dd class="text-gray-900">{{ $orangTua->email ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Pekerjaan</dt>
                        <dd class="text-gray-900">{{ $orangTua->pekerjaan ?: '-' }}</dd>
                    </div>
                    <div class="flex flex-col gap-1 py-2.5">
                        <dt class="text-gray-500">Alamat Tempat Tinggal</dt>
                        <dd class="text-gray-900">{{ $orangTua->alamat ?: '-' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Card Info Akun Login --}}
            <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card transition-all hover:border-gray-300">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                            <x-icon name="lock" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Info Akun Login</h3>
                            <p class="text-xs text-gray-400">Data autentikasi ke portal</p>
                        </div>
                    </div>
                </div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Username</dt>
                        <dd class="font-mono font-medium text-brand-600">{{ $orangTua->user->username }}</dd>
                    </div>
                    <div class="flex justify-between items-center py-2.5">
                        <dt class="text-gray-500">Status Akun</dt>
                        <dd class="flex items-center gap-3">
                            <x-badge tone="{{ $orangTua->user->is_active ? 'green' : 'amber' }}">{{ $orangTua->user->is_active ? 'Aktif' : 'Non-aktif' }}</x-badge>
                            
                            @can('orang-tua.edit')
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
                            @endcan
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    {{-- Mode Edit (Edit Mode) --}}
    <div x-show="editMode" class="space-y-4" style="display: none;">
        <div class="flex items-center justify-between rounded-2xl border border-brand-100 bg-brand-50/50 p-5">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white shadow">
                    <x-icon name="edit" class="h-5 w-5" />
                </div>
                <div>
                    <h3 class="font-bold text-gray-900">Mode Pengemasan & Perubahan Profil</h3>
                    <p class="text-xs text-gray-500">Ubah data profil sesuai dengan identitas resmi.</p>
                </div>
            </div>
            <button type="button" @click="editMode = false" class="rounded-lg border border-gray-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 active:scale-95">
                Batal Edit
            </button>
        </div>

        <form method="POST" action="{{ route('admin.orang-tua.update', $orangTua) }}" class="space-y-4">
            @csrf
            @method('PUT')

            @include('admin.orang-tua._form', ['orangTua' => $orangTua])

            <div class="flex items-center justify-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-card">
                <button type="button" @click="editMode = false" class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 active:scale-95">
                    Batal
                </button>
                <x-primary-button type="submit" class="!rounded-xl !px-6 !py-2.5">
                    Simpan Perubahan
                </x-primary-button>
            </div>
        </form>
    </div>
</div>

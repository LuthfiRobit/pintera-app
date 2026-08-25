<div x-show="activeTab === 'profil'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
    {{-- Mode Lihat (View Mode) --}}
    <div x-show="!editMode" class="space-y-6">
        <div class="flex items-center justify-between rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Informasi Akses Pengguna</h2>
                <p class="text-sm text-gray-500">Detail identitas login dan hak akses pada sistem.</p>
            </div>
            @can('users.edit')
                <button type="button" @click="editMode = true" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                    <x-icon name="edit" class="h-4 w-4" />
                    <span>Edit Profil</span>
                </button>
            @endcan
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            {{-- Card Identitas --}}
            <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card transition-all hover:border-gray-300">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                            <x-icon name="person" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Identitas Diri</h3>
                            <p class="text-xs text-gray-400">Nama lengkap pengguna</p>
                        </div>
                    </div>
                </div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Nama Lengkap</dt>
                        <dd class="font-medium text-gray-900">{{ $targetUser->name }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Tanggal Terdaftar</dt>
                        <dd class="text-gray-900">{{ $targetUser->created_at?->translatedFormat('d F Y') ?: '-' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Card Akun Login & Peran --}}
            <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card transition-all hover:border-gray-300">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <x-icon name="admin_panel_settings" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Data Akses</h3>
                            <p class="text-xs text-gray-400">Email dan peruntukan wewenang</p>
                        </div>
                    </div>
                </div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Email Login</dt>
                        <dd class="font-medium text-gray-900">{{ $targetUser->email }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Role / Peran Akses</dt>
                        <dd class="text-gray-900">{{ $targetUser->functionalRoles()->pluck('name')->map(fn($name) => ucwords(str_replace('_', ' ', $name)))->implode(', ') ?: 'Tidak Ada Akses' }}</dd>
                    </div>
                    @if ($targetUser->lembaga_id)
                        <div class="flex justify-between py-2.5">
                            <dt class="text-gray-500">Lembaga Tertaut</dt>
                            <dd class="text-gray-900">{{ $targetUser->lembaga?->nama ?: '—' }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    {{-- Mode Edit (Edit Form) --}}
    <div x-show="editMode" style="display: none;">
        <form method="POST" action="{{ route('admin.users.update', $targetUser) }}" class="space-y-6">
            @csrf
            @method('PUT')
            
            <div class="rounded-2xl border border-brand-100 bg-brand-50/50 p-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white shadow">
                        <x-icon name="edit_document" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900">Ubah Data Pengguna</h3>
                        <p class="text-xs text-gray-500">Pastikan email aktif dan hak akses sesuai dengan wewenang yang diberikan.</p>
                    </div>
                </div>
            </div>

            @include('admin.users._form')

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

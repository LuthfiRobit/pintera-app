<div x-show="activeTab === 'profil'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
    {{-- Mode Lihat (View Mode) --}}
    <div x-show="!editMode" class="space-y-6">
        <div class="flex items-center justify-between rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Informasi Profil Lengkap</h2>
                <p class="text-sm text-gray-500">Data akun, identitas, dan penempatan karyawan.</p>
            </div>
            @can('karyawan.edit')
                <button type="button" @click="editMode = true" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                    <x-icon name="edit" class="h-4 w-4" />
                    <span>Edit Profil</span>
                </button>
            @endcan
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            {{-- Card Akun & Identitas --}}
            <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card transition-all hover:border-gray-300">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <x-icon name="person" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Akun & Identitas</h3>
                        <p class="text-xs text-gray-400">Data autentikasi & NIK</p>
                    </div>
                </div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Nama Lengkap</dt>
                        <dd class="font-medium text-gray-900">{{ $karyawan->nama }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">NIK</dt>
                        <dd class="font-mono text-gray-900">{{ $karyawan->nik ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Username Login</dt>
                        <dd class="font-mono font-medium text-brand-600">{{ $karyawan->user->username }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Email Akun</dt>
                        <dd class="text-gray-900">{{ $karyawan->email ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">No. Handphone</dt>
                        <dd class="font-mono text-gray-900">{{ $karyawan->no_hp ?: '-' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Card Penempatan & Jabatan --}}
            <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card transition-all hover:border-gray-300">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <x-icon name="apartment" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Penempatan & Status</h3>
                        <p class="text-xs text-gray-400">Informasi jabatan dan lokasi dinas</p>
                    </div>
                </div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Jenis Karyawan</dt>
                        <dd class="font-medium text-gray-900">{{ $karyawan->jenisKaryawan->nama ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Penempatan</dt>
                        <dd class="text-gray-900 text-right max-w-[60%]">{{ $karyawan->lembaga?->nama ?? 'Karyawan Pool (' . $karyawan->yayasan->nama . ')' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Status Aktif</dt>
                        <dd>
                            @php
                                $statusBadge = match($karyawan->status_aktif) {
                                    'aktif' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'non_aktif' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'berhenti' => 'bg-gray-100 text-gray-600 border-gray-300',
                                    default => 'bg-gray-50 text-gray-700',
                                };
                            @endphp
                            <span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge }}">{{ ucwords(str_replace('_', ' ', $karyawan->status_aktif)) }}</span>
                        </dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Kapasitas Kasus Aktif</dt>
                        <dd class="font-mono text-gray-900">{{ $karyawan->kapasitas_kasus_aktif !== null ? $karyawan->kapasitas_kasus_aktif . ' Kasus' : 'Tidak Dibatasi' }}</dd>
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

        <form method="POST" action="{{ route('admin.karyawan.update', $karyawan) }}" x-data="karyawanForm({ canCreatePool: false })" class="space-y-4">
            @csrf
            @method('PUT')

            @include('admin.karyawan._form', [
                'karyawan' => $karyawan,
                'jenisKaryawanList' => $jenisKaryawanList,
                'yayasanList' => collect(),
                'canCreatePool' => false,
            ])

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

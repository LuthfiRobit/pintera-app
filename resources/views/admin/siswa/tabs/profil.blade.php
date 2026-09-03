<div x-show="activeTab === 'profil'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
    {{-- Mode Lihat (View Mode) --}}
    <div x-show="!editMode" class="space-y-6">
        <div class="flex items-center justify-between rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Informasi Induk & Akademik</h2>
                <p class="text-sm text-gray-500">Data kelahiran, nomor induk, serta penempatan kelas.</p>
            </div>
            @can('siswa.edit')
                <button type="button" @click="editMode = true" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                    <x-icon name="edit" class="h-4 w-4" />
                    <span>Edit Data Induk</span>
                </button>
            @endcan
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            {{-- Card Identitas Diri --}}
            <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card transition-all hover:border-gray-300">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                            <x-icon name="person" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Identitas Diri</h3>
                            <p class="text-xs text-gray-400">Data pribadi siswa</p>
                        </div>
                    </div>
                </div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Nama Lengkap</dt>
                        <dd class="font-medium text-gray-900">{{ $siswa->nama_lengkap }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Jenis Kelamin</dt>
                        <dd class="text-gray-900">{{ $siswa->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Tempat Lahir</dt>
                        <dd class="text-gray-900">{{ $siswa->tempat_lahir ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Tanggal Lahir</dt>
                        <dd class="text-gray-900">{{ optional($siswa->tanggal_lahir)->format('d F Y') ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Agama</dt>
                        <dd class="text-gray-900">{{ $siswa->agama ?: '-' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="space-y-6">
                {{-- Card Akademik & Nomor Induk --}}
                <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card transition-all hover:border-gray-300">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                <x-icon name="school" class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Akademik</h3>
                                <p class="text-xs text-gray-400">Nomor induk dan kelas</p>
                            </div>
                        </div>
                    </div>
                    <dl class="divide-y divide-gray-100 text-sm">
                        <div class="flex justify-between py-2.5">
                            <dt class="text-gray-500">NIS (Lokal)</dt>
                            <dd class="font-mono text-gray-900">{{ $siswa->nis ?: '-' }}</dd>
                        </div>
                        <div class="flex justify-between py-2.5">
                            <dt class="text-gray-500">NISN (Nasional)</dt>
                            <dd class="font-mono text-gray-900">{{ $siswa->nisn ?: '-' }}</dd>
                        </div>
                        <div class="flex justify-between py-2.5">
                            <dt class="text-gray-500">Rombel / Kelas Aktif</dt>
                            <dd class="font-medium text-brand-600">
                                {{ $siswa->kelas_efektif?->nama ?? 'Belum ada kelas' }}
                                @if (! $siswa->kelas && $siswa->kelasTerakhir)
                                    <span class="text-xs text-gray-400">(kelas terakhir)</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Card Info Akun Login --}}
                @if ($siswa->user)
                    <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card transition-all hover:border-gray-300">
                        <div class="mb-5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                                    <x-icon name="lock" class="h-5 w-5" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Info Akun Login</h3>
                                    <p class="text-xs text-gray-400">Data autentikasi ke portal siswa</p>
                                </div>
                            </div>
                        </div>
                        <dl class="divide-y divide-gray-100 text-sm">
                            <div class="flex justify-between py-2.5">
                                <dt class="text-gray-500">Username</dt>
                                <dd class="font-mono font-medium text-brand-600">{{ $siswa->user->username }}</dd>
                            </div>
                            <div class="flex justify-between items-center py-2.5">
                                <dt class="text-gray-500">Status Akun</dt>
                                <dd class="flex items-center gap-3">
                                    <x-badge tone="{{ $siswa->user->is_active ? 'green' : 'amber' }}">{{ $siswa->user->is_active ? 'Aktif' : 'Non-aktif' }}</x-badge>
                                </dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-xs text-gray-500 border-t border-gray-100 pt-3">Password awal sama dengan NIS. Siswa wajib menggantinya saat login pertama kali.</p>
                    </div>
                @endif
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
                    <h3 class="font-bold text-gray-900">Mode Pengubahan Data Siswa</h3>
                    <p class="text-xs text-gray-500">Perbarui data pokok siswa sesuai dengan Dapodik atau berkas resmi.</p>
                </div>
            </div>
            <button type="button" @click="editMode = false" class="rounded-lg border border-gray-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 active:scale-95">
                Batal Edit
            </button>
        </div>

        <form method="POST" action="{{ route('admin.siswa.update', $siswa) }}" class="space-y-4">
            @csrf
            @method('PUT')

            @include('admin.siswa._form', ['siswa' => $siswa, 'submitText' => 'Simpan Perubahan'])
        </form>
    </div>
</div>

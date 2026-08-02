<div x-show="activeTab === 'profil'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
    {{-- Mode Lihat (View Mode - Cognitive Silence & Premium Layout) --}}
    <div x-show="!editMode" class="space-y-6">
        <div class="flex items-center justify-between rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Informasi Profil Lengkap</h2>
                <p class="text-sm text-gray-500">Data akun, identitas resmi, alamat, dan kepegawaian guru.</p>
            </div>
            @can('guru.edit')
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
                        <dd class="font-medium text-gray-900">{{ $guru->nama }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">NIK</dt>
                        <dd class="font-mono text-gray-900">{{ $guru->nik ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">NIP / ID</dt>
                        <dd class="font-mono font-medium text-brand-600">{{ $guru->nip }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Email Akun</dt>
                        <dd class="text-gray-900">{{ $guru->email ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Jenis Kelamin</dt>
                        <dd class="text-gray-900">{{ $guru->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Jenis PTK</dt>
                        <dd class="text-gray-900">{{ ucwords(str_replace('_', ' ', $guru->jenis_ptk)) }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Card Data Pribadi --}}
            <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card transition-all hover:border-gray-300">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                        <x-icon name="description" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Data Pribadi</h3>
                        <p class="text-xs text-gray-400">Informasi demografi & kontak</p>
                    </div>
                </div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">NUPTK</dt>
                        <dd class="font-mono text-gray-900">{{ $guru->nuptk ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Tempat, Tanggal Lahir</dt>
                        <dd class="text-gray-900">{{ $guru->tempat_lahir ?: '-' }}, {{ $guru->tanggal_lahir ? \Illuminate\Support\Carbon::parse($guru->tanggal_lahir)->translatedFormat('d F Y') : '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Agama</dt>
                        <dd class="text-gray-900">{{ $guru->agama ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Kewarganegaraan</dt>
                        <dd class="text-gray-900">{{ $guru->kewarganegaraan ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">No. Handphone</dt>
                        <dd class="font-mono text-gray-900">{{ $guru->no_hp ?: '-' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Card Alamat --}}
            <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card transition-all hover:border-gray-300">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <x-icon name="location_on" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Alamat Tempat Tinggal</h3>
                        <p class="text-xs text-gray-400">Domisili saat ini</p>
                    </div>
                </div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Alamat Jalan</dt>
                        <dd class="max-w-xs text-right text-gray-900">{{ $guru->alamat_jalan ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">RT / RW</dt>
                        <dd class="text-gray-900">{{ $guru->rt ?: '00' }} / {{ $guru->rw ?: '00' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Desa / Kelurahan</dt>
                        <dd class="text-gray-900">{{ $guru->desa_kelurahan ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Kecamatan</dt>
                        <dd class="text-gray-900">{{ $guru->kecamatan ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Kabupaten / Kota</dt>
                        <dd class="text-gray-900">{{ $guru->kabupaten_kota ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Provinsi (Kode Pos)</dt>
                        <dd class="text-gray-900">{{ $guru->provinsi ?: '-' }} {{ $guru->kode_pos ? "({$guru->kode_pos})" : '' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Card Kepegawaian --}}
            <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card transition-all hover:border-gray-300">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <x-icon name="checklist" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Kepegawaian</h3>
                        <p class="text-xs text-gray-400">Status & pengangkatan dinas</p>
                    </div>
                </div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Status Kepegawaian</dt>
                        <dd class="font-medium text-gray-900">{{ $guru->status_kepegawaian }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Golongan / Pangkat</dt>
                        <dd class="text-gray-900">{{ $guru->golongan_pangkat ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">TMT Tugas</dt>
                        <dd class="text-gray-900">{{ $guru->tmt_tugas ? \Illuminate\Support\Carbon::parse($guru->tmt_tugas)->translatedFormat('d F Y') : '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">TMT PNS</dt>
                        <dd class="text-gray-900">{{ $guru->tmt_pns ? \Illuminate\Support\Carbon::parse($guru->tmt_pns)->translatedFormat('d F Y') : '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Status Aktif</dt>
                        <dd>
                            @php
                                $statusBadge = match($guru->status_aktif) {
                                    'aktif' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'non_aktif' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'mutasi', 'pensiun' => 'bg-gray-100 text-gray-600 border-gray-300',
                                    default => 'bg-gray-50 text-gray-700',
                                };
                            @endphp
                            <span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge }}">{{ ucwords(str_replace('_', ' ', $guru->status_aktif)) }}</span>
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
                    <p class="text-xs text-gray-500">Pastikan NIK dan NIP valid sesuai arsip resmi sebelum disimpan.</p>
                </div>
            </div>
            <button type="button" @click="editMode = false" class="rounded-lg border border-gray-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 active:scale-95">
                Batal Edit
            </button>
        </div>

        <form method="POST" action="{{ route('admin.guru.update', $guru) }}" class="space-y-4">
            @csrf
            @method('PUT')

            @include('admin.guru._form', [
                'guru' => $guru,
                'jenisKelaminOptions' => $jenisKelaminOptions,
                'jenisPtkOptions' => $jenisPtkOptions,
                'statusKepegawaianOptions' => $statusKepegawaianOptions,
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

<div x-show="activeTab === 'profil'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    {{-- READ-ONLY VIEW MODE --}}
    <div x-show="mode === 'view'" class="space-y-6">
        <div class="flex items-center justify-between rounded-2xl border border-blue-100 bg-gradient-to-r from-blue-50/70 to-indigo-50/50 p-4 text-sm text-blue-800 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                    <x-icon name="info" class="h-5 w-5" />
                </div>
                <div>
                    <span class="font-bold">Mode Lihat Profil</span> &mdash; Anda melihat data profil sekolah secara cepat dan terapis.
                    <p class="text-xs text-blue-600">Klik tombol <span class="font-bold">"Mode Edit Profil"</span> di pojok kanan atas untuk memperbarui informasi identitas, alamat, atau rekening sekolah.</p>
                </div>
            </div>
            <button type="button" @click="mode = 'edit'" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700 active:scale-95">
                <x-icon name="edit" class="h-3.5 w-3.5" />
                Ubah Profil
            </button>
        </div>

        {{-- Identitas Lembaga Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            <div class="mb-5 flex items-center justify-between border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                        <x-icon name="apartment" class="h-4 w-4" />
                    </div>
                    <h3 class="font-display font-bold text-gray-900">Identitas Sekolah</h3>
                </div>
                @if ($lembaga->status_aktif)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200/60">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Aktif Beroperasi
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 border border-rose-200/60">
                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                        Non-Aktif
                    </span>
                @endif
            </div>

            <dl class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Yayasan Naungan</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $lembaga->yayasan->nama ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Nama Resmi Sekolah</dt>
                    <dd class="mt-1 font-bold text-gray-900 text-base">{{ $lembaga->nama }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">NPSN</dt>
                    <dd class="mt-1 font-mono font-bold text-indigo-600 bg-indigo-50/70 inline-block px-2.5 py-0.5 rounded-md text-sm border border-indigo-100">{{ $lembaga->npsn ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">NSS (Nomor Statistik Sekolah)</dt>
                    <dd class="mt-1 font-mono text-gray-800">{{ $lembaga->nss ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Kode Lembaga (Prefix Username)</dt>
                    <dd class="mt-1 font-mono font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded inline-block text-xs border border-emerald-200/50">{{ $lembaga->kode_lembaga ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Bentuk Pendidikan</dt>
                    <dd class="mt-1 font-semibold text-gray-800">{{ $lembaga->bentuk_pendidikan ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Status Sekolah</dt>
                    <dd class="mt-1 font-semibold text-gray-800 capitalize">{{ $lembaga->status_sekolah ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Status Kepemilikan</dt>
                    <dd class="mt-1 text-gray-800">{{ $lembaga->status_kepemilikan ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Naungan & Akreditasi</dt>
                    <dd class="mt-1 text-gray-800 capitalize">
                        <span class="font-medium text-gray-600">{{ $lembaga->naungan ?: '-' }}</span> &bull; 
                        Akreditasi: <strong class="text-brand-600 font-bold">{{ $lembaga->akreditasi ?: '-' }}</strong>
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Legalitas & Kepemimpinan --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Legalitas --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card flex flex-col justify-between">
                <div>
                    <div class="mb-4 flex items-center gap-2.5 border-b border-gray-100 pb-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                            <x-icon name="fact_check" class="h-4 w-4" />
                        </div>
                        <h3 class="font-display font-bold text-gray-900">Legalitas &amp; Perizinan</h3>
                    </div>
                    <dl class="space-y-4">
                        <div class="flex justify-between items-start py-2 border-b border-dashed border-gray-100">
                            <dt class="text-sm font-medium text-gray-500">SK Pendirian</dt>
                            <dd class="text-right">
                                <span class="block font-mono text-sm font-semibold text-gray-900">{{ $lembaga->sk_pendirian_nomor ?: '-' }}</span>
                                @if($lembaga->sk_pendirian_tanggal)
                                    <span class="text-xs text-gray-400">Tgl: {{ \Carbon\Carbon::parse($lembaga->sk_pendirian_tanggal)->translatedFormat('d M Y') }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between items-start py-2 border-b border-dashed border-gray-100">
                            <dt class="text-sm font-medium text-gray-500">SK Izin Operasional</dt>
                            <dd class="text-right">
                                <span class="block font-mono text-sm font-semibold text-gray-900">{{ $lembaga->sk_izin_operasional_nomor ?: '-' }}</span>
                                @if($lembaga->sk_izin_operasional_tanggal)
                                    <span class="text-xs text-gray-400">Tgl: {{ \Carbon\Carbon::parse($lembaga->sk_izin_operasional_tanggal)->translatedFormat('d M Y') }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between items-start py-2">
                            <dt class="text-sm font-medium text-gray-500">SK Akreditasi</dt>
                            <dd class="text-right">
                                <span class="block font-mono text-sm font-semibold text-gray-900">{{ $lembaga->sk_akreditasi_nomor ?: '-' }}</span>
                                @if($lembaga->tanggal_sk_akreditasi)
                                    <span class="text-xs text-gray-400">Tgl: {{ \Carbon\Carbon::parse($lembaga->tanggal_sk_akreditasi)->translatedFormat('d M Y') }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Kepemimpinan & Keuangan --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card flex flex-col justify-between">
                <div>
                    <div class="mb-4 flex items-center gap-2.5 border-b border-gray-100 pb-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <x-icon name="group" class="h-4 w-4" />
                        </div>
                        <h3 class="font-display font-bold text-gray-900">Kepemimpinan &amp; Pengaturan Iuran</h3>
                    </div>
                    <dl class="space-y-4">
                        <div class="flex justify-between items-center py-2 border-b border-dashed border-gray-100">
                            <dt class="text-sm font-medium text-gray-500">Kepala Sekolah</dt>
                            <dd class="font-bold text-gray-900">{{ $lembaga->nama_kepala_sekolah ?: '-' }}</dd>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-dashed border-gray-100">
                            <dt class="text-sm font-medium text-gray-500">Bendahara BOSP</dt>
                            <dd class="font-medium text-gray-800">{{ $lembaga->nama_bendahara_bosp ?: '-' }}</dd>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-dashed border-gray-100">
                            <dt class="text-sm font-medium text-gray-500">MBS (Manajemen Berbasis Sekolah)</dt>
                            <dd>
                                @if($lembaga->mbs)
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                        <x-icon name="check_circle" class="h-3.5 w-3.5 text-emerald-500" /> Aktif
                                    </span>
                                @else
                                    <span class="text-xs font-medium text-gray-400">Tidak Menerapkan</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Alamat & Kontak & Bank --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            <div class="mb-5 flex items-center gap-2.5 border-b border-gray-100 pb-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                    <x-icon name="signpost" class="h-4 w-4" />
                </div>
                <h3 class="font-display font-bold text-gray-900">Alamat, Kontak &amp; Informasi Bank</h3>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">Alamat Fisik</h4>
                    <p class="text-sm text-gray-800 leading-relaxed font-medium">
                        {{ $lembaga->alamat_jalan ?: 'Alamat belum tercatat.' }}<br>
                        @if($lembaga->rt || $lembaga->rw)
                            RT {{ $lembaga->rt ?: '00' }} / RW {{ $lembaga->rw ?: '00' }}@if($lembaga->nama_dusun), Dusun {{ $lembaga->nama_dusun }}@endif<br>
                        @endif
                        @if($lembaga->desa_kelurahan || $lembaga->kecamatan)
                            Kel. {{ $lembaga->desa_kelurahan ?: '-' }}, Kec. {{ $lembaga->kecamatan ?: '-' }}<br>
                        @endif
                        @if($lembaga->kabupaten_kota || $lembaga->provinsi)
                            {{ $lembaga->kabupaten_kota ?: '-' }}, {{ $lembaga->provinsi ?: '-' }} {{ $lembaga->kode_pos }}
                        @endif
                    </p>
                    @if($lembaga->lintang && $lembaga->bujur)
                        <div class="inline-flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-mono text-gray-600 border border-gray-200">
                            <x-icon name="location_on" class="h-4 w-4 text-rose-500" />
                            <span>{{ $lembaga->lintang }}, {{ $lembaga->bujur }}</span>
                        </div>
                    @endif
                </div>

                <div class="space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">Kontak Sekolah</h4>
                    <ul class="space-y-2.5 text-sm">
                        <li class="flex items-center gap-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded bg-gray-50 text-gray-500 border border-gray-200/70">
                                <x-icon name="call" class="h-4 w-4" />
                            </span>
                            <span class="text-gray-700">{{ $lembaga->telepon ?: '-' }} @if($lembaga->fax)(Fax: {{ $lembaga->fax }})@endif</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded bg-gray-50 text-gray-500 border border-gray-200/70">
                                <x-icon name="mail" class="h-4 w-4" />
                            </span>
                            <span class="text-gray-700 font-medium">{{ $lembaga->email ?: '-' }}</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded bg-gray-50 text-gray-500 border border-gray-200/70">
                                <x-icon name="language" class="h-4 w-4" />
                            </span>
                            @if($lembaga->website)
                                <a href="{{ Str::startsWith($lembaga->website, 'http') ? $lembaga->website : 'https://' . $lembaga->website }}" target="_blank" class="text-brand-600 hover:underline font-medium flex items-center gap-1">
                                    {{ $lembaga->website }}
                                    <x-icon name="open_in_new" class="h-3.5 w-3.5 text-gray-400" />
                                </a>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </li>
                    </ul>
                </div>

                <div class="space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">Rekening Bank &amp; Pajak</h4>
                    <div class="rounded-xl border border-amber-100 bg-gradient-to-br from-amber-50/50 to-amber-50/20 p-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-amber-900 uppercase">{{ $lembaga->nama_bank ?: 'Bank Belum Set' }}</span>
                            @if($lembaga->cabang_kcp_unit)
                                <span class="text-xs text-gray-500">{{ $lembaga->cabang_kcp_unit }}</span>
                            @endif
                        </div>
                        <p class="font-mono text-base font-extrabold tracking-wider text-gray-900">{{ $lembaga->nomor_rekening ?: '—' }}</p>
                        <p class="text-xs text-gray-600">a.n. <strong class="text-gray-800">{{ $lembaga->rekening_atas_nama ?: '—' }}</strong></p>
                    </div>
                    @if($lembaga->npwp || $lembaga->nama_wajib_pajak)
                        <div class="text-xs text-gray-600 space-y-0.5 pt-1">
                            <span class="text-gray-400 block">NPWP / Wajib Pajak:</span>
                            <span class="font-mono font-bold text-gray-800">{{ $lembaga->npwp ?: '-' }}</span> 
                            @if($lembaga->nama_wajib_pajak) ({{ $lembaga->nama_wajib_pajak }}) @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- EDIT MODE FORM --}}
    <div x-show="mode === 'edit'" class="space-y-6">
        <div class="flex items-center justify-between rounded-2xl border border-amber-200 bg-amber-50/70 p-4 text-sm text-amber-900 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                    <x-icon name="edit" class="h-5 w-5" />
                </div>
                <div>
                    <span class="font-bold">Mode Edit Aktif</span> &mdash; Anda sedang merubah data utama dan identitas sekolah.
                    <p class="text-xs text-amber-700">Pastikan Kode Lembaga dan NPSN sesuai dengan database Dapodik/Kemenag resmi sekolah.</p>
                </div>
            </div>
            <button type="button" @click="mode = 'view'" class="inline-flex items-center gap-2 rounded-xl border border-amber-300 bg-white px-4 py-2 text-xs font-bold text-gray-700 shadow-sm transition hover:bg-amber-50 active:scale-95">
                <x-icon name="close" class="h-3.5 w-3.5 text-gray-500" />
                Batal Edit
            </button>
        </div>

        <form method="POST" action="{{ route('admin.lembaga.update', $lembaga) }}">
            @csrf
            @method('PUT')

            @include('admin.lembaga._form', ['lembaga' => $lembaga])

            <div class="mt-6 flex items-center justify-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-card">
                <button type="button" @click="mode = 'view'" class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 active:scale-95 transition">
                    Batal
                </button>
                <x-primary-button type="submit" class="px-6 py-2.5 shadow-md hover:shadow-lg transition">
                    Simpan Perubahan Profil
                </x-primary-button>
            </div>
        </form>
    </div>
</div>

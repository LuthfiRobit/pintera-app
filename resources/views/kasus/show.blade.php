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

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-3">
            <div class="flex items-center gap-2">
                <x-badge tone="slate">{{ $kasus->status->label() }}</x-badge>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500">Kategori Masalah</p>
                <p class="mt-1 text-sm text-gray-900">{{ $kasus->kategori_masalah }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500">Deskripsi</p>
                <p class="mt-1 text-sm text-gray-900">{{ $kasus->deskripsi }}</p>
            </div>
            @if ($kasus->konselorGuru || $kasus->konselorKaryawan)
                <div>
                    <p class="text-xs font-semibold text-gray-500">Konselor Penanganan</p>
                    <p class="mt-1 text-sm text-gray-900">{{ $kasus->konselorGuru?->nama ?? $kasus->konselorKaryawan?->nama }}</p>
                </div>
            @endif
        </div>

        @if ($isKontakUtama && $kasus->consents->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-3">
                <p class="font-display text-sm font-bold text-gray-900">Persetujuan (Consent)</p>
                @foreach ($kasus->consents as $consent)
                    <div class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">
                                {{ $consent->jenis === 'sesi_pendampingan' ? 'Sesi Pendampingan' : 'Pengumpulan Media (Foto/Video)' }}
                            </p>
                            <x-badge tone="{{ $consent->status === 'disetujui' ? 'green' : 'amber' }}" class="mt-1 text-xs">
                                {{ $consent->status === 'disetujui' ? 'Disetujui' : 'Menunggu Persetujuan' }}
                            </x-badge>
                        </div>
                        @if ($consent->status !== 'disetujui')
                            <form method="POST" action="{{ route('kasus.consent.approve', [$kasus, $consent]) }}">
                                @csrf
                                @method('PATCH')
                                <x-primary-button type="submit">Setujui</x-primary-button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($isKonselor)
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                <p class="font-display text-sm font-bold text-gray-900">Sesi Pendampingan</p>

                @if ($kasus->sesi->isNotEmpty())
                    <div class="space-y-2">
                        @foreach ($kasus->sesi as $sesi)
                            <div class="rounded-lg border border-gray-100 px-3 py-2 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $sesi->dijadwalkan_pada->format('d M Y H:i') }}</p>
                                        <p class="text-xs text-gray-500">{{ $sesi->lokasi_mode }} &middot; {{ ucfirst(str_replace('_', ' ', $sesi->peserta)) }}</p>
                                    </div>
                                    <x-badge tone="{{ $sesi->status->value === 'selesai' ? 'green' : ($sesi->status->value === 'terjadwal' ? 'blue' : 'slate') }}">
                                        {{ $sesi->status->label() }}
                                    </x-badge>
                                </div>

                                @if ($isKonselor && $sesi->status->value === 'terjadwal')
                                    <div x-data="{ aksi: null }" class="border-t border-gray-100 pt-2">
                                        <div class="flex items-center gap-3 text-xs">
                                            <button type="button" @click="aksi = (aksi === 'selesai' ? null : 'selesai')" class="font-semibold text-success-600 hover:text-success-700">Tandai Selesai</button>
                                            <button type="button" @click="aksi = (aksi === 'batal' ? null : 'batal')" class="font-semibold text-error-600 hover:text-error-700">Batalkan</button>
                                            <form method="POST" action="{{ route('kasus.sesi.update-status', [$kasus, $sesi]) }}" class="inline">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="tidak_hadir">
                                                <button type="submit" class="font-semibold text-gray-500 hover:text-gray-700">Tandai Tidak Hadir</button>
                                            </form>
                                        </div>
                                        <form x-show="aksi === 'selesai'" method="POST" action="{{ route('kasus.sesi.update-status', [$kasus, $sesi]) }}" class="mt-2 space-y-2">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="selesai">
                                            <textarea name="catatan_internal" rows="2" placeholder="Catatan internal (rahasia, hanya konselor & admin)" class="block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                                            <x-primary-button type="submit" class="text-xs">Simpan</x-primary-button>
                                        </form>
                                        <form x-show="aksi === 'batal'" method="POST" action="{{ route('kasus.sesi.update-status', [$kasus, $sesi]) }}" class="mt-2 space-y-2">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="batal">
                                            <textarea name="alasan_batal" rows="2" placeholder="Alasan pembatalan" class="block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                                            <x-primary-button type="submit" class="text-xs">Simpan</x-primary-button>
                                        </form>
                                    </div>
                                @endif

                                @if ($isKonselor && $sesi->catatan_internal)
                                    <p class="border-t border-gray-100 pt-2 text-xs text-gray-500"><span class="font-semibold">Catatan internal:</span> {{ $sesi->catatan_internal }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($kasus->status->value === 'ditugaskan')
                    <div x-data="{
                        rows: [{ dijadwalkan_pada: '', peserta: 'siswa', lokasi_mode: '' }],
                        tambah() { this.rows.push({ dijadwalkan_pada: '', peserta: 'siswa', lokasi_mode: '' }); },
                        hapus(i) { if (this.rows.length > 1) this.rows.splice(i, 1); },
                    }" class="border-t border-gray-100 pt-4">
                        <form method="POST" action="{{ route('kasus.sesi.store', $kasus) }}" class="space-y-3">
                            @csrf
                            <template x-for="(row, i) in rows" :key="i">
                                <div class="grid grid-cols-1 gap-2 rounded-lg border border-gray-100 p-3 sm:grid-cols-4 sm:items-end">
                                    <div>
                                        <x-input-label value="Tanggal & Jam *" />
                                        <input type="datetime-local" :name="`sesi[${i}][dijadwalkan_pada]`" x-model="row.dijadwalkan_pada" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <div>
                                        <x-input-label value="Peserta *" />
                                        <select :name="`sesi[${i}][peserta]`" x-model="row.peserta" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                            <option value="siswa">Siswa</option>
                                            <option value="orang_tua">Orang Tua</option>
                                            <option value="keduanya">Keduanya</option>
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label value="Lokasi/Mode *" />
                                        <input type="text" :name="`sesi[${i}][lokasi_mode]`" x-model="row.lokasi_mode" required placeholder="Ruang BK / Google Meet" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <button type="button" @click="hapus(i)" x-show="rows.length > 1" class="text-xs font-semibold text-error-600 hover:text-error-700">Hapus Baris</button>
                                </div>
                            </template>
                            <div class="flex items-center gap-3">
                                <button type="button" @click="tambah()" class="text-xs font-semibold text-brand-600 hover:text-brand-700">+ Tambah Baris</button>
                                <x-primary-button type="submit">Jadwalkan Sesi</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        @endif

        @if ($isKonselor || $isSiswaTerkait || $isKontakUtama)
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                <p class="font-display text-sm font-bold text-gray-900">Tugas Pendampingan</p>

                @if ($kasus->tugas->isNotEmpty())
                    <div class="space-y-2">
                        @foreach ($kasus->tugas as $tugas)
                            <div class="rounded-lg border border-gray-100 px-3 py-2 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $tugas->judul }}</p>
                                        <p class="text-xs text-gray-500">Batas: {{ $tugas->batas_selesai_pada->format('d M Y') }} &middot; {{ ucfirst($tugas->frekuensi) }}</p>
                                    </div>
                                    <x-badge tone="{{ $tugas->status->value === 'selesai' ? 'green' : ($tugas->status->value === 'terlewat' ? 'red' : ($tugas->status->value === 'revisi' ? 'amber' : 'blue')) }}">
                                        {{ $tugas->status->label() }}
                                    </x-badge>
                                </div>

                                @if ($tugas->submissions->isNotEmpty())
                                    <div class="space-y-1 border-t border-gray-100 pt-2">
                                        @foreach ($tugas->submissions as $submission)
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-gray-600">{{ $submission->created_at->format('d M Y H:i') }}: {{ $submission->teks ?? '(lampiran saja)' }}</span>
                                                <x-badge tone="{{ $submission->status_review === 'diterima' ? 'green' : ($submission->status_review === 'revisi_diminta' ? 'amber' : 'slate') }}">
                                                    {{ str_replace('_', ' ', ucfirst($submission->status_review)) }}
                                                </x-badge>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if (($isSiswaTerkait || $isKontakUtama) && in_array($tugas->status->value, ['ditugaskan', 'dikerjakan', 'revisi'], true))
                                    <form method="POST" action="{{ route('kasus.tugas.submission.store', [$kasus, $tugas]) }}" enctype="multipart/form-data" class="space-y-2 border-t border-gray-100 pt-2">
                                        @csrf
                                        <textarea name="teks" rows="2" placeholder="Ceritakan bukti pengerjaan Anda" class="block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                                        @if ($kasus->consents->firstWhere('jenis', 'pengumpulan_media')?->status === 'disetujui')
                                            <input type="file" name="lampiran" class="block w-full text-xs text-gray-700">
                                        @endif
                                        <x-primary-button type="submit" class="text-xs">Kirim Bukti</x-primary-button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($isKonselor && $kasus->status->value === 'ditugaskan')
                    <div x-data="{
                        rows: [{ judul: '', instruksi: '', frekuensi: 'sekali', mulai_pada: '', batas_selesai_pada: '' }],
                        tambah() { this.rows.push({ judul: '', instruksi: '', frekuensi: 'sekali', mulai_pada: '', batas_selesai_pada: '' }); },
                        hapus(i) { if (this.rows.length > 1) this.rows.splice(i, 1); },
                    }" class="border-t border-gray-100 pt-4">
                        <form method="POST" action="{{ route('kasus.tugas.store', $kasus) }}" class="space-y-3">
                            @csrf
                            <template x-for="(row, i) in rows" :key="i">
                                <div class="grid grid-cols-1 gap-2 rounded-lg border border-gray-100 p-3 sm:grid-cols-2">
                                    <div>
                                        <x-input-label value="Judul *" />
                                        <input type="text" :name="`tugas[${i}][judul]`" x-model="row.judul" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <div>
                                        <x-input-label value="Frekuensi *" />
                                        <select :name="`tugas[${i}][frekuensi]`" x-model="row.frekuensi" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                            <option value="sekali">Sekali</option>
                                            <option value="harian">Harian</option>
                                            <option value="mingguan">Mingguan</option>
                                            <option value="bulanan">Bulanan</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <x-input-label value="Instruksi *" />
                                        <textarea :name="`tugas[${i}][instruksi]`" x-model="row.instruksi" rows="2" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                                    </div>
                                    <div>
                                        <x-input-label value="Mulai *" />
                                        <input type="date" :name="`tugas[${i}][mulai_pada]`" x-model="row.mulai_pada" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <div>
                                        <x-input-label value="Batas Selesai *" />
                                        <input type="date" :name="`tugas[${i}][batas_selesai_pada]`" x-model="row.batas_selesai_pada" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <button type="button" @click="hapus(i)" x-show="rows.length > 1" class="text-left text-xs font-semibold text-error-600 hover:text-error-700 sm:col-span-2">Hapus Baris</button>
                                </div>
                            </template>
                            <div class="flex items-center gap-3">
                                <button type="button" @click="tambah()" class="text-xs font-semibold text-brand-600 hover:text-brand-700">+ Tambah Baris</button>
                                <x-primary-button type="submit">Beri Tugas</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>

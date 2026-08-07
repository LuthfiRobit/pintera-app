<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-6" @if($isKonselor) x-data="tugasBatchForm()" @endif>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-4">
        <div>
            <h3 class="font-display text-base font-bold text-gray-900">Tugas & Refleksi Pendampingan</h3>
            <p class="text-xs text-gray-500 mt-0.5">Penugasan mandiri bagi siswa untuk dikerjakan dan disetujui selama periode pembinaan.</p>
        </div>

        @if ($isKonselor && in_array($kasus->status->value, ['ditugaskan', 'berjalan', 'eskalasi'], true))
            <button
                type="button"
                @click="showForm = !showForm"
                :class="showForm ? 'bg-gray-100 text-gray-700 border-gray-300' : 'bg-brand-500 text-white border-transparent shadow-sm hover:bg-brand-600'"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border text-xs font-bold transition whitespace-nowrap"
            >
                <x-icon name="checklist" class="h-4 w-4" />
                <span x-text="showForm ? 'Tutup Formulir' : '+ Beri Tugas Pendampingan'">+ Beri Tugas Pendampingan</span>
            </button>
        @endif
    </div>

    {{-- Formulir Satu Definisi Tugas --}}
    @if ($isKonselor && in_array($kasus->status->value, ['ditugaskan', 'berjalan', 'eskalasi'], true))
        <div x-show="showForm" style="display: none;" x-transition:enter="transition ease-out duration-200" class="rounded-xl border border-brand-200 bg-brand-50/20 p-5 shadow-2xs">
            <h4 class="font-display text-sm font-bold text-gray-900 mb-1">Formulir Pemberian Tugas Baru</h4>
            <p class="text-xs text-gray-500 mb-4">Sistem akan membuat baris tugas sesuai frekuensi dan rentang tanggal yang dipilih.</p>

            <form method="POST" action="{{ route('kasus.tugas.store', $kasus) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                    <div class="sm:col-span-8">
                        <x-input-label value="Judul Tugas *" class="text-xs font-bold text-gray-700" />
                        <x-text-input type="text" name="judul" x-model="form.judul" required placeholder="Contoh: Jurnal Emosi Harian / Evaluasi Sikap" class="mt-1.5 block w-full" />
                    </div>
                    <div class="sm:col-span-4">
                        <x-input-label value="Frekuensi *" class="text-xs font-bold text-gray-700" />
                        <x-select name="frekuensi" x-model="form.frekuensi" @change="pratinjau()" class="mt-1.5 block w-full">
                            <option value="sekali">Sekali (One-off)</option>
                            <option value="harian">Harian</option>
                            <option value="mingguan">Mingguan</option>
                            <option value="bulanan">Bulanan</option>
                        </x-select>
                    </div>
                    <div class="sm:col-span-12">
                        <x-input-label value="Instruksi Pengerjaan *" class="text-xs font-bold text-gray-700" />
                        <x-textarea name="instruksi" x-model="form.instruksi" rows="2" required placeholder="Jelaskan langkah-langkah detail pengerjaan atau pertanyaan yang harus dijawab siswa..." class="mt-1.5 block w-full"></x-textarea>
                    </div>
                    <div class="sm:col-span-4">
                        <x-input-label value="Tanggal Mulai *" class="text-xs font-bold text-gray-700" />
                        <x-text-input type="date" name="tanggal_mulai" x-model="form.tanggal_mulai" @change="pratinjau()" required class="mt-1.5 block w-full" />
                    </div>
                    <div class="sm:col-span-4">
                        <x-input-label value="Tanggal Selesai *" class="text-xs font-bold text-gray-700" />
                        <x-text-input type="date" name="tanggal_selesai" x-model="form.tanggal_selesai" @change="pratinjau()" required class="mt-1.5 block w-full" />
                    </div>
                    <div class="sm:col-span-4" x-show="frekuensiAkhir === 'bulanan'" style="display: none;">
                        <x-input-label value="Tanggal Pengumpulan Bulanan *" class="text-xs font-bold text-gray-700" />
                        <x-select name="tanggal_pengumpulan_bulanan" x-model="form.tanggal_pengumpulan_bulanan" @change="pratinjau()" class="mt-1.5 block w-full">
                            <option value="">Pilih tanggal...</option>
                            <template x-for="hari in 31" :key="hari">
                                <option :value="hari" x-text="hari"></option>
                            </template>
                            <option value="akhir_bulan">Hari terakhir bulan</option>
                        </x-select>
                    </div>
                </div>

                {{-- Pratinjau Real-Time --}}
                <div x-show="pratinjauLoaded" style="display: none;" class="rounded-lg border p-3" :class="frekuensiAkhir !== form.frekuensi ? 'border-amber-300 bg-amber-50/60' : 'border-gray-200 bg-gray-50'">
                    <p class="text-xs font-bold flex items-center gap-1.5" :class="frekuensiAkhir !== form.frekuensi ? 'text-amber-800' : 'text-gray-700'">
                        <x-icon name="warning" class="h-3.5 w-3.5" x-show="frekuensiAkhir !== form.frekuensi" style="display: none;" />
                        <span x-text="frekuensiAkhir !== form.frekuensi
                            ? `Rentang tidak memenuhi syarat '${form.frekuensi}' — akan diproses sebagai '${frekuensiAkhir}'`
                            : `Akan dibuat sebagai '${frekuensiAkhir}'`"></span>
                    </p>
                    <p class="text-xs text-gray-600 mt-1">Jumlah baris tugas yang akan dibuat: <b x-text="jumlahBaris"></b></p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
                    <button type="button" @click="showForm = false" class="px-4 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition">
                        Batal
                    </button>
                    <x-primary-button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold shadow-sm">
                        Beri Tugas
                    </x-primary-button>
                </div>
            </form>
        </div>
    @endif

    {{-- Daftar Tugas Terkelompok per Batch --}}
    @if ($kasus->tugas->isNotEmpty())
        @php
            $tugasPerBatch = $kasus->tugas->sortBy('batch_urutan')->groupBy('batch_id');
        @endphp
        <div class="space-y-6">
            @foreach ($tugasPerBatch as $batchId => $barisBatch)
                @php $tugasPertama = $barisBatch->first(); @endphp
                <div class="space-y-3">
                    <p class="font-display text-sm font-bold text-gray-900 flex items-center gap-2">
                        <x-icon name="checklist" class="h-4 w-4 text-brand-500 shrink-0" />
                        {{ $tugasPertama->judul }}
                        <span class="capitalize bg-gray-100 px-2 py-0.5 rounded text-[11px] font-semibold text-gray-700">{{ ucfirst($tugasPertama->frekuensi) }}</span>
                    </p>

                    <div class="space-y-3 pl-2 border-l-2 border-gray-100">
                        @foreach ($barisBatch as $tugas)
                            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs transition hover:shadow-card space-y-4">
                                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 border-b border-gray-100 pb-3.5">
                                    <div class="space-y-1">
                                        <p class="text-xs font-bold text-gray-700">
                                            @if ($tugas->batch_total > 1)
                                                {{ $tugas->frekuensi === 'harian' ? 'Hari' : ($tugas->frekuensi === 'mingguan' ? 'Minggu' : 'Bulan') }} {{ $tugas->batch_urutan }} dari {{ $tugas->batch_total }}
                                            @else
                                                Tugas
                                            @endif
                                        </p>
                                        <p class="text-xs font-semibold text-gray-500 flex items-center gap-2">
                                            <span class="text-amber-600 font-bold">Batas Selesai: {{ $tugas->batas_selesai_pada->format('d M Y') }}</span>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <x-badge :tone="$tugas->status->value === 'selesai' ? 'green' : ($tugas->status->value === 'terlewat' ? 'red' : ($tugas->status->value === 'revisi' ? 'amber' : 'blue'))" class="text-xs font-bold px-3 py-1">
                                            {{ $tugas->status->label() }}
                                        </x-badge>

                                        @if ($isKonselor && ! in_array($tugas->status->value, ['selesai', 'terlewat'], true))
                                            <form method="POST" action="{{ route('kasus.tugas.selesai', [$kasus, $tugas]) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-success-50 hover:bg-success-100 px-2.5 py-1 text-[11px] font-extrabold text-success-700 transition border border-success-200" title="Selesaikan tugas ini secara manual">
                                                    <x-icon name="check_circle" class="h-3.5 w-3.5" />
                                                    Tandai Selesai
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                @if ($tugas->instruksi)
                                    <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-700 border border-gray-100">
                                        <span class="font-bold text-gray-600 block mb-1">Instruksi:</span>
                                        <p class="leading-relaxed font-medium text-gray-800">{{ $tugas->instruksi }}</p>
                                    </div>
                                @endif

                                @if ($tugas->submissions->isNotEmpty())
                                    <div class="space-y-2 pt-1">
                                        <p class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Riwayat Bukti & Hasil Pengerjaan:</p>
                                        <div class="space-y-2.5 divide-y divide-gray-100 rounded-xl border border-gray-100 bg-gray-50/50 p-3.5">
                                            @foreach ($tugas->submissions as $submission)
                                                <div class="pt-2.5 first:pt-0 space-y-2 text-xs">
                                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                        <div>
                                                            <span class="font-bold text-gray-800">{{ $submission->created_at->format('d M Y H:i') }}:</span>
                                                            <span class="text-gray-700 ml-1 font-medium">{{ $submission->teks ?? '(Lampiran saja)' }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-2 shrink-0">
                                                            @if ($submission->lampiran)
                                                                <a href="{{ route('kasus.tugas.submission.lampiran', [$kasus, $tugas, $submission]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-bold text-brand-600 hover:text-brand-700 hover:underline bg-white px-2 py-1 rounded border border-brand-200 shadow-2xs text-[11px]">
                                                                    <x-icon name="description" class="h-3 w-3" />
                                                                    Lihat Lampiran
                                                                </a>
                                                            @endif
                                                            <x-badge :tone="$submission->status_review === 'diterima' ? 'green' : ($submission->status_review === 'revisi_diminta' ? 'amber' : 'slate')" class="text-[10px] font-extrabold">
                                                                {{ str_replace('_', ' ', ucfirst($submission->status_review)) }}
                                                            </x-badge>
                                                        </div>
                                                    </div>

                                                    @if ($isKonselor && $submission->status_review === 'menunggu_review')
                                                        <div x-data="{ revisi: false }" class="rounded-lg bg-white p-3 border border-gray-200 shadow-2xs mt-2">
                                                            <div class="flex items-center justify-between gap-3 text-xs">
                                                                <span class="font-bold text-gray-700">Tindakan Review:</span>
                                                                <div class="flex items-center gap-2">
                                                                    <form method="POST" action="{{ route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]) }}">
                                                                        @csrf @method('PATCH')
                                                                        <input type="hidden" name="status_review" value="diterima">
                                                                        <button type="submit" class="inline-flex items-center gap-1 font-bold text-success-700 hover:bg-success-100 bg-success-50 px-3 py-1.5 rounded-lg transition border border-success-200">
                                                                            <x-icon name="check_circle" class="h-3.5 w-3.5" />
                                                                            Terima Hasil
                                                                        </button>
                                                                    </form>
                                                                    <button type="button" @click="revisi = !revisi" class="inline-flex items-center gap-1 font-bold text-amber-700 hover:bg-amber-100 bg-amber-50 px-3 py-1.5 rounded-lg transition border border-amber-200">
                                                                        <x-icon name="edit_note" class="h-3.5 w-3.5" />
                                                                        Minta Revisi
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <form x-show="revisi" style="display: none;" method="POST" action="{{ route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]) }}" class="mt-3 pt-3 border-t border-gray-100 space-y-2">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status_review" value="revisi_diminta">
                                                                <label class="block text-[11px] font-bold text-amber-900">Catatan Perbaikan untuk Siswa:</label>
                                                                <div class="flex items-center gap-2">
                                                                    <input type="text" name="catatan_revisi" required placeholder="Contoh: Harap lampirkan bukti foto refleksi..." class="block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-amber-500 focus:ring-amber-500">
                                                                    <button type="submit" class="whitespace-nowrap font-bold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg text-xs transition shadow-sm">Kirim Catatan</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if (($isSiswaTerkait || $isKontakUtama) && in_array($tugas->status->value, ['ditugaskan', 'dikerjakan', 'revisi'], true))
                                    <form method="POST" action="{{ route('kasus.tugas.submission.store', [$kasus, $tugas]) }}" enctype="multipart/form-data" class="space-y-3 border-t border-gray-100 pt-4">
                                        @csrf
                                        <div class="rounded-xl border border-brand-200 bg-brand-50/10 p-4 space-y-3">
                                            <h5 class="font-display text-xs font-bold text-brand-900">Kirim Hasil / Bukti Pengerjaan Tugas</h5>
                                            <textarea name="teks" rows="2" placeholder="Ceritakan bukti atau jelaskan hasil refleksi pengerjaan tugas Anda di sini..." class="block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                                                <div>
                                                    @if ($kasus->consents->firstWhere('jenis', 'pengumpulan_media')?->status === 'disetujui')
                                                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Unggah File/Foto Bukti (Opsional):</label>
                                                        <input type="file" name="lampiran" class="block w-full text-xs text-gray-700 file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 transition">
                                                    @else
                                                        <p class="text-[11px] font-medium text-gray-400 italic">Unggah media dinonaktifkan hingga informed consent media disetujui.</p>
                                                    @endif
                                                </div>
                                                <x-primary-button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold shrink-0">Kirim Bukti</x-primary-button>
                                            </div>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/50 p-8 text-center text-gray-400">
            <x-icon name="checklist" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
            <p class="text-sm font-bold text-gray-600">Belum Ada Tugas Diberikan</p>
            <p class="mt-0.5 text-xs text-gray-400 max-w-md mx-auto">Konselor belum memberikan instruksi tugas mandiri atau lembar refleksi untuk kasus ini.</p>
        </div>
    @endif
</div>

@if ($isKonselor)
    <script>
    function tugasBatchForm() {
        return {
            // Jika submit sebelumnya gagal validasi (mis. judul kosong), buka kembali panel
            // formulir dan isi ulang dari old() alih-alih membiarkan konselor mengetik ulang
            // semuanya dari awal — `frekuensi` cukup unik di halaman ini untuk dipakai sebagai
            // penanda "submit tugas ini yang gagal".
            showForm: {{ old('frekuensi') !== null ? 'true' : 'false' }},
            form: {
                judul: @js(old('judul', '')),
                instruksi: @js(old('instruksi', '')),
                frekuensi: @js(old('frekuensi', 'sekali')),
                tanggal_mulai: @js(old('tanggal_mulai', '')),
                tanggal_selesai: @js(old('tanggal_selesai', '')),
                tanggal_pengumpulan_bulanan: @js(old('tanggal_pengumpulan_bulanan', '')),
            },
            frekuensiAkhir: 'sekali',
            jumlahBaris: 0,
            pratinjauLoaded: false,

            init() {
                if (this.showForm && this.form.tanggal_mulai && this.form.tanggal_selesai) {
                    this.pratinjau();
                }
            },

            async pratinjau() {
                if (!this.form.tanggal_mulai || !this.form.tanggal_selesai) return;

                const response = await fetch('{{ route('kasus.tugas.preview', $kasus) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });

                if (!response.ok) { this.pratinjauLoaded = false; return; }

                const data = await response.json();
                this.frekuensiAkhir = data.frekuensi_akhir;
                this.jumlahBaris = data.jumlah_baris;
                this.pratinjauLoaded = true;
            },
        };
    }
    </script>
@endif

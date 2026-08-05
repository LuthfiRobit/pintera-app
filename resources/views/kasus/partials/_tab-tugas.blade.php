<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-4">
        <div>
            <h3 class="font-display text-base font-bold text-gray-900">Tugas & Refleksi Pendampingan</h3>
            <p class="text-xs text-gray-500 mt-0.5">Penugasan mandiri bagi siswa untuk dikerjakan dan distujui selama periode pembinaan.</p>
        </div>

        @if ($isKonselor && in_array($kasus->status->value, ['ditugaskan', 'berjalan', 'eskalasi'], true))
            <button
                type="button"
                @click="showTugasForm = !showTugasForm"
                :class="showTugasForm ? 'bg-gray-100 text-gray-700 border-gray-300' : 'bg-brand-500 text-white border-transparent shadow-sm hover:bg-brand-600'"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border text-xs font-bold transition whitespace-nowrap"
            >
                <x-icon name="add_task" class="h-4 w-4" />
                <span x-text="showTugasForm ? 'Tutup Formulir' : '+ Beri Tugas Pendampingan'">+ Beri Tugas Pendampingan</span>
            </button>
        @endif
    </div>

    {{-- Toggleable Tugas Assignment Form --}}
    @if ($isKonselor && in_array($kasus->status->value, ['ditugaskan', 'berjalan', 'eskalasi'], true))
        <div x-show="showTugasForm" style="display: none;" x-transition:enter="transition ease-out duration-200" class="rounded-xl border border-brand-200 bg-brand-50/20 p-5 shadow-2xs">
            <h4 class="font-display text-sm font-bold text-gray-900 mb-1">Formulir Pemberian Tugas Baru</h4>
            <p class="text-xs text-gray-500 mb-4">Berikan instruksi jelas beserta batas waktu penyelesaian tugas atau lembar refleksi kepada siswa.</p>

            <div x-data="{
                rows: [{ judul: '', instruksi: '', frekuensi: 'sekali', mulai_pada: '', batas_selesai_pada: '' }],
                tambah() { this.rows.push({ judul: '', instruksi: '', frekuensi: 'sekali', mulai_pada: '', batas_selesai_pada: '' }); },
                hapus(i) { if (this.rows.length > 1) this.rows.splice(i, 1); },
            }">
                <form method="POST" action="{{ route('kasus.tugas.store', $kasus) }}" class="space-y-4">
                    @csrf
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-2xs sm:grid-cols-12 sm:items-start transition hover:border-brand-300">
                            <div class="sm:col-span-8">
                                <x-input-label value="Judul Tugas *" class="text-xs font-bold text-gray-700" />
                                <input type="text" :name="`tugas[${i}][judul]`" x-model="row.judul" required placeholder="Contoh: Jurnal Emosi Harian / Evaluasi Sikap" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div class="sm:col-span-4">
                                <x-input-label value="Frekuensi *" class="text-xs font-bold text-gray-700" />
                                <select :name="`tugas[${i}][frekuensi]`" x-model="row.frekuensi" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                                    <option value="sekali">Sekali (One-off)</option>
                                    <option value="harian">Harian</option>
                                    <option value="mingguan">Mingguan</option>
                                    <option value="bulanan">Bulanan</option>
                                </select>
                            </div>
                            <div class="sm:col-span-12">
                                <x-input-label value="Instruksi Pengerjaan *" class="text-xs font-bold text-gray-700" />
                                <textarea :name="`tugas[${i}][instruksi]`" x-model="row.instruksi" rows="2" required placeholder="Jelaskan langkah-langkah detail pengerjaan atau pertanyaan yang harus dijawab siswa..." class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                            </div>
                            <div class="sm:col-span-5">
                                <x-input-label value="Tanggal Mulai *" class="text-xs font-bold text-gray-700" />
                                <input type="date" :name="`tugas[${i}][mulai_pada]`" x-model="row.mulai_pada" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div class="sm:col-span-5">
                                <x-input-label value="Batas Selesai *" class="text-xs font-bold text-gray-700" />
                                <input type="date" :name="`tugas[${i}][batas_selesai_pada]`" x-model="row.batas_selesai_pada" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div class="sm:col-span-2 pt-6 flex items-center justify-end">
                                <button type="button" @click="hapus(i)" x-show="rows.length > 1" class="inline-flex items-center gap-1 text-xs font-bold text-error-600 hover:text-error-700 px-2 py-1.5 rounded-lg hover:bg-error-50 transition">
                                    <x-icon name="delete" class="h-4 w-4" />
                                    <span>Hapus</span>
                                </button>
                            </div>
                        </div>
                    </template>
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <button type="button" @click="tambah()" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700 bg-brand-50/50 hover:bg-brand-50 px-3 py-2 rounded-lg transition">
                            <x-icon name="add" class="h-4 w-4" />
                            Tambah Baris
                        </button>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="showTugasForm = false" class="px-4 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition">
                                Batal
                            </button>
                            <x-primary-button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold shadow-sm">
                                <x-icon name="send" class="mr-1.5 h-4 w-4" />
                                Beri Tugas
                            </x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Tugas List & Submissions --}}
    @if ($kasus->tugas->isNotEmpty())
        <div class="space-y-5">
            @foreach ($kasus->tugas as $tugas)
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs transition hover:shadow-card space-y-4">
                    {{-- Header Tugas --}}
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 border-b border-gray-100 pb-3.5">
                        <div class="space-y-1">
                            <p class="font-display text-sm font-bold text-gray-900 flex items-center gap-2">
                                <x-icon name="assignment" class="h-4 w-4 text-brand-500 shrink-0" />
                                <span>{{ $tugas->judul }}</span>
                            </p>
                            <p class="text-xs font-semibold text-gray-500 flex items-center gap-2 pl-6">
                                <span class="text-amber-600 font-bold">Batas Selesai: {{ $tugas->batas_selesai_pada->format('d M Y') }}</span>
                                <span class="text-gray-300">&bull;</span>
                                <span class="capitalize bg-gray-100 px-2 py-0.5 rounded text-[11px] font-semibold text-gray-700">{{ ucfirst($tugas->frekuensi) }}</span>
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

                    {{-- Instruksi Tugas --}}
                    @if($tugas->instruksi)
                        <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-700 border border-gray-100">
                            <span class="font-bold text-gray-600 block mb-1">Instruksi:</span>
                            <p class="leading-relaxed font-medium text-gray-800">{{ $tugas->instruksi }}</p>
                        </div>
                    @endif

                    {{-- Submisi & Formulir: per-tanggal untuk harian, satu zona untuk lainnya --}}
                    @if ($tugas->frekuensi === 'harian')
                        @php
                            $tanggalList = collect();
                            $kursor = $tugas->mulai_pada->copy();
                            while ($kursor->lte($tugas->batas_selesai_pada)) {
                                $tanggalList->push($kursor->copy());
                                $kursor->addDay();
                            }
                            $submisiPerTanggal = $tugas->submissions->groupBy(fn ($s) => $s->tanggal?->toDateString());
                        @endphp
                        <div class="space-y-2.5 pt-1">
                            <p class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Checklist Harian:</p>
                            @foreach ($tanggalList as $tanggal)
                                @php
                                    $submisiHariIni = $submisiPerTanggal->get($tanggal->toDateString(), collect())->sortByDesc('created_at')->first();
                                    $terkunci = $submisiHariIni && in_array($submisiHariIni->status_review, ['menunggu_review', 'diterima'], true);
                                @endphp
                                <div class="rounded-lg border border-gray-200 bg-white p-3.5">
                                    <p class="text-xs font-bold text-gray-700 flex items-center gap-1.5">
                                        <x-icon name="calendar_month" class="h-3.5 w-3.5 text-gray-400" />
                                        {{ $tanggal->translatedFormat('d M Y') }}
                                        @if ($terkunci)
                                            <x-icon name="lock" class="h-3 w-3 text-gray-300" />
                                        @endif
                                    </p>

                                    @if ($submisiHariIni)
                                        <div class="mt-2 space-y-2 text-xs">
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                <div>
                                                    <span class="font-bold text-gray-800">{{ $submisiHariIni->created_at->format('d M Y H:i') }}:</span>
                                                    <span class="text-gray-700 ml-1 font-medium">{{ $submisiHariIni->teks ?? '(Lampiran saja)' }}</span>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    @if ($submisiHariIni->lampiran)
                                                        <a href="{{ route('kasus.tugas.submission.lampiran', [$kasus, $tugas, $submisiHariIni]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-bold text-brand-600 hover:text-brand-700 hover:underline bg-white px-2 py-1 rounded border border-brand-200 shadow-2xs text-[11px]">
                                                            Lihat Lampiran
                                                        </a>
                                                    @endif
                                                    <x-badge :tone="$submisiHariIni->status_review === 'diterima' ? 'green' : ($submisiHariIni->status_review === 'revisi_diminta' ? 'amber' : 'slate')" class="text-[10px] font-extrabold">
                                                        {{ str_replace('_', ' ', ucfirst($submisiHariIni->status_review)) }}
                                                    </x-badge>
                                                </div>
                                            </div>

                                            @if ($isKonselor && $submisiHariIni->status_review === 'menunggu_review')
                                                <div x-data="{ revisi: false }" class="rounded-lg bg-gray-50 p-3 border border-gray-200 shadow-2xs mt-2">
                                                    <div class="flex items-center justify-between gap-3 text-xs">
                                                        <span class="font-bold text-gray-700">Tindakan Review:</span>
                                                        <div class="flex items-center gap-2">
                                                            <form method="POST" action="{{ route('kasus.tugas.submission.review', [$kasus, $tugas, $submisiHariIni]) }}">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status_review" value="diterima">
                                                                <button type="submit" class="inline-flex items-center gap-1 font-bold text-success-700 hover:bg-success-100 bg-success-50 px-3 py-1.5 rounded-lg transition border border-success-200">
                                                                    <x-icon name="check_circle" class="h-3.5 w-3.5" />
                                                                    Terima
                                                                </button>
                                                            </form>
                                                            <button type="button" @click="revisi = !revisi" class="inline-flex items-center gap-1 font-bold text-amber-700 hover:bg-amber-100 bg-amber-50 px-3 py-1.5 rounded-lg transition border border-amber-200">
                                                                Minta Revisi
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <form x-show="revisi" style="display: none;" method="POST" action="{{ route('kasus.tugas.submission.review', [$kasus, $tugas, $submisiHariIni]) }}" class="mt-3 pt-3 border-t border-gray-100 space-y-2">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="status_review" value="revisi_diminta">
                                                        <input type="text" name="catatan_revisi" required placeholder="Catatan perbaikan untuk hari ini..." class="block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-amber-500 focus:ring-amber-500">
                                                        <button type="submit" class="font-bold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg text-xs transition shadow-sm">Kirim Catatan</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @if (! $terkunci && ($isSiswaTerkait || $isKontakUtama) && in_array($tugas->status->value, ['ditugaskan', 'dikerjakan', 'revisi'], true))
                                        <form method="POST" action="{{ route('kasus.tugas.submission.store', [$kasus, $tugas]) }}" enctype="multipart/form-data" class="mt-2.5 space-y-2 border-t border-gray-100 pt-2.5">
                                            @csrf
                                            <input type="hidden" name="tanggal" value="{{ $tanggal->toDateString() }}">
                                            <textarea name="teks" rows="2" placeholder="Bukti/refleksi untuk tanggal ini..." class="block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                @if ($kasus->consents->firstWhere('jenis', 'pengumpulan_media')?->status === 'disetujui')
                                                    <input type="file" name="lampiran" class="block w-full text-xs text-gray-700 file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 transition">
                                                @else
                                                    <p class="text-[11px] font-medium text-gray-400 italic">Unggah media dinonaktifkan hingga informed consent media disetujui.</p>
                                                @endif
                                                <x-primary-button type="submit" class="px-4 py-1.5 rounded-lg text-xs font-bold shrink-0">Kirim</x-primary-button>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        {{-- Daftar Submisi / Bukti Pengerjaan --}}
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
                                                            <x-icon name="attach_file" class="h-3 w-3" />
                                                            Lihat Lampiran
                                                        </a>
                                                    @endif
                                                    <x-badge :tone="$submission->status_review === 'diterima' ? 'green' : ($submission->status_review === 'revisi_diminta' ? 'amber' : 'slate')" class="text-[10px] font-extrabold">
                                                        {{ str_replace('_', ' ', ucfirst($submission->status_review)) }}
                                                    </x-badge>
                                                </div>
                                            </div>

                                            {{-- Aksi Review Konselor --}}
                                            @if ($isKonselor && $submission->status_review === 'menunggu_review')
                                                <div x-data="{ revisi: false }" class="rounded-lg bg-white p-3 border border-gray-200 shadow-2xs mt-2">
                                                    <div class="flex items-center justify-between gap-3 text-xs">
                                                        <span class="font-bold text-gray-700">Tindakan Review:</span>
                                                        <div class="flex items-center gap-2">
                                                            <form method="POST" action="{{ route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]) }}">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status_review" value="diterima">
                                                                <button type="submit" class="inline-flex items-center gap-1 font-bold text-success-700 hover:bg-success-100 bg-success-50 px-3 py-1.5 rounded-lg transition border border-success-200">
                                                                    <x-icon name="thumb_up" class="h-3.5 w-3.5" />
                                                                    Terima Hasil
                                                                </button>
                                                            </form>
                                                            <button type="button" @click="revisi = !revisi" class="inline-flex items-center gap-1 font-bold text-amber-700 hover:bg-amber-100 bg-amber-50 px-3 py-1.5 rounded-lg transition border border-amber-200">
                                                                <x-icon name="rate_review" class="h-3.5 w-3.5" />
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

                        {{-- Form Submisi (Siswa / Orang Tua) --}}
                        @if (($isSiswaTerkait || $isKontakUtama) && in_array($tugas->status->value, ['ditugaskan', 'dikerjakan', 'revisi'], true))
                            <form method="POST" action="{{ route('kasus.tugas.submission.store', [$kasus, $tugas]) }}" enctype="multipart/form-data" class="space-y-3 border-t border-gray-100 pt-4">
                                @csrf
                                <div class="rounded-xl border border-brand-200 bg-brand-50/10 p-4 space-y-3">
                                    <h5 class="font-display text-xs font-bold text-brand-900 flex items-center gap-1.5">
                                        <x-icon name="upload_file" class="h-4 w-4 text-brand-600" />
                                        Kirim Hasil / Bukti Pengerjaan Tugas
                                    </h5>
                                    <div>
                                        <textarea name="teks" rows="2" placeholder="Ceritakan bukti atau jelaskan hasil refleksi pengerjaan tugas Anda di sini..." class="block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                                    </div>
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                                        <div>
                                            @if ($kasus->consents->firstWhere('jenis', 'pengumpulan_media')?->status === 'disetujui')
                                                <label class="block text-[11px] font-bold text-gray-600 mb-1">Unggah File/Foto Bukti (Opsional):</label>
                                                <input type="file" name="lampiran" class="block w-full text-xs text-gray-700 file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 transition">
                                            @else
                                                <p class="text-[11px] font-medium text-gray-400 italic">Unggah media dinonaktifkan hingga informed consent media disetujui.</p>
                                            @endif
                                        </div>
                                        <x-primary-button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold shrink-0">
                                            <x-icon name="send" class="mr-1.5 h-3.5 w-3.5" />
                                            Kirim Bukti
                                        </x-primary-button>
                                    </div>
                                </div>
                            </form>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/50 p-8 text-center text-gray-400">
            <x-icon name="assignment" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
            <p class="text-sm font-bold text-gray-600">Belum Ada Tugas Diberikan</p>
            <p class="mt-0.5 text-xs text-gray-400 max-w-md mx-auto">Konselor belum memberikan instruksi tugas mandiri atau lembar refleksi untuk kasus ini.</p>
        </div>
    @endif
</div>

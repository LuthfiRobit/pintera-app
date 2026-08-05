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
                                <div class="space-y-1 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">{{ $submission->created_at->format('d M Y H:i') }}: {{ $submission->teks ?? '(lampiran saja)' }}</span>
                                        <x-badge tone="{{ $submission->status_review === 'diterima' ? 'green' : ($submission->status_review === 'revisi_diminta' ? 'amber' : 'slate') }}">
                                            {{ str_replace('_', ' ', ucfirst($submission->status_review)) }}
                                        </x-badge>
                                    </div>
                                    @if ($submission->lampiran)
                                        <a href="{{ route('kasus.tugas.submission.lampiran', [$kasus, $tugas, $submission]) }}" target="_blank" rel="noopener" class="font-semibold text-brand-600 hover:text-brand-700">Lihat Lampiran</a>
                                    @endif
                                    @if ($isKonselor && $submission->status_review === 'menunggu_review')
                                        <div x-data="{ revisi: false }" class="flex items-center gap-2">
                                            <form method="POST" action="{{ route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status_review" value="diterima">
                                                <button type="submit" class="font-semibold text-success-600 hover:text-success-700">Terima</button>
                                            </form>
                                            <button type="button" @click="revisi = !revisi" class="font-semibold text-amber-600 hover:text-amber-700">Minta Revisi</button>
                                        </div>
                                        <form x-show="revisi" method="POST" action="{{ route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]) }}" class="flex items-center gap-2">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status_review" value="revisi_diminta">
                                            <input type="text" name="catatan_revisi" placeholder="Catatan revisi" class="block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                            <button type="submit" class="whitespace-nowrap font-semibold text-amber-600 hover:text-amber-700">Kirim</button>
                                        </form>
                                    @endif
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

                    @if ($isKonselor && ! in_array($tugas->status->value, ['selesai', 'terlewat'], true))
                        <form method="POST" action="{{ route('kasus.tugas.selesai', [$kasus, $tugas]) }}" class="border-t border-gray-100 pt-2">
                            @csrf @method('PATCH')
                            <button type="submit" class="text-xs font-semibold text-success-600 hover:text-success-700">Tandai Tugas Selesai</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <p class="text-xs text-gray-500 italic">Belum ada tugas pendampingan diberikan.</p>
    @endif

    @if ($isKonselor && in_array($kasus->status->value, ['ditugaskan', 'berjalan', 'eskalasi'], true))
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

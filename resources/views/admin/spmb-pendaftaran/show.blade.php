<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">{{ $pendaftaran->calonMurid->nama_lengkap }}</h2>
        <p class="mt-1 font-mono text-sm text-slate">{{ $pendaftaran->kode_pendaftaran }}</p>
    </x-slot>

    <div
        class="mx-auto max-w-5xl space-y-6"
        x-data="pendaftaranDetail({
            canVerifikasiDokumen: @js($pendaftaran instanceof \App\Models\Pendaftaran && auth()->user()->can('spmb-pendaftaran.verifikasi-dokumen')),
            canNilaiSeleksi: @js(auth()->user()->can('spmb-pendaftaran.nilai-seleksi')),
            canTetapkanKeputusan: @js(auth()->user()->can('spmb-pendaftaran.tetapkan-keputusan')),
            verifikasiDokumenUrlTemplate: @js(route('admin.spmb-pendaftaran.verifikasi-dokumen', [$pendaftaran, '__ID__'])),
            nilaiUrl: @js(route('admin.spmb-pendaftaran.nilai', $pendaftaran)),
            keputusanUrl: @js(route('admin.spmb-pendaftaran.keputusan', $pendaftaran)),
            initialStatus: @js($pendaftaran->status === 'menunggu_verifikasi' ? 'diterima' : $pendaftaran->status),
            initialCatatanKeputusan: @js($pendaftaran->catatan_keputusan),
        })"
    >
        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Data Calon Murid</h3>
            </div>
            <div class="grid grid-cols-2 gap-4 p-6 text-sm">
                <div><p class="text-slate">NIK</p><p class="font-mono text-ink">{{ $pendaftaran->calonMurid->nik }}</p></div>
                <div><p class="text-slate">Jenis Kelamin</p><p class="text-ink">{{ $pendaftaran->calonMurid->jenis_kelamin }}</p></div>
                <div><p class="text-slate">Tempat, Tanggal Lahir</p><p class="text-ink">{{ $pendaftaran->calonMurid->tempat_lahir }}, {{ $pendaftaran->calonMurid->tanggal_lahir->translatedFormat('d F Y') }}</p></div>
                <div><p class="text-slate">Jalur / Gelombang</p><p class="text-ink">{{ $pendaftaran->jalurPpdb->nama }} / {{ $pendaftaran->gelombangPpdb->nama }}</p></div>
                @if ($pendaftaran->calonMurid->alamat)
                    <div class="col-span-2">
                        <p class="text-slate">Alamat</p>
                        <p class="text-ink">{{ $pendaftaran->calonMurid->alamat->alamat_jalan }}, {{ $pendaftaran->calonMurid->alamat->desa_kelurahan }}, {{ $pendaftaran->calonMurid->alamat->kecamatan }}, {{ $pendaftaran->calonMurid->alamat->kabupaten_kota }}, {{ $pendaftaran->calonMurid->alamat->provinsi }}</p>
                    </div>
                @endif
                @if ($pendaftaran->calonMurid->keluarga->isNotEmpty())
                    <div class="col-span-2">
                        <p class="text-slate">Orang Tua / Wali</p>
                        <ul class="mt-1 space-y-1 text-ink">
                            @foreach ($pendaftaran->calonMurid->keluarga as $anggota)
                                <li>{{ ucfirst($anggota->jenis) }}: {{ $anggota->nama }} @if ($anggota->pekerjaan) &middot; {{ $anggota->pekerjaan }} @endif</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($pendaftaran->jawabanFormulir->isNotEmpty())
                    <div class="col-span-2">
                        <p class="text-slate">Formulir Tambahan</p>
                        <ul class="mt-1 space-y-1 text-ink">
                            @foreach ($pendaftaran->jawabanFormulir as $jawaban)
                                <li>{{ $jawaban->formulirField->label }}: {{ $jawaban->nilai }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </x-panel>

        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Dokumen</h3>
                <p class="mt-0.5 text-sm text-slate">
                    {{ $pendaftaran->dokumen->where('status_verifikasi', 'diterima')->count() }} dari {{ $pendaftaran->dokumen->count() }} dokumen terverifikasi
                </p>
            </div>
            <ul class="divide-y divide-ink/10 px-6">
                @foreach ($pendaftaran->dokumen as $dokumen)
                    <li class="py-4" x-data="{ menolak: false }">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-medium text-ink">{{ $dokumen->dokumenSyaratPpdb->nama_dokumen }}</p>
                                <a href="{{ Storage::url($dokumen->file_path) }}" target="_blank" class="text-xs text-slate hover:text-ink">Lihat berkas: {{ $dokumen->nama_file_asli }}</a>
                                @if ($dokumen->catatan_verifikasi)
                                    <p class="mt-1 text-xs text-signal-red">Catatan: {{ $dokumen->catatan_verifikasi }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <x-badge :tone="$dokumen->status_verifikasi === 'diterima' ? 'green' : ($dokumen->status_verifikasi === 'ditolak' ? 'red' : 'slate')">
                                    {{ $dokumen->status_verifikasi === 'diterima' ? 'Diterima' : ($dokumen->status_verifikasi === 'ditolak' ? 'Ditolak' : 'Belum Diverifikasi') }}
                                </x-badge>
                                <template x-if="canVerifikasiDokumen">
                                    <div class="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            :disabled="savingDokumen[{{ $dokumen->id }}]"
                                            @click="verifikasiDokumen({{ $dokumen->id }}, 'diterima')"
                                            class="rounded-lg border border-signal-green/30 px-2.5 py-1 text-xs font-bold text-signal-green hover:bg-signal-green/5 disabled:opacity-40"
                                        >Terima</button>
                                        <button
                                            type="button"
                                            @click="menolak = !menolak"
                                            class="rounded-lg border border-signal-red/30 px-2.5 py-1 text-xs font-bold text-signal-red hover:bg-signal-red/5"
                                        >Tolak</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div x-show="menolak" x-cloak class="mt-3 flex items-center gap-2">
                            <input
                                type="text"
                                x-model="catatanTolak[{{ $dokumen->id }}]"
                                placeholder="Alasan penolakan (wajib)"
                                class="w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                            >
                            <button
                                type="button"
                                :disabled="savingDokumen[{{ $dokumen->id }}]"
                                @click="verifikasiDokumen({{ $dokumen->id }}, 'ditolak')"
                                class="shrink-0 rounded-xl bg-signal-red px-3 py-2 text-xs font-bold text-white disabled:opacity-40"
                            >Kirim Penolakan</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-panel>

        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Penilaian &amp; Keputusan</h3>
            </div>
            <div class="p-6">
                @if ($seleksiTersedia->isEmpty())
                    <p class="text-sm text-slate">Tidak ada jadwal seleksi untuk jalur ini.</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate">
                                <th class="pb-2">Jenis Tes</th>
                                <th class="pb-2">Bobot</th>
                                <th class="pb-2">Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/10">
                            @foreach ($seleksiTersedia as $seleksi)
                                @php $hasil = $pendaftaran->hasilSeleksi->firstWhere('seleksi_ppdb_id', $seleksi->id); @endphp
                                <tr x-data="{ nilai: {{ $hasil?->nilai ?? 'null' }}, catatan: @js($hasil?->catatan) }">
                                    <td class="py-3 text-ink">{{ $seleksi->jenisTesMaster->nama }}</td>
                                    <td class="py-3 text-slate">{{ $seleksi->bobot }}%</td>
                                    <td class="py-3">
                                        <template x-if="canNilaiSeleksi">
                                            <div class="flex items-center gap-2">
                                                <input type="number" min="0" max="100" step="0.01" x-model="nilai" class="w-24 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                                <button
                                                    type="button"
                                                    :disabled="savingNilai[{{ $seleksi->id }}]"
                                                    @click="simpanNilai({{ $seleksi->id }}, nilai, catatan)"
                                                    class="rounded-lg border border-ink/15 px-2.5 py-1.5 text-xs font-bold text-ink hover:bg-paper disabled:opacity-40"
                                                >Simpan</button>
                                            </div>
                                        </template>
                                        <template x-if="!canNilaiSeleksi">
                                            <span x-text="nilai ?? '-'"></span>
                                        </template>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <div class="mt-6 border-t border-ink/10 pt-6">
                    <p class="text-sm font-medium text-ink">Status saat ini:
                        <x-badge :tone="$pendaftaran->status === 'diterima' ? 'green' : ($pendaftaran->status === 'ditolak' ? 'red' : 'amber')">
                            {{ $pendaftaran->status === 'diterima' ? 'Diterima' : ($pendaftaran->status === 'ditolak' ? 'Ditolak' : 'Menunggu Verifikasi') }}
                        </x-badge>
                    </p>

                    <template x-if="canTetapkanKeputusan">
                        <div class="mt-4 space-y-3">
                            <div>
                                <x-input-label value="Keputusan" />
                                <select x-model="keputusanStatus" class="mt-1.5 w-full max-w-xs rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                    <option value="diterima">Diterima</option>
                                    <option value="ditolak">Ditolak</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Catatan (opsional)" />
                                <x-text-input type="text" x-model="catatanKeputusan" class="mt-1.5" />
                            </div>
                            <button
                                type="button"
                                :disabled="savingKeputusan"
                                @click="tetapkanKeputusan()"
                                class="inline-flex items-center gap-2 rounded-xl bg-ink px-4 py-2.5 text-sm font-bold text-paper shadow-sm transition hover:bg-ink/90 disabled:opacity-60"
                            >
                                <span x-text="savingKeputusan ? 'Menyimpan...' : 'Tetapkan Keputusan'"></span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </x-panel>

        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Tagihan</h3>
            </div>
            <div class="p-6 space-y-4">
                @forelse (['pendaftaran' => 'Tagihan Pendaftaran', 'daftar_ulang' => 'Tagihan Daftar Ulang'] as $kategori => $label)
                    @php $tagihan = $pendaftaran->tagihan->firstWhere('kategori', $kategori); @endphp
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $label }}</p>
                            @if ($tagihan)
                                <p class="text-xs text-slate">Rp {{ number_format($tagihan->total_tagihan, 0, ',', '.') }}</p>
                            @else
                                <p class="text-xs text-slate">Belum ada tagihan</p>
                            @endif
                        </div>
                        @if ($tagihan)
                            <x-badge :tone="$tagihan->status === 'lunas' ? 'green' : ($tagihan->status === 'dicicil' ? 'blue' : 'amber')">
                                {{ ucfirst(str_replace('_', ' ', $tagihan->status)) }}
                            </x-badge>
                        @elseif (auth()->user()->can('tagihan.buat-susulan'))
                            <form method="POST" action="{{ route('admin.tagihan.susulan', $pendaftaran) }}">
                                @csrf
                                <input type="hidden" name="kategori" value="{{ $kategori }}">
                                <button type="submit" class="text-xs font-bold text-ink hover:underline">Buat Tagihan Susulan</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate">Belum ada kategori tagihan.</p>
                @endforelse
            </div>
        </x-panel>
    </div>
</x-app-layout>

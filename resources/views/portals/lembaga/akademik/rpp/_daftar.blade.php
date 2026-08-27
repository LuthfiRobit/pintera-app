<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">
            {{ $tab === 'saya' ? 'Daftar Perangkat Ajar Saya' : 'Inbox Verifikasi Kurikulum (Menunggu Review)' }}
        </p>
        <div class="flex items-center gap-2">
            <label for="per_page" class="text-xs font-medium text-gray-500">Tampilkan:</label>
            <select id="per_page" x-model="perPage" @change="muatUlangDaftar()" class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                <option value="10">10 / hal</option>
                <option value="20">20 / hal</option>
                <option value="25">25 / hal</option>
                <option value="50">50 / hal</option>
            </select>
        </div>
    </div>

    <div class="relative overflow-x-auto">
        <!-- Loading overlay -->
        <div x-show="false" class="absolute inset-0 z-20 flex items-center justify-center bg-white/50 backdrop-blur-sm"
             x-transition.opacity
             @ajax-start.window="$el.style.display = 'flex'"
             @ajax-end.window="$el.style.display = 'none'">
            <x-icon name="sync" class="h-8 w-8 animate-spin text-brand-500" />
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                    <th class="sticky left-0 z-10 bg-white px-5 py-3.5 border-b border-gray-100">Aksi</th>
                    <th class="px-5 py-3.5 border-b border-gray-100">Topik / Lingkup Materi</th>
                    <th class="px-5 py-3.5 border-b border-gray-100">Mata Pelajaran</th>
                    <th class="px-5 py-3.5 border-b border-gray-100">Kelas & Semester</th>
                    <th class="px-5 py-3.5 border-b border-gray-100">Guru Pengampu</th>
                    <th class="px-5 py-3.5 border-b border-gray-100">Alokasi / Pertemuan</th>
                    <th class="px-5 py-3.5 border-b border-gray-100">Berkas Dokumen</th>
                    <th class="px-5 py-3.5 border-b border-gray-100 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($rppList as $rpp)
                    @php
                        $isPdf = str_ends_with(strtolower($rpp->file_name), '.pdf');
                    @endphp
                    <tr class="transition hover:bg-gray-50/75">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3.5">
                            @if ($tab === 'verifikasi')
                                {{-- Tab Verifikasi: Hanya tampil tombol Tinjau jika status Diajukan --}}
                                @if ($rpp->isDiajukan())
                                    <button
                                        type="button"
                                        @click="openVerifyModal($el.dataset)"
                                        data-id="{{ $rpp->id }}"
                                        data-guru-nama="{{ $rpp->guru->nama }}"
                                        data-kelas-nama="{{ $rpp->kelas->nama }}"
                                        data-mapel-nama="{{ $rpp->mataPelajaran?->nama ?? 'Tematik PAUD' }}"
                                        data-judul-topik="{{ $rpp->judul_topik }}"
                                        data-file-name="{{ $rpp->file_name }}"
                                        data-file-url="{{ route('admin.rpp.download', $rpp) }}"
                                        data-action-url="{{ route('admin.rpp.verify', $rpp) }}"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100 border border-emerald-200 transition shadow-xs"
                                    >
                                        <x-icon name="fact_check" class="h-4 w-4" />
                                        <span>Tinjau</span>
                                    </button>
                                @else
                                    <x-table-actions>
                                        @if ($isPdf)
                                            <button
                                                type="button"
                                                @click="bukaBerkas('{{ route('admin.rpp.download', $rpp) }}', '{{ $rpp->file_name }}')"
                                                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-xs font-medium text-brand-700 hover:bg-brand-50 transition"
                                            >
                                                <x-icon name="visibility" class="h-4 w-4 text-brand-500" />
                                                <span>Lihat di Platform</span>
                                            </button>
                                        @endif
                                        <x-dropdown-link :href="route('admin.rpp.download', $rpp)">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="download" class="h-4 w-4 text-gray-500" />
                                                Unduh Berkas Asli
                                            </span>
                                        </x-dropdown-link>
                                    </x-table-actions>
                                @endif
                            @else
                                {{-- Tab Saya (Aksi Guru Pemilik Dokumen) --}}
                                <x-table-actions>
                                    {{-- Pratinjau di Platform jika PDF --}}
                                    @if ($isPdf)
                                        <button
                                            type="button"
                                            @click="bukaBerkas('{{ route('admin.rpp.download', $rpp) }}', '{{ $rpp->file_name }}')"
                                            class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-xs font-medium text-brand-700 hover:bg-brand-50 transition"
                                        >
                                            <x-icon name="visibility" class="h-4 w-4 text-brand-500" />
                                            <span>Lihat di Platform</span>
                                        </button>
                                    @endif

                                    {{-- Unduh Dokumen Asli --}}
                                    <x-dropdown-link :href="route('admin.rpp.download', $rpp)">
                                        <span class="inline-flex items-center gap-2.5">
                                            <x-icon name="download" class="h-4 w-4 text-gray-500" />
                                            Unduh Berkas Asli
                                        </span>
                                    </x-dropdown-link>

                                    {{-- Aksi Edit/Hapus/Ajukan: HANYA jika status Draft atau Perlu Revisi --}}
                                    @if ($rpp->canBeEditedByGuru())
                                        @can('rpp.kelola')
                                            {{-- Ajukan ke Kurikulum --}}
                                            <form
                                                method="POST"
                                                action="{{ route('admin.rpp.submit', $rpp) }}"
                                                x-data
                                                @submit.prevent="confirmDialog('Ajukan RPP ke Kurikulum?', 'Apakah Anda yakin ingin mengajukan berkas ini untuk diverifikasi oleh Waka Kurikulum?', { confirmLabel: 'Ya, Ajukan' }).then(c => { if(c) $el.submit() })"
                                            >
                                                @csrf
                                                <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-xs font-semibold text-brand-600 hover:bg-brand-50 transition">
                                                    <x-icon name="send" class="h-4 w-4" />
                                                    <span>Ajukan ke Kurikulum</span>
                                                </button>
                                            </form>

                                            {{-- Edit --}}
                                            <button
                                                type="button"
                                                @click="openEditModal($el.dataset)"
                                                data-id="{{ $rpp->id }}"
                                                data-semester-id="{{ $rpp->semester_id }}"
                                                data-kelas-id="{{ $rpp->kelas_id }}"
                                                data-mata-pelajaran-id="{{ $rpp->mata_pelajaran_id ?? '' }}"
                                                data-judul-topik="{{ $rpp->judul_topik }}"
                                                data-alokasi-waktu="{{ $rpp->alokasi_waktu }}"
                                                data-pertemuan-ke="{{ $rpp->pertemuan_ke }}"
                                                data-url="{{ route('admin.rpp.update', $rpp) }}"
                                                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-xs text-gray-700 hover:bg-gray-100 transition"
                                            >
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                <span>Edit Dokumen</span>
                                            </button>

                                            {{-- Hapus --}}
                                            <form
                                                method="POST"
                                                action="{{ route('admin.rpp.destroy', $rpp) }}"
                                                x-data
                                                @submit.prevent="confirmDialog('Hapus RPP?', 'Apakah Anda yakin ingin menghapus dokumen RPP ini?', { confirmLabel: 'Ya, Hapus', isDanger: true }).then(c => { if(c) $el.submit() })"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-xs text-rose-600 hover:bg-rose-50 transition">
                                                    <x-icon name="delete" class="h-4 w-4 text-rose-500" />
                                                    <span>Hapus Dokumen</span>
                                                </button>
                                            </form>
                                        @endcan
                                    @endif
                                </x-table-actions>
                            @endif
                        </td>

                        {{-- Judul Topik --}}
                        <td class="px-5 py-3.5">
                            <span class="font-semibold text-gray-900 block max-w-xs truncate" title="{{ $rpp->judul_topik }}">{{ $rpp->judul_topik }}</span>
                            <span class="text-[11px] text-gray-400 font-mono">Dibuat: {{ $rpp->created_at->translatedFormat('d M Y') }}</span>
                            @if ($rpp->isPerluRevisi() && $rpp->catatan_revisi)
                                <div class="mt-1.5 p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800">
                                    <strong class="font-bold flex items-center gap-1 text-rose-700">
                                        <x-icon name="warning" class="h-3.5 w-3.5" /> Catatan Revisi:
                                    </strong>
                                    <p class="italic mt-0.5 text-rose-900 font-medium">{{ $rpp->catatan_revisi }}</p>
                                </div>
                            @endif
                        </td>

                        {{-- Mata Pelajaran --}}
                        <td class="px-5 py-3.5">
                            @if ($rpp->mataPelajaran)
                                <x-badge tone="blue">{{ $rpp->mataPelajaran->nama }}</x-badge>
                            @else
                                <x-badge tone="purple">Tematik / Sentra PAUD</x-badge>
                            @endif
                        </td>

                        {{-- Kelas & Semester --}}
                        <td class="px-5 py-3.5 text-gray-700 text-xs">
                            <span class="font-bold text-gray-900">{{ $rpp->kelas->nama }}</span>
                            <p class="text-gray-500 text-[11px]">{{ $rpp->semester->nama }} &bull; {{ $rpp->tahunAjaran->nama ?? '' }}</p>
                            @if ($rpp->kelas->kurikulum)
                                <x-badge tone="{{ $rpp->kelas->kurikulum->value === 'merdeka' ? 'green' : 'blue' }}">{{ $rpp->kelas->kurikulum->label() }}</x-badge>
                            @else
                                <x-badge tone="slate">Belum Diketahui</x-badge>
                            @endif
                        </td>

                        {{-- Guru Pengampu --}}
                        <td class="px-5 py-3.5 text-gray-800 text-xs font-medium">
                            {{ $rpp->guru->nama }}
                        </td>

                        {{-- Alokasi Waktu & Pertemuan --}}
                        <td class="px-5 py-3.5 text-gray-700 text-xs">
                            <span>{{ $rpp->alokasi_waktu }}</span>
                            @if ($rpp->pertemuan_ke)
                                <p class="text-gray-400 text-[11px]">Pertemuan: {{ $rpp->pertemuan_ke }}</p>
                            @endif
                        </td>

                        {{-- Berkas Dokumen --}}
                        <td class="px-5 py-3.5">
                            @if ($isPdf)
                                <button
                                    type="button"
                                    @click="bukaBerkas('{{ route('admin.rpp.download', $rpp) }}', '{{ $rpp->file_name }}')"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-brand-200 bg-brand-50/70 hover:bg-brand-100 text-xs font-semibold text-brand-700 transition group max-w-[180px]"
                                    title="Klik untuk membuka pratinjau di platform"
                                >
                                    <x-icon name="visibility" class="h-3.5 w-3.5 text-brand-600 shrink-0" />
                                    <span class="truncate">{{ $rpp->file_name }}</span>
                                </button>
                            @else
                                <a
                                    href="{{ route('admin.rpp.download', $rpp) }}"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 text-xs font-semibold text-gray-700 transition group max-w-[180px]"
                                    title="Klik untuk mengunduh dokumen Word"
                                >
                                    <x-icon name="description" class="h-3.5 w-3.5 text-blue-500 shrink-0" />
                                    <span class="truncate">{{ $rpp->file_name }}</span>
                                </a>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-5 py-3.5 text-center">
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold border {{ $rpp->status->badgeClasses() }}">
                                <x-icon name="{{ $rpp->status->icon() }}" class="h-3 w-3" />
                                <span>{{ $rpp->status->label() }}</span>
                            </span>
                            @if ($rpp->isDisetujui() && $rpp->verifiedBy)
                                <p class="text-[10px] text-gray-400 mt-0.5">Oleh: {{ $rpp->verifiedBy->name }}</p>
                            @endif
                        </td>
                    </tr>
                @endforeach

                @if ($rppList->isEmpty())
                    <tr>
                        <td colspan="8" class="px-5 py-12 text-center text-gray-500">
                            <x-icon name="description" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
                            <p class="font-semibold text-gray-700">
                                {{ $tab === 'verifikasi' ? 'Tidak ada perangkat ajar yang sedang menunggu review verifikasi kurikulum.' : 'Belum ada dokumen perangkat ajar yang cocok dengan filter.' }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $tab === 'verifikasi' ? 'Semua pengajuan RPP telah selesai ditinjau.' : 'Silakan sesuaikan kriteria filter atau unggah dokumen baru.' }}
                            </p>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    @if ($rppList->hasPages())
        <div class="border-t border-gray-200 px-5 py-3">
            {{ $rppList->links() }}
        </div>
    @endif
</div>

<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="{ selectedProposal: null, modalOpen: false }">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Pencairan Dana Kas Pengadaan</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola dan catat realisasi pencairan dana untuk usulan belanja yang telah disetujui.</p>
            </div>
            <p class="text-sm text-gray-500">
                Yayasan <span class="mx-1 text-gray-300">&rsaquo;</span> Pengadaan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pencairan Kas</b>
            </p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-700">
                    <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th scope="col" class="px-6 py-4">Unit Sekolah</th>
                            <th scope="col" class="px-6 py-4">Pengajuan</th>
                            <th scope="col" class="px-6 py-4 text-right">Anggaran Disetujui</th>
                            <th scope="col" class="px-6 py-4 text-center">Status Kas</th>
                            <th scope="col" class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-normal">
                        @forelse ($proposals as $p)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4 font-semibold text-gray-900">
                                    {{ $p->lembaga->nama ?? 'Sekolah' }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">{{ $p->judul_pengajuan }}</div>
                                    <div class="text-xs text-gray-500 font-mono mt-0.5">{{ $p->nomor_pengajuan }}</div>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-gray-900">
                                    Rp {{ number_format($p->total_estimasi, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if ($p->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Disbursed)
                                        <x-badge tone="green">Sudah Dicairkan</x-badge>
                                        <div class="text-[11px] text-gray-500 mt-0.5">Rp {{ number_format($p->nominal_pencairan, 0, ',', '.') }}</div>
                                    @else
                                        <x-badge tone="amber">Siap Dicairkan</x-badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if ($p->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Approved)
                                        <button
                                            type="button"
                                            @click="selectedProposal = @js($p); $dispatch('open-modal', 'modal-pencairan-kas')"
                                            class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 shadow-sm"
                                        >
                                            <x-icon name="payments" class="h-4 w-4" /> Catat Pencairan
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">Tercatat ({{ $p->tanggal_pencairan?->format('d/m/y') }})</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <p class="font-medium text-gray-900">Belum ada proposal yang menunggu pencairan dana</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($proposals->hasPages() || $proposals->total() > 0)
                <div class="border-t border-gray-200 px-6 py-4">
                    {{ $proposals->links('pagination.tailadmin') }}
                </div>
            @endif
        </div>

        {{-- Modal Pencairan Kas --}}
        <x-modal name="modal-pencairan-kas" maxWidth="md" focusable>
            <form
                :action="selectedProposal ? `/admin/pengadaan/disbursement/${selectedProposal.id}` : '#'"
                method="POST"
                enctype="multipart/form-data"
                class="p-6 space-y-4"
            >
                @csrf

                <div class="border-b border-gray-100 pb-3">
                    <h3 class="font-display text-base font-bold text-gray-900">Catat Realisasi Pencairan Dana Kas</h3>
                    <p class="text-xs text-gray-500 mt-0.5" x-text="selectedProposal ? `${selectedProposal.nomor_pengajuan} - ${selectedProposal.judul_pengajuan}` : ''"></p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nominal Dana yang Dicairkan (Rp) <span class="text-error-600">*</span></label>
                    <input type="number" name="nominal_pencairan" :value="selectedProposal ? selectedProposal.total_estimasi : 0" required min="1" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Pencairan <span class="text-error-600">*</span></label>
                    <input type="date" name="tanggal_pencairan" value="{{ date('Y-m-d') }}" required class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan / Rekening Kas</label>
                    <textarea name="catatan_pencairan" rows="2" placeholder="Contoh: Transfer via Rekening BSI Yayasan ke Bendahara SMP" class="w-full rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Bukti Transfer / Tanda Terima</label>
                    <input type="file" name="bukti_transfer" accept="image/*,application/pdf" class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-gray-100">
                </div>

                <div class="flex items-center justify-end gap-3 pt-3">
                    <x-secondary-button type="button" @click="$dispatch('close')">
                        Batal
                    </x-secondary-button>

                    <x-primary-button type="submit">
                        Simpan Pencairan
                    </x-primary-button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>

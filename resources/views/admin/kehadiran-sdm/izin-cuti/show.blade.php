{{-- resources/views/admin/kehadiran-sdm/izin-cuti/show.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0">
        
        {{-- Flash Notification --}}
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-xs font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-xs font-semibold text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Inline Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Review Pengajuan {{ $izinCuti->pegawai->nama ?? '' }}</h1>
                <p class="text-xs text-gray-500 mt-0.5">Detail permohonan izin/cuti dan riwayat keputusan bertingkat.</p>
            </div>
            <div class="flex items-center gap-2">
                <p class="hidden text-sm text-gray-500 sm:block mr-2">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> SDM <span class="mx-1 text-gray-300">&rsaquo;</span> Persetujuan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Review Tiket</b>
                </p>
                <a href="{{ route('admin.kehadiran-sdm.izin-cuti.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50">
                    &larr; <span>Kembali</span>
                </a>
            </div>
        </div>

        {{-- 2-Column Wide Grid Layout --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-start">
            
            {{-- Left Column: Ticket Details & Decision Form --}}
            <div class="lg:col-span-7 space-y-4">
                
                {{-- Ticket Details Card --}}
                <div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
                    {{-- Header Bar --}}
                    <div class="flex flex-wrap items-center justify-between border-b border-gray-100 bg-gray-50/50 px-5 py-4 gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 font-display text-sm font-bold text-brand-700">
                                {{ strtoupper(substr($izinCuti->pegawai->nama ?? 'P', 0, 2)) }}
                            </div>
                            <div>
                                <h2 class="font-display text-sm font-bold text-gray-900">{{ $izinCuti->pegawai->nama ?? '—' }}</h2>
                                <p class="text-xs text-gray-500">Pegawai SDM &amp; Kepegawaian</p>
                            </div>
                        </div>

                        @php $ar = $izinCuti->approvalRequest; @endphp
                        @if ($ar)
                            <span class="inline-flex items-center rounded-full bg-{{ $ar->status->badgeTone() }}-100 px-3 py-1 text-xs font-semibold text-{{ $ar->status->badgeTone() }}-800">
                                {{ $ar->status->label() }}
                            </span>
                        @endif
                    </div>

                    {{-- Info Grid --}}
                    <div class="p-5 space-y-4">
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-3.5">
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Kategori Permohonan</dt>
                                <dd class="mt-1 text-xs font-bold text-gray-900">{{ $izinCuti->kategori->label() }}</dd>
                            </div>

                            <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-3.5">
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Periode Tanggal</dt>
                                <dd class="mt-1 font-mono text-xs font-bold text-gray-900">
                                    {{ $izinCuti->tanggal_mulai->format('d M Y') }} — {{ $izinCuti->tanggal_selesai->format('d M Y') }}
                                </dd>
                            </div>

                            <div class="sm:col-span-2 rounded-xl border border-gray-100 bg-gray-50/50 p-3.5">
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Alasan Pengajuan</dt>
                                <dd class="mt-1 text-xs leading-relaxed text-gray-700 whitespace-pre-line">{{ $izinCuti->alasan }}</dd>
                            </div>

                            <div class="sm:col-span-2 flex items-center justify-between rounded-xl border border-amber-100 bg-amber-50/40 p-3.5">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-700">Langkah Tahap Saat Ini</p>
                                    <p class="text-xs font-bold text-gray-900 mt-0.5">{{ $ar?->currentStep?->step_name ?? '—' }}</p>
                                </div>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                    <span>Proses Review</span>
                                </span>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Decision Form Card --}}
                @if ($ar && in_array($ar->status->value, ['pending', 'in_review'], true))
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4" x-data="{ actionToSubmit: '', notes: '' }">
                        <div class="border-b border-gray-100 pb-3">
                            <h3 class="font-display text-sm font-bold text-gray-900">Form Keputusan Approver</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Pilih keputusan persetujuan atau penolakan permohonan ini.</p>
                        </div>
                        
                        <form id="decisionForm" method="POST" action="{{ route('admin.kehadiran-sdm.izin-cuti.decision', $izinCuti) }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="action" :value="actionToSubmit">

                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Catatan Approver (opsional)</label>
                                <textarea 
                                    name="notes" 
                                    x-model="notes" 
                                    rows="3" 
                                    placeholder="Tuliskan catatan penjelasan persetujuan atau alasan penolakan..." 
                                    class="w-full rounded-xl border-gray-200 bg-gray-50 p-3 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"
                                ></textarea>
                            </div>

                            <div class="flex items-center justify-end gap-2.5 pt-1">
                                <button 
                                    type="button" 
                                    @click="
                                        actionToSubmit = 'REJECT';
                                        confirmDialog('Tolak Pengajuan Izin/Cuti?', 'Apakah Anda yakin ingin MENOLAK permohonan pengajuan ini?', { confirmLabel: 'Ya, Tolak Pengajuan', isDanger: true })
                                        .then(confirmed => { if (confirmed) $el.form.submit() })
                                    " 
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-4 py-2 text-xs font-semibold text-rose-600 shadow-2xs hover:bg-rose-50 transition active:scale-[0.98]"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>Tolak</span>
                                </button>

                                <button 
                                    type="button" 
                                    @click="
                                        actionToSubmit = 'APPROVE';
                                        confirmDialog('Setujui Pengajuan Izin/Cuti?', 'Apakah Anda yakin ingin MENYETUJUI permohonan pengajuan ini?', { confirmLabel: 'Ya, Setujui Pengajuan' })
                                        .then(confirmed => { if (confirmed) $el.form.submit() })
                                    " 
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition active:scale-[0.98]"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Setujui</span>
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>

            {{-- Right Column: Approval Audit Trail Timeline --}}
            <div class="lg:col-span-5">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                    <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-display text-sm font-bold text-gray-900">Riwayat Keputusan</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Audit trail persetujuan bertingkat.</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-600">
                            {{ $ar?->logs->count() ?? 0 }} Log
                        </span>
                    </div>

                    @if ($ar?->logs->isNotEmpty())
                        <div class="relative pl-6 space-y-4 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-gray-200">
                            @foreach ($ar->logs as $log)
                                <div class="relative">
                                    <span class="absolute -left-6 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-brand-600 text-white ring-4 ring-white">
                                        <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-3 text-xs space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-gray-900">{{ $log->user->name ?? '—' }}</span>
                                            <span class="rounded bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700 border border-brand-200">
                                                {{ $log->action->label() }}
                                            </span>
                                        </div>
                                        @if ($log->notes)
                                            <p class="text-gray-600 italic bg-white p-2 rounded-lg border border-gray-100 text-[11px] mt-1">
                                                &ldquo;{{ $log->notes }}&rdquo;
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-8 text-center text-gray-400">
                            <svg class="mx-auto h-8 w-8 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-xs font-semibold text-gray-600">Belum ada riwayat keputusan.</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Pengajuan ini sedang menunggu keputusan pertama.</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

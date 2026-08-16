<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
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
                <div class="flex items-center gap-3">
                    <h1 class="font-display text-lg font-bold text-gray-900">{{ $proposal->judul_pengajuan }}</h1>
                    <x-badge :tone="$proposal->status->badgeTone()">
                        {{ $proposal->status->label() }}
                    </x-badge>
                </div>
                <p class="text-xs text-gray-500 mt-0.5 font-mono">
                    Nomor: {{ $proposal->nomor_pengajuan }} &bull; Diajukan oleh: <b>{{ $proposal->pengaju->name ?? 'Admin' }}</b> &bull; {{ $proposal->created_at->translatedFormat('d F Y H:i') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <x-link-button variant="secondary" href="{{ route('admin.pengadaan.proposal.index') }}">
                    <x-icon name="arrow_back" class="h-4 w-4 mr-1" /> Kembali
                </x-link-button>

                @if ($proposal->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Draft)
                    <form action="{{ route('admin.pengadaan.proposal.submit', $proposal) }}" method="POST">
                        @csrf
                        <x-primary-button type="submit">
                            <x-icon name="send" class="h-4 w-4 mr-1" /> Ajukan Proposal
                        </x-primary-button>
                    </form>
                @endif

                @if ($proposal->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Disbursed)
                    <x-link-button href="{{ route('admin.pengadaan.lpj.create', $proposal) }}">
                        <x-icon name="receipt_long" class="h-4 w-4 mr-1" /> Unggah LPJ Belanja
                    </x-link-button>
                @endif

                @if ($proposal->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Completed && $proposal->lpj && $proposal->lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::Verified)
                    <x-link-button href="{{ route('admin.pengadaan.lpj.staging-inventory', $proposal->lpj) }}">
                        <x-icon name="inventory_2" class="h-4 w-4 mr-1" /> Konversi ke Sarpras
                    </x-link-button>
                @endif
            </div>
        </div>

        {{-- Workflow Step Action Banner --}}
        @if (in_array($proposal->status, [\App\Domains\Pengadaan\Enums\StatusPengajuan::Submitted, \App\Domains\Pengadaan\Enums\StatusPengajuan::InReview]))
            @php
                $currentStep = $proposal->approvalRequest?->currentStep;
                $canApprove = $proposal->canBeApprovedBy(auth()->user());
            @endphp
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-4 shadow-card {{ $canApprove ? 'border-brand-200 bg-brand-50/70' : 'border-amber-200 bg-amber-50/60' }}">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $canApprove ? 'bg-brand-600 text-white' : 'bg-amber-100 text-amber-700' }}">
                        <x-icon name="{{ $canApprove ? 'rate_review' : 'hourglass_top' }}" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider {{ $canApprove ? 'text-brand-700' : 'text-amber-800' }}">
                            {{ $canApprove ? 'Persetujuan Diperlukan Dari Anda' : 'Sedang Dalam Proses Persetujuan' }}
                        </p>
                        <p class="text-sm font-semibold text-gray-900 mt-0.5">
                            Langkah Aktif: {{ $currentStep->step_name ?? 'Verifikasi Proposal' }}
                            @if ($currentStep)
                                <span class="text-xs text-gray-500 font-normal">({{ ucwords(str_replace('_', ' ', $currentStep->approver_value)) }})</span>
                            @endif
                        </p>
                    </div>
                </div>
                @if ($canApprove)
                    <x-link-button href="{{ route('admin.pengadaan.inbox.review', $proposal) }}">
                        <x-icon name="arrow_forward" class="h-4 w-4 mr-1" /> Proses Persetujuan Sekarang
                    </x-link-button>
                @endif
            </div>
        @endif

        {{-- Stepper Workflow Lifecycle --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">Tahapan Alur Pengadaan</h2>

            @php
                $steps = [
                    ['id' => 'draft', 'name' => '1. Usulan Proposal', 'desc' => 'Penyusunan item belanja'],
                    ['id' => 'review', 'name' => '2. Verifikasi & Approval', 'desc' => 'Kepsek & Yayasan'],
                    ['id' => 'disburse', 'name' => '3. Pencairan Kas', 'desc' => 'Bendahara Yayasan'],
                    ['id' => 'lpj', 'name' => '4. LPJ & Realisasi', 'desc' => 'Upload nota fisik'],
                    ['id' => 'completed', 'name' => '5. Inventarisasi Sarpras', 'desc' => 'Auto-generate Aset'],
                ];

                $currentStepIdx = match ($proposal->status) {
                    \App\Domains\Pengadaan\Enums\StatusPengajuan::Draft => 0,
                    \App\Domains\Pengadaan\Enums\StatusPengajuan::Submitted, \App\Domains\Pengadaan\Enums\StatusPengajuan::InReview, \App\Domains\Pengadaan\Enums\StatusPengajuan::RevisionRequired => 1,
                    \App\Domains\Pengadaan\Enums\StatusPengajuan::Approved => 2,
                    \App\Domains\Pengadaan\Enums\StatusPengajuan::Disbursed => 3,
                    \App\Domains\Pengadaan\Enums\StatusPengajuan::Completed => 4,
                    default => 0,
                };
            @endphp

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-5">
                @foreach ($steps as $idx => $step)
                    <div class="flex flex-col items-center text-center p-2 rounded-xl {{ $idx === $currentStepIdx ? 'bg-brand-50/75 border border-brand-200' : '' }}">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold mb-1.5
                            {{ $idx < $currentStepIdx || $proposal->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Completed
                                ? 'bg-emerald-500 text-white'
                                : ($idx === $currentStepIdx ? 'bg-brand-600 text-white ring-4 ring-brand-100' : 'bg-gray-100 text-gray-400') }}">
                            @if ($idx < $currentStepIdx || $proposal->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Completed)
                                ✓
                            @else
                                {{ $idx + 1 }}
                            @endif
                        </div>
                        <p class="text-xs font-bold text-gray-800 leading-tight">{{ $step['name'] }}</p>
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ $step['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Proposal Items Table --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div>
                    <h2 class="font-display text-sm font-bold text-gray-900">Rincian Barang yang Diajukan</h2>
                    <p class="text-xs text-gray-500">Daftar item belanja berserta ruangan target penempatan.</p>
                </div>
                <div class="text-right">
                    <span class="text-xs text-gray-500">Total Anggaran Disetujui:</span>
                    <p class="text-base font-extrabold text-brand-700">Rp {{ number_format($proposal->total_estimasi, 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-700">
                    <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">Barang & Spesifikasi</th>
                            <th scope="col" class="px-4 py-3">Kategori</th>
                            <th scope="col" class="px-4 py-3">Ruangan</th>
                            <th scope="col" class="px-4 py-3 text-center">Tipe</th>
                            <th scope="col" class="px-4 py-3 text-center">Qty</th>
                            <th scope="col" class="px-4 py-3 text-right">Harga Satuan</th>
                            <th scope="col" class="px-4 py-3 text-right">Subtotal</th>
                            <th scope="col" class="px-4 py-3 text-center">Status Item</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        @foreach ($proposal->items as $item)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900">{{ $item->nama_barang }}</div>
                                    @if ($item->merk) <div class="text-gray-500 font-medium">Merk: {{ $item->merk }}</div> @endif
                                    @if ($item->spesifikasi) <div class="text-gray-400 mt-0.5 italic">{{ $item->spesifikasi }}</div> @endif
                                    @if ($item->catatan_reviewer)
                                        <div class="mt-1 rounded bg-amber-50 px-2 py-1 text-[11px] text-amber-700 border border-amber-200">
                                            <b>Catatan Reviewer:</b> {{ $item->catatan_reviewer }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $item->kategori->nama_kategori ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $item->ruangan->nama_ruangan ?? '-' }}</td>
                                <td class="px-4 py-3 text-center uppercase font-mono text-[10px] text-gray-500">{{ $item->tipe_pencatatan->value ?? 'unit' }}</td>
                                <td class="px-4 py-3 text-center font-bold text-gray-800">{{ $item->qty }} {{ $item->satuan }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($item->estimasi_harga_satuan, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-bold text-gray-900">Rp {{ number_format($item->total_estimasi, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <x-badge :tone="$item->status_item->badgeTone()">
                                        {{ $item->status_item->label() }}
                                    </x-badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pencairan Dana Information --}}
        @if ($proposal->nominal_pencairan)
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50/40 p-6 shadow-card space-y-3">
                <div class="flex items-center gap-2">
                    <x-icon name="payments" class="h-5 w-5 text-indigo-600" />
                    <h2 class="font-display text-sm font-bold text-indigo-900">Informasi Pencairan Dana Kas Yayasan</h2>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 text-xs">
                    <div>
                        <span class="text-indigo-600 font-semibold">Nominal Uang Cair:</span>
                        <p class="text-base font-bold text-indigo-950">Rp {{ number_format($proposal->nominal_pencairan, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <span class="text-indigo-600 font-semibold">Tanggal Dicairkan:</span>
                        <p class="text-sm font-medium text-indigo-950">{{ $proposal->tanggal_pencairan?->translatedFormat('d F Y') }}</p>
                    </div>
                    <div>
                        <span class="text-indigo-600 font-semibold">Catatan Kasir / Bendahara:</span>
                        <p class="text-xs text-indigo-900 italic">{{ $proposal->catatan_pencairan ?? '-' }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Approval Logs Timeline --}}
        @if ($proposal->approvalRequest && $proposal->approvalRequest->logs->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                <h2 class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Riwayat Catatan Persetujuan</h2>
                <div class="space-y-3">
                    @foreach ($proposal->approvalRequest->logs as $log)
                        <div class="flex items-start gap-3 rounded-xl border border-gray-100 bg-gray-50/50 p-3 text-xs">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700 font-bold">
                                <x-icon name="person" class="h-4 w-4" />
                            </span>
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-gray-900">{{ $log->user->name ?? 'Reviewer' }}</span>
                                    <x-badge :tone="$log->action === \App\Domains\Workflow\Enums\ApprovalAction::Approve ? 'green' : ($log->action === \App\Domains\Workflow\Enums\ApprovalAction::Reject ? 'rose' : 'amber')">
                                        {{ $log->action->label() }}
                                    </x-badge>
                                    <span class="text-[11px] text-gray-400">&bull; {{ $log->created_at->translatedFormat('d M Y H:i') }}</span>
                                </div>
                                @if ($log->notes)
                                    <p class="text-gray-600 italic">"{{ $log->notes }}"</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>

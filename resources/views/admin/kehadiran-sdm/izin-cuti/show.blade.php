<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-6">
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">SDM &amp; Kepegawaian</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Review Pengajuan {{ $izinCuti->pegawai->nama ?? '' }}</h1>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-xs text-gray-400">Kategori</dt><dd class="font-semibold text-gray-900">{{ $izinCuti->kategori->label() }}</dd></div>
                <div><dt class="text-xs text-gray-400">Tanggal</dt><dd class="font-semibold text-gray-900">{{ $izinCuti->tanggal_mulai->format('d M Y') }} — {{ $izinCuti->tanggal_selesai->format('d M Y') }}</dd></div>
                <div class="col-span-2"><dt class="text-xs text-gray-400">Alasan</dt><dd class="text-gray-700">{{ $izinCuti->alasan }}</dd></div>
                <div><dt class="text-xs text-gray-400">Langkah Saat Ini</dt><dd class="text-gray-700">{{ $izinCuti->approvalRequest?->currentStep?->step_name ?? '—' }}</dd></div>
            </dl>

            @if ($izinCuti->approvalRequest?->logs->isNotEmpty())
                <div class="mt-4 border-t border-gray-100 pt-4">
                    <p class="text-xs font-semibold text-gray-500">Riwayat Keputusan</p>
                    <ul class="mt-2 space-y-1 text-xs text-gray-600">
                        @foreach ($izinCuti->approvalRequest->logs as $log)
                            <li>{{ $log->user->name ?? '—' }} — {{ $log->action->label() }}@if($log->notes): {{ $log->notes }}@endif</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.kehadiran-sdm.izin-cuti.decision', $izinCuti) }}" class="mt-6 space-y-4 border-t border-gray-100 pt-4">
                @csrf
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Catatan (opsional)</label>
                    <textarea name="notes" rows="2" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="submit" name="action" value="REJECT" class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50">Tolak</button>
                    <button type="submit" name="action" value="APPROVE" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Setujui</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

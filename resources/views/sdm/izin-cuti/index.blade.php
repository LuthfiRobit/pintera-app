<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">Kehadiran Saya</p>
                <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Riwayat Izin/Cuti</h1>
            </div>
            @can('kehadiran-sdm.izin.ajukan')
                <a href="{{ route('sdm.izin-cuti.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600">+ Ajukan Baru</a>
            @endcan
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3">Kategori</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Langkah Saat Ini</th>
                        <th class="px-5 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($riwayat as $item)
                        @php $ar = $item->approvalRequest; @endphp
                        <tr>
                            <td class="px-5 py-3 font-semibold text-gray-900">{{ $item->kategori->label() }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $item->tanggal_mulai->format('d M Y') }} — {{ $item->tanggal_selesai->format('d M Y') }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center rounded-full bg-{{ $ar?->status->badgeTone() }}-100 px-2.5 py-1 text-xs font-semibold text-{{ $ar?->status->badgeTone() }}-800">
                                    {{ $ar?->status->label() ?? '—' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ $ar?->currentStep?->step_name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @if ($ar && in_array($ar->status->value, ['pending', 'in_review'], true))
                                    <form method="POST" action="{{ route('sdm.izin-cuti.destroy', $item) }}" onsubmit="return confirm('Batalkan pengajuan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Batalkan</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-sm text-gray-400">Belum ada pengajuan izin/cuti.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

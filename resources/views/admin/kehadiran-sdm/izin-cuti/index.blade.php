<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">SDM &amp; Kepegawaian</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Persetujuan Izin/Cuti</h1>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3">Pegawai</th>
                        <th class="px-5 py-3">Kategori</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3">Langkah Saat Ini</th>
                        <th class="px-5 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($daftar as $item)
                        <tr>
                            <td class="px-5 py-3 font-semibold text-gray-900">{{ $item->pegawai->nama ?? '—' }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $item->kategori->label() }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $item->tanggal_mulai->format('d M Y') }} — {{ $item->tanggal_selesai->format('d M Y') }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $item->approvalRequest?->currentStep?->step_name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.kehadiran-sdm.izin-cuti.show', $item) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-sm text-gray-400">Tidak ada pengajuan menunggu persetujuan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

{{-- resources/views/admin/kasus/akses-log.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Log Akses Klinis</h1>
                <p class="text-xs text-gray-500 mt-0.5">Riwayat siapa yang membuka catatan klinis kasus pendampingan, dan kapan.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Log Akses Klinis</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">Pengakses</th>
                            <th class="px-5 py-3">Siswa</th>
                            <th class="px-5 py-3">Kasus</th>
                            <th class="px-5 py-3">Waktu Akses</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($logs as $log)
                            <tr class="transition hover:bg-gray-50">
                                <td class="px-5 py-3.5 font-medium text-gray-900">{{ $log->causer?->name ?? 'Pengguna tidak diketahui' }}</td>
                                <td class="px-5 py-3.5 text-gray-700">{{ $log->subject?->siswa?->nama_lengkap ?? '—' }}</td>
                                <td class="px-5 py-3.5 text-gray-700">{{ $log->subject?->kategori_masalah ?? '—' }}</td>
                                <td class="px-5 py-3.5 text-gray-500 font-mono text-xs">{{ $log->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <x-icon name="fact_check" class="h-7 w-7" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-700">Belum Ada Akses Tercatat</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">
                {{ $logs->links('pagination.tailadmin') }}
            </div>
        </div>
    </div>
</x-app-layout>

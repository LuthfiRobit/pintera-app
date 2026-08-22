<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">SDM &amp; Kepegawaian</p>
                <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Kehadiran SDM</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.kehadiran-sdm.konfigurasi.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">Konfigurasi</a>
                @can('kehadiran-sdm.catat')
                    <a href="{{ route('admin.kehadiran-sdm.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98]">+ Catat Kehadiran</a>
                @endcan
            </div>
        </div>

        <form method="GET" class="flex items-center gap-3">
            <label class="text-xs font-semibold text-gray-600">Tanggal</label>
            <input type="date" name="tanggal" value="{{ $tanggal }}" onchange="this.form.submit()" class="rounded-lg border-gray-200 text-sm">
        </form>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3">Pegawai</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Masuk</th>
                        <th class="px-5 py-3">Pulang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recordList as $record)
                        <tr>
                            <td class="px-5 py-3 font-semibold text-gray-900">{{ $record->pegawai->nama ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center rounded-full bg-{{ $record->status->badgeTone() }}-100 px-2.5 py-1 text-xs font-semibold text-{{ $record->status->badgeTone() }}-800">
                                    {{ $record->status->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ $record->waktu_masuk?->format('H:i') ?? '—' }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $record->waktu_pulang?->format('H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-12 text-center text-sm text-gray-400">Belum ada data kehadiran pada tanggal ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

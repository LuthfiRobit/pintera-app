{{-- resources/views/kasus/index.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Kasus Pendampingan</h1>
                <p class="text-xs text-gray-500 mt-0.5">Daftar kasus pendampingan yang relevan untuk Anda.</p>
            </div>
            @can('kasus.ajukan')
                <x-link-button href="{{ route('kasus.create') }}">
                    <span class="text-base leading-none">+</span> Ajukan Kasus
                </x-link-button>
            @endcan
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-600">
                        <x-icon name="folder_open" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-600">Total Kasus</p>
                        <p class="font-display text-lg font-bold leading-tight text-gray-900">{{ $totalKasus }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Terdaftar</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <x-icon name="trending_up" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-green-600">Sedang Berjalan</p>
                        <p class="font-display text-lg font-bold leading-tight text-gray-900">{{ $totalBerjalan }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Proses bimbingan</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="priority_high" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Butuh Tindakan</p>
                        <p class="font-display text-lg font-bold leading-tight text-gray-900">{{ $totalButuhTindakan }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Triase/Consent</span>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Kasus</p>
                <x-badge tone="brass" class="text-xs font-semibold px-2.5 py-0.5">{{ $kasusList->count() }} Data</x-badge>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">Siswa</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Diajukan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($kasusList as $item)
                            <tr class="cursor-pointer transition hover:bg-gray-50" onclick="window.location='{{ route('kasus.show', $item) }}'">
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $item->siswa->nama_lengkap }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $item->kategori_masalah }}</td>
                                <td class="px-5 py-3.5">
                                    <x-badge tone="{{ $item->status->badgeTone() }}">{{ $item->status->label() }}</x-badge>
                                </td>
                                <td class="px-5 py-3.5 text-gray-500">{{ $item->created_at->format('d M Y') }}</td>
                            </tr>
                        @endforeach

                        @if ($kasusList->isEmpty())
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <x-icon name="folder_open" class="h-7 w-7" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-700">Belum Ada Kasus</p>
                                    <p class="mx-auto mt-0.5 max-w-sm text-xs text-gray-400">Belum ada catatan atau pengajuan kasus pendampingan terdaftar saat ini.</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

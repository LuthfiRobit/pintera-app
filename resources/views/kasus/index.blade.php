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
                                    <x-badge tone="slate">{{ $item->status->label() }}</x-badge>
                                </td>
                                <td class="px-5 py-3.5 text-gray-500">{{ $item->created_at->format('d M Y') }}</td>
                            </tr>
                        @endforeach

                        @if ($kasusList->isEmpty())
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-gray-400">
                                    <p class="text-sm font-semibold text-gray-700">Belum Ada Kasus</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

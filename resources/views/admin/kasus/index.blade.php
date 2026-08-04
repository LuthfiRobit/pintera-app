<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Triase Kasus Pendampingan</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kasus yang menunggu pemilihan konselor.</p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">Siswa</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Diajukan</th>
                            <th class="px-5 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($kasusList as $item)
                            <tr class="transition hover:bg-gray-50">
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $item->siswa->nama_lengkap }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $item->kategori_masalah }}</td>
                                <td class="px-5 py-3.5 text-gray-500">{{ $item->created_at->format('d M Y') }}</td>
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('admin.kasus.triase', $item) }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700">Triase</a>
                                </td>
                            </tr>
                        @endforeach

                        @if ($kasusList->isEmpty())
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-gray-400">
                                    <p class="text-sm font-semibold text-gray-700">Tidak Ada Kasus yang Menunggu Triase</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

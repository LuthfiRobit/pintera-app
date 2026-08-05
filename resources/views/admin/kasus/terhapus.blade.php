{{-- resources/views/admin/kasus/terhapus.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Kasus Terhapus</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kasus yang sudah dihapus (soft-delete) dan dapat dipulihkan kembali.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Kasus Terhapus</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">Siswa</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Dihapus Pada</th>
                            <th class="px-5 py-3 w-36">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($kasusList as $kasus)
                            <tr class="transition hover:bg-gray-50">
                                <td class="px-5 py-3.5 font-medium text-gray-900">{{ $kasus->siswa?->nama_lengkap ?? '—' }}</td>
                                <td class="px-5 py-3.5 text-gray-700">{{ $kasus->kategori_masalah }}</td>
                                <td class="px-5 py-3.5 text-gray-500 font-mono text-xs">{{ $kasus->deleted_at->format('d M Y H:i') }}</td>
                                <td class="px-5 py-3.5">
                                    @can('kasus.pulihkan')
                                        <form method="POST" action="{{ route('admin.kasus.restore', $kasus) }}">
                                            @csrf
                                            <button type="submit" class="font-medium text-brand-600 hover:text-brand-700">Pulihkan</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <x-icon name="fact_check" class="h-7 w-7" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-700">Tidak Ada Kasus Terhapus</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">
                {{ $kasusList->links('pagination.tailadmin') }}
            </div>
        </div>
    </div>
</x-app-layout>

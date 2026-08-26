<x-app-layout>
    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Mapping Default Fase (Kurikulum Merdeka)</h1>
                <p class="text-xs text-gray-500">Daftar rekomendasi fase untuk otomatisasi saran saat membuat Kelas baru.</p>
            </div>
            @can('fase-mapping.create')
                <a href="{{ route('admin.fase-mapping.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                    <x-icon name="plus" class="h-4 w-4" />
                    Tambah Mapping
                </a>
            @endcan
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Scope</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Bentuk Pendidikan</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Tingkat</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Fase Rekomendasi</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($mappingList as $m)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm">
                                @if ($m->lembaga_id === null)
                                    <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">Platform Default</span>
                                @else
                                    <span class="inline-flex rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">{{ $m->lembaga->nama ?? 'Lembaga #' . $m->lembaga_id }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm font-semibold text-gray-900">{{ $m->bentuk_pendidikan }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-600">{{ $m->tingkat ?? 'Semua Tingkat' }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-900">
                                <span class="font-semibold">{{ $m->fase->nama ?? '-' }}</span>
                                <span class="text-xs text-gray-400">({{ $m->fase->kode ?? '-' }})</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-right text-sm">
                                @php
                                    $canManage = $isPlatformOrYayasan || ($m->lembaga_id !== null && $m->lembaga_id === auth()->user()->lembaga_id);
                                @endphp
                                @if ($canManage)
                                    <div class="inline-flex items-center gap-2">
                                        @can('fase-mapping.edit')
                                            <a href="{{ route('admin.fase-mapping.edit', $m) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Edit</a>
                                        @endcan
                                        @can('fase-mapping.delete')
                                            <form method="POST" action="{{ route('admin.fase-mapping.destroy', $m) }}" onsubmit="return confirm('Hapus mapping ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs font-semibold text-error-600 hover:text-error-700">Hapus</button>
                                            </form>
                                        @endcan
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">Read-only (Platform)</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada mapping default fase yang dikonfigurasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

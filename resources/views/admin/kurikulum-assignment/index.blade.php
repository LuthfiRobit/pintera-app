<x-app-layout>
    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ session('error') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Kurikulum</h1>
                <p class="text-xs text-gray-500">Kurikulum yang berlaku per jenjang, tingkat, dan tahun ajaran. Kelas baru mengikuti ini otomatis saat dibuat.</p>
            </div>
            <div class="flex items-center gap-2">
                @can('kurikulum-assignment.view')
                    <a href="{{ route('admin.kurikulum-assignment.resync') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Cek & Perbaiki Kurikulum/Fase
                    </a>
                @endcan
                @can('kurikulum-assignment.create')
                    <a href="{{ route('admin.kurikulum-assignment.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                        <x-icon name="plus" class="h-4 w-4" />
                        Tambah Assignment
                    </a>
                @endcan
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Scope</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Tahun Ajaran</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Bentuk Pendidikan</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Tingkat</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Kurikulum</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($assignmentList as $a)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm">
                                @if ($a->lembaga_id === null)
                                    <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">Platform Default</span>
                                @else
                                    <span class="inline-flex rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">{{ $a->lembaga->nama ?? 'Lembaga #' . $a->lembaga_id }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-600">{{ $a->tahunAjaran->nama ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm font-semibold text-gray-900">{{ $a->bentuk_pendidikan }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-600">{{ $a->tingkat ?? 'Semua Tingkat' }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-900">{{ $a->kurikulum->label() }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-right text-sm">
                                @php
                                    $canManage = $isPlatformOrYayasan || ($a->lembaga_id !== null && $a->lembaga_id === auth()->user()->lembaga_id);
                                @endphp
                                @if ($canManage)
                                    <div class="inline-flex items-center gap-2">
                                        @can('kurikulum-assignment.edit')
                                            <a href="{{ route('admin.kurikulum-assignment.edit', $a) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Edit</a>
                                        @endcan
                                        @can('kurikulum-assignment.delete')
                                            <form method="POST" action="{{ route('admin.kurikulum-assignment.destroy', $a) }}" onsubmit="return confirm('Hapus assignment ini?')">
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
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada assignment kurikulum yang dikonfigurasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

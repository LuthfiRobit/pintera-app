<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Aturan Kurikulum</p>
        <span class="text-xs text-gray-500">{{ $assignmentList->count() }} aturan</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50/75 font-display text-xs font-bold uppercase tracking-wider text-gray-500">
                    <th class="sticky left-0 z-10 bg-gray-50/75 px-5 py-3 w-32">Aksi</th>
                    <th class="px-4 py-3">Scope</th>
                    <th class="px-4 py-3">Tahun Ajaran</th>
                    <th class="px-4 py-3">Bentuk Pendidikan</th>
                    <th class="px-4 py-3">Tingkat</th>
                    <th class="px-5 py-3">Kurikulum</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-normal">
                @forelse ($assignmentList as $a)
                    <tr class="transition-colors hover:bg-gray-50/60">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            @if ($a->canManage)
                                <x-table-actions>
                                    @can('kurikulum-assignment.edit')
                                        <x-dropdown-link href="{{ route('admin.kurikulum-assignment.edit', $a) }}">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                Edit Aturan
                                            </span>
                                        </x-dropdown-link>
                                    @endcan
                                    @can('kurikulum-assignment.delete')
                                        <form
                                            method="POST"
                                            action="{{ route('admin.kurikulum-assignment.destroy', $a) }}"
                                            x-data
                                            @submit.prevent="confirmDialog(
                                                'Hapus Aturan Kurikulum?',
                                                @js('Hapus assignment '.$a->bentuk_pendidikan.($a->tingkat ? ' tingkat '.$a->tingkat : ' (semua tingkat)').' untuk '.($a->tahunAjaran->nama ?? 'tahun ajaran ini').'?'.($a->lembaga_id === null ? ' PERINGATAN: ini assignment GLOBAL (Platform Default) — dipakai sebagai cadangan oleh lembaga mana pun yang belum punya assignment sendiri untuk kombinasi ini, dan TIDAK ADA pengecekan otomatis sebelum dihapus.' : '')),
                                                { confirmLabel: 'Ya, Hapus', isDanger: true }
                                            ).then(confirmed => { if (confirmed) $el.submit() })"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-error-600 transition duration-150 ease-in-out hover:bg-error-50 focus:bg-error-50 focus:outline-none">
                                                <x-icon name="delete" class="h-4 w-4" />
                                                Hapus Aturan
                                            </button>
                                        </form>
                                    @endcan
                                </x-table-actions>
                            @else
                                <x-tooltip text="Dikelola oleh Platform Admin. Buat aturan baru untuk menimpa ini khusus lembaga Anda.">
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                                        <x-icon name="lock" class="h-3.5 w-3.5" />
                                        Read-only
                                    </span>
                                </x-tooltip>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3.5">
                            @if ($a->lembaga_id === null)
                                <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">Standar Platform</span>
                            @else
                                <span class="inline-flex rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">{{ $a->lembaga->nama ?? 'Lembaga #'.$a->lembaga_id }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3.5 text-gray-600">{{ $a->tahunAjaran->nama ?? '-' }}</td>
                        <td class="whitespace-nowrap px-4 py-3.5 font-semibold text-gray-900">{{ $a->bentuk_pendidikan }}</td>
                        <td class="whitespace-nowrap px-4 py-3.5 text-gray-600">
                            @if ($a->tingkat)
                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">Tingkat {{ $a->tingkat }}</span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                                    <x-icon name="bolt" class="h-3 w-3" />
                                    Semua Tingkat (Default Jenjang)
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-gray-900">{{ $a->kurikulum->label() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-500">
                            <p class="text-sm">Belum ada aturan kurikulum yang dikonfigurasi.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

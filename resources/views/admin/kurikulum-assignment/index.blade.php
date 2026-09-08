<x-app-layout>
    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ session('error') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Kurikulum</h1>
                    @if ($isYayasan ?? false)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                            <x-icon name="apartment" class="h-3.5 w-3.5" />
                            {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500">Kurikulum yang berlaku per jenjang, tingkat, dan tahun ajaran. Kelas baru mengikuti ini otomatis saat dibuat.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('kurikulum-assignment.view')
                    <x-link-button href="{{ route('admin.kurikulum-assignment.resync') }}" variant="ghost">
                        Cek & Perbaiki Kurikulum/Fase
                    </x-link-button>
                @endcan
                @can('kurikulum-assignment.create')
                    <x-link-button href="{{ route('admin.kurikulum-assignment.create') }}">
                        <x-icon name="plus" class="h-4 w-4" />
                        Tambah Assignment
                    </x-link-button>
                @endcan
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Assignment Kurikulum</p>
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
                                                        Edit Assignment
                                                    </span>
                                                </x-dropdown-link>
                                            @endcan
                                            @can('kurikulum-assignment.delete')
                                                <form method="POST" action="{{ route('admin.kurikulum-assignment.destroy', $a) }}" onsubmit="return confirm('Hapus assignment ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-error-600 transition duration-150 ease-in-out hover:bg-error-50 focus:bg-error-50 focus:outline-none">
                                                        <x-icon name="delete" class="h-4 w-4" />
                                                        Hapus Assignment
                                                    </button>
                                                </form>
                                            @endcan
                                        </x-table-actions>
                                    @else
                                        <span class="text-xs text-gray-400">Read-only (Platform)</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5">
                                    @if ($a->lembaga_id === null)
                                        <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">Platform Default</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">{{ $a->lembaga->nama ?? 'Lembaga #'.$a->lembaga_id }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-gray-600">{{ $a->tahunAjaran->nama ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 font-semibold text-gray-900">{{ $a->bentuk_pendidikan }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-gray-600">{{ $a->tingkat ?? 'Semua Tingkat' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-gray-900">{{ $a->kurikulum->label() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-gray-500">
                                    <p class="text-sm">Belum ada assignment kurikulum yang dikonfigurasi.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

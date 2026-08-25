<div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-2.5">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Role</p>
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ $roles->total() }} Data</span>
    </div>

    <div class="flex items-center gap-2">
        <label for="per_page" class="text-xs font-medium text-gray-500">Tampilkan:</label>
        <select id="per_page" x-model="perPage" @change="muatUlangDaftar()" class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
            <option value="10">10 / hal</option>
            <option value="20">20 / hal</option>
            <option value="25">25 / hal</option>
            <option value="50">50 / hal</option>
        </select>
    </div>
</div>

<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                <th class="sticky left-0 z-10 bg-white px-5 py-3 w-32">Aksi</th>
                <th class="px-5 py-3">Nama Role</th>
                <th class="px-5 py-3">Scope Level</th>
                <th class="px-5 py-3">Users</th>
                <th class="px-5 py-3">Permissions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($roles as $role)
                <tr class="transition hover:bg-gray-50/50">
                    <td class="sticky left-0 z-10 bg-white px-5 py-3 align-top">
                            <x-table-actions>
                                <a href="{{ route('admin.roles.edit', $role) }}" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                    <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                    Edit Role
                                </a>
                                @if (!$role->is_protected)
                                    <form
                                        method="POST"
                                        action="{{ route('admin.roles.destroy', $role) }}"
                                        x-data
                                        @submit.prevent="confirmDialog('Hapus Role?', @js('Yakin ingin menghapus role ' . $role->name . '?'), { confirmLabel: 'Ya, Hapus', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-red-600 transition duration-150 ease-in-out hover:bg-red-50 focus:bg-red-50 focus:outline-none">
                                            <x-icon name="delete" class="h-4 w-4 text-red-500" />
                                            Hapus Role
                                        </button>
                                    </form>
                                @endif
                            </x-table-actions>
                    </td>
                    <td class="px-5 py-3 align-top">
                        <p class="font-medium text-gray-900">{{ ucwords(str_replace('_', ' ', $role->name)) }}</p>
                        @if ($role->is_protected)
                            <span class="mt-1 inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-700 ring-1 ring-brand-600/20">Protected</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 align-top">
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">{{ ucfirst($role->scope_level) }}</span>
                    </td>
                    <td class="px-5 py-3 align-top font-mono text-gray-600">
                        <a href="{{ route('admin.users.index', ['role' => $role->name]) }}" class="text-brand-600 hover:underline">{{ $role->users_count }}</a>
                    </td>
                    <td class="px-5 py-3 align-top font-mono text-gray-600">
                        @php
                            $previewNames = $role->permissions->pluck('name')->implode(', ');
                            $sisa = max(0, $role->permissions_count - $role->permissions->count());
                            $tooltipText = $role->permissions_count > 0
                                ? $previewNames . ($sisa > 0 ? ", +{$sisa} lainnya" : '')
                                : 'Belum ada permission';
                        @endphp
                        <x-tooltip :text="$tooltipText">
                            <span class="cursor-help border-b border-dashed border-gray-300">{{ $role->permissions_count }}</span>
                        </x-tooltip>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-8 text-center text-gray-500">Tidak ada role yang ditemukan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($roles->hasPages())
    <div class="border-t border-gray-100 px-5 py-4">
        {{ $roles->links('pagination.tailadmin') }}
    </div>
@endif

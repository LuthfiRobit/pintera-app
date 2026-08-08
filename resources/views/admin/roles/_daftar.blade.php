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
                        <div class="flex items-center gap-3">
                            <a href="{{ route('admin.roles.edit', $role) }}" class="font-medium text-gray-700 hover:text-brand-600">Edit</a>
                            @if (!$role->is_protected)
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Hapus role ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:text-red-800">
                                        Hapus
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                    <td class="px-5 py-3 align-top">
                        <p class="font-medium text-gray-900">{{ $role->name }}</p>
                        @if ($role->is_protected)
                            <span class="mt-1 inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-700 ring-1 ring-brand-600/20">Protected</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 align-top">
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">{{ ucfirst($role->scope_level) }}</span>
                    </td>
                    <td class="px-5 py-3 align-top font-mono text-gray-600">{{ $role->users_count }}</td>
                    <td class="px-5 py-3 align-top font-mono text-gray-600">{{ $role->permissions_count }}</td>
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

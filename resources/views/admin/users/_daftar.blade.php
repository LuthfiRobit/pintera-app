<div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-2.5">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Akun Staff</p>
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ $users->total() }} Data</span>
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
                <th class="px-5 py-3">Nama</th>
                <th class="px-5 py-3">Email</th>
                <th class="px-5 py-3">Role</th>
                @if ($isPlatformViewer)
                    <th class="px-5 py-3">Yayasan</th>
                @endif
                <th class="px-5 py-3">Lembaga</th>
                <th class="px-5 py-3">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($users as $user)
                <tr class="transition hover:bg-gray-50/50">
                    <td class="sticky left-0 z-10 bg-white px-5 py-3 align-top">
                            <x-table-actions>
                                @if ($user->hasRole('siswa'))
                                    @if ($user->siswa)
                                        <a href="{{ route('admin.siswa.edit', $user->siswa) }}" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                            <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                            Edit di Data Siswa
                                        </a>
                                    @endif
                                @elseif ($user->hasRole('orang_tua'))
                                    @if ($user->orangTua)
                                        <a href="{{ route('admin.orang-tua.edit', $user->orangTua) }}" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                            <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                            Edit di Modul Orang Tua
                                        </a>
                                    @endif
                                @else
                                    <a href="{{ route('admin.users.edit', $user) }}" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                        Edit Akun
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.users.toggle-active', $user) }}"
                                        x-data
                                        @submit.prevent="confirmDialog('Ubah Status Akun?', @js('Ubah status akun menjadi ' . ($user->is_active ? 'Nonaktif' : 'Aktif') . '?'), { confirmLabel: 'Ya, Ubah', isDanger: {{ $user->is_active ? 'true' : 'false' }} }).then(confirmed => { if (confirmed) $el.submit() })"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 {{ $user->is_active ? 'text-red-600 hover:bg-red-50 focus:bg-red-50' : 'text-green-600 hover:bg-green-50 focus:bg-green-50' }} transition duration-150 ease-in-out focus:outline-none">
                                            <x-icon name="{{ $user->is_active ? 'block' : 'check_circle' }}" class="h-4 w-4 {{ $user->is_active ? 'text-red-500' : 'text-green-500' }}" />
                                            {{ $user->is_active ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                                        </button>
                                    </form>
                                @endif
                            </x-table-actions>
                    </td>
                    <td class="px-5 py-3 align-top font-medium text-gray-900">{{ $user->name }}</td>
                    <td class="px-5 py-3 align-top text-gray-500">{{ $user->email }}</td>
                    <td class="px-5 py-3 align-top text-gray-500">{{ $user->functionalRoles()->pluck('name')->map(fn($name) => ucwords(str_replace('_', ' ', $name)))->implode(', ') ?: '—' }}</td>
                    @if ($isPlatformViewer)
                        <td class="px-5 py-3 align-top text-gray-500">{{ $user->yayasan?->nama ?? $user->lembaga?->yayasan?->nama ?? '—' }}</td>
                    @endif
                    <td class="px-5 py-3 align-top text-gray-500">{{ $user->lembaga?->nama ?? '—' }}</td>
                    <td class="px-5 py-3 align-top">
                        @if ($user->is_active)
                            <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-semibold text-green-700 ring-1 ring-inset ring-green-600/20">Aktif</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 ring-1 ring-inset ring-gray-500/10">Nonaktif</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $isPlatformViewer ? 7 : 6 }}" class="px-5 py-8 text-center text-gray-500">Tidak ada akun yang ditemukan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($users->hasPages())
    <div class="border-t border-gray-100 px-5 py-4">
        {{ $users->links('pagination.tailadmin') }}
    </div>
@endif

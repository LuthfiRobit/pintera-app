<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-semibold text-xl text-ink leading-tight">Role Builder</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 p-4 bg-signal-green/10 text-signal-green rounded">{{ session('status') }}</div>
        @endif

        <a href="{{ route('admin.roles.create') }}" class="inline-block mb-4 px-4 py-2 bg-ink text-white rounded">
            Buat Role Baru
        </a>

        <table class="w-full bg-white shadow rounded">
            <thead>
                <tr class="text-left border-b border-slate/20">
                    <th class="p-3 text-ink">Nama</th>
                    <th class="p-3 text-ink">Scope</th>
                    <th class="p-3 text-ink">Protected</th>
                    <th class="p-3 text-ink">Jumlah User</th>
                    <th class="p-3 text-ink">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr class="border-b border-slate/20">
                        <td class="p-3 text-ink">{{ $role->name }}</td>
                        <td class="p-3">
                            @if ($role->scope_level === 'yayasan')
                                <span class="px-2 py-0.5 rounded text-xs bg-brass/10 text-brass">{{ $role->scope_level }}</span>
                            @else
                                <span class="text-slate">{{ $role->scope_level }}</span>
                            @endif
                        </td>
                        <td class="p-3 text-slate">{{ $role->is_protected ? 'Ya' : 'Tidak' }}</td>
                        <td class="p-3 text-slate">{{ $role->users_count }}</td>
                        <td class="p-3 space-x-2">
                            <a href="{{ route('admin.roles.edit', $role) }}" class="text-ink underline">Edit</a>
                            @unless ($role->is_protected)
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-signal-red">Hapus</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @error('role')
            <p class="mt-4 text-signal-red">{{ $message }}</p>
        @enderror
    </div>
</x-app-layout>

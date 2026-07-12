<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-semibold text-xl text-ink leading-tight">Manajemen Akun Staff</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 p-4 bg-signal-green/10 text-signal-green rounded">{{ session('status') }}</div>
        @endif

        <a href="{{ route('admin.users.create') }}" class="inline-block mb-4 px-4 py-2 bg-ink text-white rounded">
            Buat Akun Staff
        </a>

        <table class="w-full bg-white shadow rounded">
            <thead>
                <tr class="text-left border-b border-slate/20">
                    <th class="p-3 text-ink">Nama</th>
                    <th class="p-3 text-ink">Email</th>
                    <th class="p-3 text-ink">Role</th>
                    <th class="p-3 text-ink">Lembaga</th>
                    <th class="p-3 text-ink">Status</th>
                    <th class="p-3 text-ink">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr class="border-b border-slate/20">
                        <td class="p-3 text-ink">{{ $user->name }}</td>
                        <td class="p-3 text-slate">{{ $user->email }}</td>
                        <td class="p-3 text-slate">{{ $user->roles->pluck('name')->implode(', ') }}</td>
                        <td class="p-3 text-slate">{{ $user->lembaga?->nama ?? '-' }}</td>
                        <td class="p-3">
                            @if ($user->is_active)
                                <span class="px-2 py-0.5 rounded text-xs bg-signal-green/10 text-signal-green">Aktif</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-xs bg-slate/10 text-slate">Nonaktif</span>
                            @endif
                        </td>
                        <td class="p-3 space-x-2">
                            <a href="{{ route('admin.users.edit', $user) }}" class="text-ink underline">Edit</a>
                            <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-brass">
                                    {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>

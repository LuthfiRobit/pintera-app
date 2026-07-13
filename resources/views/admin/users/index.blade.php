<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Akses &amp; Peran</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Manajemen Akun Staff</h2>
            </div>
            <x-link-button href="{{ route('admin.users.create') }}">
                <span class="text-base leading-none">+</span> Buat Akun Staff
            </x-link-button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif

        <x-panel>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                        <th class="px-5 py-3 font-display font-semibold">Nama</th>
                        <th class="px-5 py-3 font-display font-semibold">Email</th>
                        <th class="px-5 py-3 font-display font-semibold">Role</th>
                        <th class="px-5 py-3 font-display font-semibold">Lembaga</th>
                        <th class="px-5 py-3 font-display font-semibold">Status</th>
                        <th class="px-5 py-3 font-display font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/10">
                    @foreach ($users as $user)
                        <tr class="transition hover:bg-paper/50">
                            <td class="px-5 py-3.5 font-medium text-ink">{{ $user->name }}</td>
                            <td class="px-5 py-3.5 text-slate">{{ $user->email }}</td>
                            <td class="px-5 py-3.5 text-slate">{{ $user->roles->pluck('name')->implode(', ') }}</td>
                            <td class="px-5 py-3.5 text-slate">{{ $user->lembaga?->nama ?? '—' }}</td>
                            <td class="px-5 py-3.5">
                                @if ($user->is_active)
                                    <x-badge tone="green">Aktif</x-badge>
                                @else
                                    <x-badge tone="slate">Nonaktif</x-badge>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="font-medium text-ink hover:text-brass">Edit</a>
                                    <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="font-medium text-brass hover:text-brass/80">
                                            {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-panel>
    </div>
</x-app-layout>

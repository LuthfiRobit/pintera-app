<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Akses &amp; Peran</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Role Builder</h2>
            </div>
            <x-link-button href="{{ route('admin.roles.create') }}">
                <span class="text-base leading-none">+</span> Buat Role Baru
            </x-link-button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif
        @error('role')
            <div class="rounded-xl bg-signal-red/10 p-4 text-sm text-signal-red">{{ $message }}</div>
        @enderror

        <x-panel>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                        <th class="px-5 py-3 font-display font-semibold">Nama</th>
                        <th class="px-5 py-3 font-display font-semibold">Scope</th>
                        <th class="px-5 py-3 font-display font-semibold">Protected</th>
                        <th class="px-5 py-3 font-display font-semibold">Jumlah User</th>
                        <th class="px-5 py-3 font-display font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/10">
                    @foreach ($roles as $role)
                        <tr class="transition hover:bg-paper/50">
                            <td class="px-5 py-3.5 font-medium text-ink">{{ $role->name }}</td>
                            <td class="px-5 py-3.5">
                                @if ($role->scope_level === 'yayasan')
                                    <x-badge tone="brass">{{ $role->scope_level }}</x-badge>
                                @else
                                    <span class="text-slate">{{ $role->scope_level }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-slate">{{ $role->is_protected ? 'Ya' : 'Tidak' }}</td>
                            <td class="px-5 py-3.5 font-mono text-slate">{{ $role->users_count }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="font-medium text-ink hover:text-brass">Edit</a>
                                    @unless ($role->is_protected)
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-medium text-signal-red hover:text-signal-red/80">Hapus</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-panel>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-semibold text-xl text-ink leading-tight">Edit Role: {{ $role->name }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="bg-white shadow rounded p-6 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block font-medium text-ink">Nama Role</label>
                <input type="text" name="name" value="{{ old('name', $role->name) }}" class="w-full border border-slate/30 rounded p-2">
                @error('name') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">Scope Level</label>
                @if ($role->is_protected)
                    <p class="p-2 bg-brass/10 text-brass rounded">{{ $role->scope_level }} (terkunci, role ini dilindungi)</p>
                @else
                    <select name="scope_level" class="w-full border border-slate/30 rounded p-2">
                        <option value="yayasan" @selected(old('scope_level', $role->scope_level) === 'yayasan')>Yayasan</option>
                        <option value="lembaga" @selected(old('scope_level', $role->scope_level) === 'lembaga')>Lembaga</option>
                        <option value="diri_sendiri" @selected(old('scope_level', $role->scope_level) === 'diri_sendiri')>Diri Sendiri</option>
                    </select>
                @endif
                @error('scope_level') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">Permission</label>
                @foreach ($permissions as $permission)
                    <label class="block text-slate">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                            @checked($role->hasPermissionTo($permission))>
                        {{ $permission->name }}
                    </label>
                @endforeach
            </div>

            <button type="submit" class="px-4 py-2 bg-ink text-white rounded">Simpan</button>
        </form>
    </div>
</x-app-layout>

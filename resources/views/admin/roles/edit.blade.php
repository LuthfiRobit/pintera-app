<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Akses &amp; Peran</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Edit Role: {{ $role->name }}</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-5 p-6">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label value="Nama Role" />
                    <x-text-input type="text" name="name" value="{{ old('name', $role->name) }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Scope Level" />
                    @if ($role->is_protected)
                        <p class="mt-1.5 rounded-xl bg-brass/10 p-2.5 text-sm text-brass">{{ $role->scope_level }} (terkunci, role ini dilindungi)</p>
                    @else
                        <select name="scope_level" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            <option value="yayasan" @selected(old('scope_level', $role->scope_level) === 'yayasan')>Yayasan</option>
                            <option value="lembaga" @selected(old('scope_level', $role->scope_level) === 'lembaga')>Lembaga</option>
                            <option value="diri_sendiri" @selected(old('scope_level', $role->scope_level) === 'diri_sendiri')>Diri Sendiri</option>
                        </select>
                    @endif
                    <x-input-error :messages="$errors->get('scope_level')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Permission" />
                    <div class="mt-2 space-y-1.5 rounded-xl border border-ink/10 bg-paper/50 p-3">
                        @foreach ($permissions as $permission)
                            <label class="flex items-center gap-2 text-sm text-slate">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" class="rounded border-ink/25 text-brass focus:ring-brass" @checked($role->hasPermissionTo($permission))>
                                {{ $permission->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>Simpan</x-primary-button>
                    <a href="{{ route('admin.roles.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </form>
        </x-panel>
    </div>
</x-app-layout>

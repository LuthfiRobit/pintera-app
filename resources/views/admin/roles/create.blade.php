<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Akses &amp; Peran</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Buat Role Baru</h2>
    </x-slot>

    <div
        class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,320px)_1fr]"
        x-data="roleForm({
            catalogUrl: @js(route('admin.roles.permissions-catalog')),
            submitUrl: @js(route('admin.roles.store')),
            method: 'POST',
            indexUrl: @js(route('admin.roles.index')),
            initialModuleGroups: @js($moduleGroups),
            initialCheckedIds: @js([]),
            initialName: @js(old('name', '')),
            initialScopeLevel: @js(old('scope_level', 'lembaga')),
            initialIsProtected: false,
        })"
    >
        <x-panel>
            <div class="space-y-5 p-6">
                <div>
                    <x-input-label value="Nama Role" />
                    <x-text-input type="text" x-model="name" class="mt-1.5" />
                    <p x-show="errors.name" class="mt-1.5 text-sm text-signal-red" x-text="errors.name && errors.name[0]"></p>
                </div>

                <div>
                    <x-input-label value="Scope Level" />
                    <select x-model="scopeLevel" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="yayasan">Yayasan</option>
                        <option value="lembaga">Lembaga</option>
                        <option value="diri_sendiri">Diri Sendiri</option>
                    </select>
                    <p x-show="errors.scope_level" class="mt-1.5 text-sm text-signal-red" x-text="errors.scope_level && errors.scope_level[0]"></p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="button"
                        @click="submit()"
                        :disabled="submitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-ink px-4 py-2.5 text-sm font-bold text-paper shadow-sm transition hover:bg-ink/90 active:scale-[0.98] disabled:opacity-60"
                    >
                        <span x-text="submitting ? 'Menyimpan...' : 'Simpan'"></span>
                    </button>
                    <a href="{{ route('admin.roles.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </div>
        </x-panel>

        @include('admin.roles._permission-matrix')
    </div>
</x-app-layout>

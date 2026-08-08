<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-500">Akses &amp; Peran</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-gray-900">Edit Role: {{ $role->name }}</h2>
    </x-slot>

    <div
        class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[minmax(0,340px)_1fr] items-start"
        x-data="roleForm({
            catalogUrl: @js(route('admin.roles.permissions-catalog')),
            submitUrl: @js(route('admin.roles.update', $role)),
            method: 'PUT',
            indexUrl: @js(route('admin.roles.index')),
            initialModuleGroups: @js($moduleGroups),
            initialCheckedIds: @js($checkedIds),
            initialName: @js(old('name', $role->name)),
            initialScopeLevel: @js(old('scope_level', $role->scope_level)),
            initialIsProtected: @js($role->is_protected),
        })"
    >
        {{-- Sticky Sidebar Form --}}
        <div class="sticky top-6 flex flex-col gap-5">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card transition hover:shadow-elevated">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <x-icon name="badge" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900">Identitas Peran</h3>
                        <p class="text-xs text-gray-500">Detail dasar wewenang</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <div>
                        <x-input-label value="Nama Role" />
                        <x-text-input type="text" x-model="name" class="mt-1.5" placeholder="Contoh: Admin Akademik" />
                        <p x-show="errors.name" class="mt-1.5 text-sm font-medium text-error-600" x-text="errors.name && errors.name[0]"></p>
                    </div>

                    <div>
                        <x-input-label value="Scope Level" />
                        <template x-if="isProtected">
                            <div class="mt-1.5 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm font-medium text-amber-700">
                                <x-icon name="lock" class="h-4 w-4" />
                                <span x-text="scopeLevel + ' (Terkunci)'"></span>
                            </div>
                        </template>
                        <template x-if="!isProtected">
                            <x-select x-model="scopeLevel" class="mt-1.5">
                                <option value="yayasan">Yayasan</option>
                                <option value="lembaga">Lembaga</option>
                                <option value="diri_sendiri">Diri Sendiri</option>
                            </x-select>
                        </template>
                        <p x-show="errors.scope_level" class="mt-1.5 text-sm font-medium text-error-600" x-text="errors.scope_level && errors.scope_level[0]"></p>
                    </div>

                    <div class="flex flex-col gap-3 pt-4">
                        <button
                            type="button"
                            @click="submit()"
                            :disabled="submitting"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700 active:scale-[0.98] disabled:opacity-60"
                        >
                            <x-icon name="save" class="h-4 w-4" x-show="!submitting" />
                            <span x-text="submitting ? 'Menyimpan...' : 'Simpan Perubahan'"></span>
                        </button>
                        <a href="{{ route('admin.roles.index') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-[0.98]">
                            Batal
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Permission Matrix Area --}}
        @include('admin.roles._permission-matrix')
    </div>
</x-app-layout>

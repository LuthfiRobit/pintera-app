<x-app-layout>
    {{-- Top Navigation & Breadcrumbs --}}
    <div class="mx-auto max-w-6xl mb-6 flex flex-wrap items-center justify-between gap-3 px-4 sm:px-0">
        <div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.roles.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Akses & Peran</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-900">Buat Role Baru</b>
            </p>
        </div>
        <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
            <x-icon name="arrow_back" class="h-4 w-4 text-gray-500" />
            <span>Kembali ke Daftar</span>
        </a>
    </div>

    <div
        class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[minmax(0,340px)_1fr] items-start"
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
        {{-- Sticky Sidebar Form --}}
        <div class="sticky top-6 flex flex-col gap-5">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card transition hover:shadow-elevated">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <x-icon name="add_circle" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900">Pembuatan Peran</h3>
                        <p class="text-xs text-gray-500">Tentukan nama dan cakupan peran baru.</p>
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
                        <x-select x-model="scopeLevel" class="mt-1.5">
                            @if ($isPlatformActor)
                                <option value="platform">Platform</option>
                            @endif
                            <option value="yayasan">Yayasan</option>
                            <option value="lembaga">Lembaga</option>
                            <option value="diri_sendiri">Diri Sendiri</option>
                        </x-select>
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
                            <span x-text="submitting ? 'Menyimpan...' : 'Buat Role Baru'"></span>
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

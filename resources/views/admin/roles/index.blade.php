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

    <div
        class="mx-auto max-w-6xl space-y-6"
        x-data="rolesTable({
            dataUrl: @js(route('admin.roles.data')),
            editUrlTemplate: @js(route('admin.roles.edit', ['role' => '__ID__'])),
            deleteUrlTemplate: @js(route('admin.roles.destroy', ['role' => '__ID__'])),
        })"
    >
        <x-panel>
            <div class="flex flex-wrap items-center gap-3 border-b border-ink/10 p-4">
                <input
                    type="search"
                    x-model="search"
                    @input="onSearchInput()"
                    placeholder="Cari nama role..."
                    class="w-full max-w-xs rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                >
                <select
                    x-model="scope"
                    @change="onScopeChange()"
                    class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                >
                    <option value="">Semua Scope</option>
                    <option value="yayasan">Yayasan</option>
                    <option value="lembaga">Lembaga</option>
                    <option value="diri_sendiri">Diri Sendiri</option>
                </select>
                <button
                    type="button"
                    @click="fetchData()"
                    class="ml-auto inline-flex items-center gap-2 rounded-xl border border-ink/15 px-3 py-2 text-sm font-medium text-ink hover:bg-paper"
                >
                    <span x-show="loading" class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-ink/30 border-t-ink"></span>
                    Refresh
                </button>
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                        <th class="px-5 py-3 font-display font-semibold">No</th>
                        <th class="px-5 py-3 font-display font-semibold">
                            <button type="button" @click="sortBy('name')" class="hover:text-ink">Nama Role &amp; Scope</button>
                        </th>
                        <th class="px-5 py-3 font-display font-semibold">
                            <button type="button" @click="sortBy('permissions_count')" class="hover:text-ink">Permission</button>
                        </th>
                        <th class="px-5 py-3 font-display font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/10">
                    <template x-for="(row, index) in rows" :key="row.id">
                        <tr class="transition hover:bg-paper/50">
                            <td class="px-5 py-3.5 font-mono text-slate" x-text="(meta.current_page - 1) * meta.per_page + index + 1"></td>
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-ink" x-text="row.name"></p>
                                <span
                                    class="mt-1 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold"
                                    :class="row.scope_level === 'yayasan' ? 'bg-brass/10 text-brass' : 'bg-slate/10 text-slate'"
                                    x-text="row.scope_level"
                                ></span>
                                <span x-show="row.is_protected" class="ml-1.5 inline-flex items-center rounded-full bg-brass/10 px-2.5 py-0.5 text-xs font-bold text-brass">Protected</span>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-slate" x-text="row.permissions_count"></td>
                            <td class="px-5 py-3.5">
                                <div class="relative" x-data="{ menuOpen: false }" @click.outside="menuOpen = false">
                                    <button
                                        type="button"
                                        @click="menuOpen = !menuOpen"
                                        class="rounded-lg p-1.5 text-slate hover:bg-paper hover:text-ink"
                                        aria-label="Aksi"
                                    >
                                        <span class="text-lg leading-none">&#9881;</span>
                                    </button>
                                    <div
                                        x-show="menuOpen"
                                        x-transition
                                        class="absolute right-0 z-10 mt-1 w-40 rounded-xl border border-ink/10 bg-white py-1 shadow-elevated"
                                        style="display: none;"
                                    >
                                        <a :href="editUrl(row)" class="block px-4 py-2 text-sm text-ink hover:bg-paper">Edit Role</a>
                                        <template x-if="!row.is_protected">
                                            <button
                                                type="button"
                                                @click="menuOpen = false; deleteRole(row)"
                                                class="block w-full px-4 py-2 text-left text-sm text-signal-red hover:bg-signal-red/5"
                                            >
                                                Hapus
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loading && rows.length === 0">
                        <td colspan="4" class="px-5 py-10 text-center text-slate">Tidak ada role yang cocok.</td>
                    </tr>
                </tbody>
            </table>

            <div class="flex items-center justify-between border-t border-ink/10 p-4 text-sm text-slate">
                <p>Halaman <span x-text="meta.current_page"></span> dari <span x-text="meta.last_page"></span> &middot; <span x-text="meta.total"></span> role</p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Sebelumnya</button>
                    <button type="button" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Berikutnya</button>
                </div>
            </div>
        </x-panel>
    </div>
</x-app-layout>

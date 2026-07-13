<x-panel>
    <div class="space-y-4 p-6">
        <div class="flex items-center justify-between">
            <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate">Hak Akses (Permissions)</h3>
            <div class="flex items-center gap-3 text-sm">
                <button type="button" @click="selectAll()" class="font-medium text-ink hover:text-brass">Pilih Semua</button>
                <button type="button" @click="clearAll()" class="font-medium text-slate hover:text-ink">Kosongkan</button>
                <button
                    type="button"
                    @click="syncPermissions()"
                    :disabled="syncing"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-ink/15 px-2.5 py-1.5 font-medium text-ink hover:bg-paper disabled:opacity-60"
                >
                    <span x-text="syncing ? 'Menyegarkan...' : 'Sync Permission'"></span>
                </button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <template x-for="group in moduleGroups" :key="group.module">
                <div class="rounded-xl border border-ink/10 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="font-display text-sm font-semibold text-ink" x-text="'Modul: ' + group.label"></p>
                        <label class="flex items-center gap-1.5 text-xs text-slate">
                            <input type="checkbox" :checked="allCheckedInModule(group)" @change="toggleModule(group)" class="rounded border-ink/25 text-brass focus:ring-brass">
                            Semua
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        <template x-for="permission in group.permissions" :key="permission.id">
                            <label class="flex items-center gap-2 text-sm text-slate">
                                <input type="checkbox" :checked="isChecked(permission.id)" @change="toggle(permission.id)" class="rounded border-ink/25 text-brass focus:ring-brass">
                                <span x-text="permission.label"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-panel>

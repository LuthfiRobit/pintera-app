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

        <div x-show="showAuditBanner" x-cloak class="rounded-xl border border-signal-amber/40 bg-signal-amber/10 p-4 text-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-2">
                    <template x-if="auditMissingFromDatabase.length > 0">
                        <p>
                            <span class="font-semibold text-ink" x-text="auditMissingFromDatabase.length"></span>
                            permission baru ditemukan di kode, belum ada di database — sudah otomatis ditambahkan ke daftar checkbox di bawah:
                            <span class="font-mono text-xs" x-text="auditMissingFromDatabase.join(', ')" data-testid="auditMissingFromDatabase"></span>
                        </p>
                    </template>
                    <template x-if="auditUnusedInCode.length > 0">
                        <p>
                            <span class="font-semibold text-ink" x-text="auditUnusedInCode.length"></span>
                            permission di database tidak dipakai di kode manapun:
                            <span class="font-mono text-xs" x-text="auditUnusedInCode.join(', ')" data-testid="auditUnusedInCode"></span>
                        </p>
                    </template>
                </div>
                <button type="button" @click="dismissAuditBanner()" class="text-xs font-semibold text-slate hover:text-ink">Tutup</button>
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

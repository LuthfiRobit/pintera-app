<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="space-y-4 p-6">
        <div class="flex flex-col gap-3 border-b border-gray-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="font-display text-lg font-bold text-gray-900">Matriks Hak Akses</h3>
                <p class="text-xs text-gray-500">Pilih izin (permissions) yang diizinkan untuk peran ini.</p>
            </div>
            <div class="flex items-center gap-2">
                <!-- Button Switcher: Pilih Semua / Kosongkan -->
                <button
                    type="button"
                    @click="isAllChecked() ? clearAll() : selectAll()"
                    :class="isAllChecked() ? 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900' : 'border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100'"
                    class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-bold transition"
                >
                    <x-icon name="cancel" class="h-3.5 w-3.5" x-show="isAllChecked()" />
                    <x-icon name="check_circle" class="h-3.5 w-3.5" x-show="!isAllChecked()" />
                    <span x-text="isAllChecked() ? 'Kosongkan' : 'Pilih Semua'"></span>
                </button>
                <!-- Button Sync -->
                <button
                    type="button"
                    @click="syncPermissions()"
                    :disabled="syncing"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-bold text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 disabled:opacity-60"
                >
                    <x-icon name="sync" class="h-3.5 w-3.5" x-bind:class="syncing ? 'animate-spin' : ''" />
                    <span x-text="syncing ? 'Menyegarkan...' : 'Sync Permission'"></span>
                </button>
            </div>
        </div>

        <!-- Row 2: Search Input -->
        <div class="border-b border-gray-100 pb-4">
            <div class="relative w-full sm:w-80">
                <input type="text" x-model="permissionSearch" placeholder="Cari permission... (mis. tagihan, rapor)" class="w-full rounded-lg border-gray-200 bg-gray-50 py-1.5 pl-8 pr-3 text-xs focus:border-brand-500 focus:ring-brand-500">
                <x-icon name="search" class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
            </div>
        </div>

        <div x-show="showAuditBanner" x-cloak class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 shrink-0">
                    <x-icon name="warning" class="h-5 w-5 text-amber-600" />
                </div>
                <div class="flex-1 space-y-2">
                    <template x-if="auditMissingFromDatabase.length > 0">
                        <p>
                            <span class="font-bold" x-text="auditMissingFromDatabase.length"></span>
                            hak akses baru ditemukan di kode sumber dan belum tercatat di sistem — telah otomatis ditambahkan:
                            <span class="font-mono text-xs rounded bg-amber-100/50 px-1.5 py-0.5 font-semibold text-amber-900 ml-1" x-text="auditMissingFromDatabase.join(', ')" data-testid="auditMissingFromDatabase"></span>
                        </p>
                    </template>
                    <template x-if="auditUnusedInCode.length > 0">
                        <p>
                            <span class="font-bold" x-text="auditUnusedInCode.length"></span>
                            hak akses tercatat di sistem tapi terdeteksi tidak dipakai:
                            <span class="font-mono text-xs rounded bg-amber-100/50 px-1.5 py-0.5 font-semibold text-amber-900 ml-1" x-text="auditUnusedInCode.join(', ')" data-testid="auditUnusedInCode"></span>
                        </p>
                    </template>
                </div>
                <button type="button" @click="dismissAuditBanner()" class="shrink-0 text-amber-600 hover:text-amber-800 transition">
                    <x-icon name="close" class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 pt-2">
            <template x-for="group in filteredModuleGroups()" :key="group.module">
                <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 transition hover:border-brand-200 hover:shadow-elevated">
                    <div class="mb-4 flex items-center justify-between border-b border-gray-200/60 pb-3">
                        <p class="font-display text-sm font-bold text-gray-900" x-text="'Modul: ' + group.label"></p>
                        <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-gray-500 hover:text-gray-900 transition">
                            <input type="checkbox" :checked="allCheckedInModule(group)" @change="toggleModule(group)" class="h-4 w-4 rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500 transition">
                            Semua
                        </label>
                    </div>
                    <div class="flex flex-col gap-3">
                        <template x-for="permission in group.permissions" :key="permission.id">
                            <label class="group flex cursor-pointer items-start gap-3 text-sm text-gray-600 hover:text-gray-900 transition">
                                <div class="flex h-5 items-center">
                                    <input type="checkbox" :checked="isChecked(permission.id)" @change="toggle(permission.id)" class="h-4 w-4 rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500 transition">
                                </div>
                                <span class="leading-5" x-text="permission.label"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

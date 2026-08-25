<div x-data="{
    selectedRoles: @js(old('roles', $targetUser?->functionalRoles()->pluck('name')->toArray() ?? [])),
    needsLembaga() {
        const yayasanAndPlatform = ['platform_super_admin', 'yayasan_super_admin', 'bendahara_yayasan'];
        return this.selectedRoles.length > 0 && this.selectedRoles.some(role => !yayasanAndPlatform.includes(role));
    }
}" class="grid grid-cols-1 gap-8 lg:grid-cols-12">

    <!-- Left Column: Credentials & Profile (lg:col-span-5) -->
    <div class="space-y-6 lg:col-span-5">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-5">
            <h4 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
                <x-icon name="badge" class="h-5 w-5 text-brand-500" />
                Informasi Utama & Kredensial
            </h4>

            <div class="space-y-4">
                <div>
                    <x-input-label for="name" value="Nama Lengkap" />
                    <x-text-input type="text" id="name" name="name" value="{{ old('name', $targetUser?->name) }}" class="mt-1.5" placeholder="Masukkan nama pengguna" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="email" value="Email Akses" />
                    <x-text-input type="email" id="email" name="email" value="{{ old('email', $targetUser?->email) }}" class="mt-1.5" placeholder="Alamat email untuk login" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                </div>

                @if(!$targetUser)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="password" value="Password" />
                            <x-text-input type="password" id="password" name="password" class="mt-1.5" placeholder="Minimal 8 karakter" required />
                            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                        </div>
                        <div>
                            <x-input-label for="password_confirmation" value="Konfirmasi Password" />
                            <x-text-input type="password" id="password_confirmation" name="password_confirmation" class="mt-1.5" placeholder="Ulangi password" required />
                        </div>
                    </div>

                    @if (isset($lembaga) && $lembaga->isNotEmpty())
                        <div x-show="needsLembaga()" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2" class="mt-4 pt-4 border-t border-gray-100">
                            <label for="lembaga_id" class="block text-sm font-semibold text-gray-700">
                                Lembaga <span class="text-red-500" x-show="needsLembaga()">*</span>
                            </label>
                            <x-select id="lembaga_id" name="lembaga_id" class="mt-1.5" ::required="needsLembaga()">
                                <option value="" disabled selected>Pilih lembaga (opsional)</option>
                                @foreach ($lembaga as $item)
                                    <option value="{{ $item->id }}" @selected(old('lembaga_id') == $item->id)>{{ $item->nama }}</option>
                                @endforeach
                            </x-select>
                            <x-input-error :messages="$errors->get('lembaga_id')" class="mt-1.5" />
                            <x-input-hint>Pilih lembaga jika pengguna ini terikat pada sekolah/lembaga tertentu.</x-input-hint>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <!-- Right Column: Access & Roles (lg:col-span-7) -->
    <div class="lg:col-span-7">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-5">
            <h4 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
                <x-icon name="security" class="h-5 w-5 text-brand-500" />
                Hak Akses & Peran (Role)
            </h4>
            <p class="text-xs text-gray-500">Pilih satu atau lebih peran fungsional. Role teknis (scope pegawai) ditambahkan otomatis oleh sistem.</p>

            <div class="space-y-6">
                @foreach ($rolesByGroup as $groupLabel => $groupRoles)
                    <div>
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400 flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand-400"></span>
                            {{ $groupLabel }}
                        </p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach ($groupRoles as $roleOption)
                                <label 
                                    :class="selectedRoles.includes('{{ $roleOption->name }}') 
                                        ? 'border-brand-500 bg-brand-50/20 ring-1 ring-brand-500/10' 
                                        : 'border-gray-200 bg-white hover:bg-gray-50/50'"
                                    class="flex items-center gap-3 rounded-xl border p-4 text-sm font-medium text-gray-700 transition duration-150 cursor-pointer shadow-sm hover:shadow-md"
                                >
                                    <input
                                        type="checkbox"
                                        name="roles[]"
                                        value="{{ $roleOption->name }}"
                                        x-model="selectedRoles"
                                        class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                    >
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-gray-950 font-semibold leading-tight">{{ ucwords(str_replace('_', ' ', $roleOption->name)) }}</span>
                                        <span class="text-[10px] text-gray-400 font-normal">Scope: {{ $roleOption->scope_level }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pt-2">
                <x-input-error :messages="$errors->get('roles')" class="mt-1.5" />
                <x-input-error :messages="$errors->get('roles.*')" class="mt-1.5" />
            </div>
        </div>
    </div>

</div>

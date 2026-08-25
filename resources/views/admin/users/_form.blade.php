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
        <div>
            <x-input-label for="lembaga_id" value="Lembaga" />
            <x-select id="lembaga_id" name="lembaga_id" class="mt-1.5">
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

<div>
    <x-input-label value="Role / Peran Akses" />
    <p class="mt-0.5 text-xs text-gray-500">Pilih satu atau lebih peran fungsional. Role teknis (scope pegawai) ditambahkan otomatis oleh sistem.</p>
    @php
        $checkedRoles = old('roles', $targetUser?->functionalRoles()->pluck('name')->toArray() ?? []);
    @endphp
    <div class="mt-2 space-y-4">
        @foreach ($rolesByGroup as $groupLabel => $groupRoles)
            <div>
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $groupLabel }}</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($groupRoles as $roleOption)
                        <label class="flex items-center gap-2.5 rounded-lg border border-gray-200 px-3.5 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="{{ $roleOption->name }}"
                                @checked(in_array($roleOption->name, $checkedRoles, true))
                                class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                            >
                            <span>{{ $roleOption->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    <x-input-error :messages="$errors->get('roles')" class="mt-1.5" />
    <x-input-error :messages="$errors->get('roles.*')" class="mt-1.5" />
</div>

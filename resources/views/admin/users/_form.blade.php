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
    <x-input-label for="role" value="Role / Peran Akses" />
    <x-select id="role" name="role" class="mt-1.5" required>
        <option value="" disabled {{ !$targetUser ? 'selected' : '' }}>Pilih peran akses...</option>
        @foreach ($roles as $roleOption)
            <option value="{{ $roleOption->name }}" @selected(old('role', $targetUser?->roles->first()->name ?? null) === $roleOption->name)>
                {{ $roleOption->name }}
            </option>
        @endforeach
    </x-select>
    <x-input-error :messages="$errors->get('role')" class="mt-1.5" />
</div>

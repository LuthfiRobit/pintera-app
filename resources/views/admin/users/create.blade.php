<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Akses &amp; Peran</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Buat Akun Staff</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5 p-6">
                @csrf

                <div>
                    <x-input-label value="Nama" />
                    <x-text-input type="text" name="name" value="{{ old('name') }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Email" />
                    <x-text-input type="email" name="email" value="{{ old('email') }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Password" />
                        <x-text-input type="password" name="password" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Konfirmasi Password" />
                        <x-text-input type="password" name="password_confirmation" class="mt-1.5" />
                    </div>
                </div>

                @if ($lembaga->isNotEmpty())
                    <div>
                        <x-input-label value="Lembaga" />
                        <select name="lembaga_id" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            @foreach ($lembaga as $item)
                                <option value="{{ $item->id }}">{{ $item->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('lembaga_id')" class="mt-1.5" />
                    </div>
                @endif

                <div>
                    <x-input-label value="Role" />
                    <select name="role" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @foreach ($roles as $roleOption)
                            <option value="{{ $roleOption->name }}">{{ $roleOption->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('role')" class="mt-1.5" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>Simpan</x-primary-button>
                    <a href="{{ route('admin.users.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </form>
        </x-panel>
    </div>
</x-app-layout>

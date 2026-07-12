<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-semibold text-xl text-ink leading-tight">Buat Akun Staff</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('admin.users.store') }}" class="bg-white shadow rounded p-6 space-y-4">
            @csrf

            <div>
                <label class="block font-medium text-ink">Nama</label>
                <input type="text" name="name" value="{{ old('name') }}" class="w-full border border-slate/30 rounded p-2">
                @error('name') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="w-full border border-slate/30 rounded p-2">
                @error('email') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">Password</label>
                <input type="password" name="password" class="w-full border border-slate/30 rounded p-2">
                @error('password') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">Konfirmasi Password</label>
                <input type="password" name="password_confirmation" class="w-full border border-slate/30 rounded p-2">
            </div>

            @if ($lembaga->isNotEmpty())
                <div>
                    <label class="block font-medium text-ink">Lembaga</label>
                    <select name="lembaga_id" class="w-full border border-slate/30 rounded p-2">
                        @foreach ($lembaga as $item)
                            <option value="{{ $item->id }}">{{ $item->nama }}</option>
                        @endforeach
                    </select>
                    @error('lembaga_id') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="block font-medium text-ink">Role</label>
                <select name="role" class="w-full border border-slate/30 rounded p-2">
                    @foreach ($roles as $roleOption)
                        <option value="{{ $roleOption->name }}">{{ $roleOption->name }}</option>
                    @endforeach
                </select>
                @error('role') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="px-4 py-2 bg-ink text-white rounded">Simpan</button>
        </form>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-semibold text-xl text-ink leading-tight">Edit Akun: {{ $targetUser->name }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('admin.users.update', $targetUser) }}" class="bg-white shadow rounded p-6 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block font-medium text-ink">Nama</label>
                <input type="text" name="name" value="{{ old('name', $targetUser->name) }}" class="w-full border border-slate/30 rounded p-2">
                @error('name') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">Email</label>
                <input type="email" name="email" value="{{ old('email', $targetUser->email) }}" class="w-full border border-slate/30 rounded p-2">
                @error('email') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">Role</label>
                <select name="role" class="w-full border border-slate/30 rounded p-2">
                    @foreach ($roles as $roleOption)
                        <option value="{{ $roleOption->name }}" @selected($targetUser->hasRole($roleOption->name))>
                            {{ $roleOption->name }}
                        </option>
                    @endforeach
                </select>
                @error('role') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="px-4 py-2 bg-ink text-white rounded">Simpan</button>
        </form>
    </div>
</x-app-layout>

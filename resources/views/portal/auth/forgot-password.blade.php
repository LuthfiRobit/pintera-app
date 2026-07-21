<x-layouts.portal-auth
    title="Lupa Kata Sandi"
    link-label="Ingat kata sandimu?"
    link-text="Masuk"
    link-route="portal.login"
>
    <div class="mx-auto flex min-h-[460px] max-w-7xl items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
        <div class="w-full max-w-[420px] rounded-[20px] border border-gray-200 bg-white p-8 shadow-[0_20px_44px_rgba(30,58,95,0.10)]">
            <div class="mb-6 text-center">
                <h1 class="text-[21px] font-bold text-gray-900">Lupa Kata Sandi</h1>
                <p class="mt-1.5 text-[12.5px] text-gray-500">Masukkan emailmu, kami kirimkan tautan untuk mengatur ulang kata sandi.</p>
            </div>

            <form method="POST" action="{{ route('portal.password.email') }}">
                @csrf
                <div class="mb-[22px]">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Alamat Email</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="mail" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" required autofocus
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                    @error('email') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-portal-500 py-[13px] text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Kirim Tautan Reset
                </button>
            </form>
        </div>
    </div>
</x-layouts.portal-auth>

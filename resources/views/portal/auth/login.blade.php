<x-layouts.portal-auth
    title="Masuk"
    link-label="Belum punya akun?"
    link-text="Daftar Akun"
    link-route="spmb.welcome"
>
    <div class="mx-auto flex min-h-[460px] max-w-7xl items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
        <div class="w-full max-w-[420px] rounded-[20px] border border-gray-200 bg-white p-8 shadow-[0_20px_44px_rgba(30,58,95,0.10)]">
            <div class="mb-6 text-center">
                <h1 class="text-[21px] font-bold text-gray-900">Masuk ke Akunmu</h1>
                <p class="mt-1.5 text-[12.5px] text-gray-500">Pantau status pendaftaran, dokumen, dan tagihanmu di sini.</p>
            </div>

            <form method="POST" action="{{ route('portal.login') }}">
                @csrf
                <div class="mb-4">
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

                <div class="mb-4">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kata Sandi</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="lock" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="password" name="password" placeholder="Kata sandi" required
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                </div>

                <div class="-mt-0.5 mb-[22px] flex flex-wrap items-center justify-between gap-2 text-[12.5px]">
                    <label class="flex items-center gap-2 text-gray-500">
                        <input type="checkbox" name="remember" class="h-4 w-4 rounded-[5px] border-gray-300 text-portal-500 focus:ring-portal-500/20">
                        Ingat saya
                    </label>
                    <a href="{{ route('portal.password.request') }}" class="font-semibold text-portal-500">Lupa kata sandi?</a>
                </div>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-portal-500 py-[13px] text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Masuk
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </button>
            </form>

            <p class="mt-[18px] text-center text-[12.5px] text-gray-500">Belum punya akun? <a href="{{ route('spmb.welcome') }}" class="font-bold text-portal-500">Daftar di sini</a></p>
        </div>
    </div>
</x-layouts.portal-auth>

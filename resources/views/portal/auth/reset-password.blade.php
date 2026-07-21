<x-layouts.portal-auth
    title="Atur Ulang Kata Sandi"
    link-label="Ingat kata sandimu?"
    link-text="Masuk"
    link-route="portal.login"
>
    <div class="mx-auto flex min-h-[460px] max-w-7xl items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
        <div class="w-full max-w-[420px] rounded-[20px] border border-gray-200 bg-white p-8 shadow-[0_20px_44px_rgba(30,58,95,0.10)]">
            <div class="mb-6 text-center">
                <h1 class="text-[21px] font-bold text-gray-900">Kata Sandi Baru</h1>
                <p class="mt-1.5 text-[12.5px] text-gray-500">Buat kata sandi baru untuk akunmu.</p>
            </div>

            <form method="POST" action="{{ route('portal.password.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div class="mb-4">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Alamat Email</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="mail" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                    @error('email') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4" x-data="passwordStrength()">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kata Sandi Baru</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="lock" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="password" name="password" placeholder="Minimal 8 karakter" required
                            x-model="value"
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                    <div class="mt-2 flex gap-1" x-show="value.length > 0" x-cloak>
                        <template x-for="n in 4" :key="n">
                            <i class="h-1 flex-1 rounded-full" :class="{
                                'bg-success-500': tier === 'strong',
                                'bg-warning-500': tier === 'mid' && n <= 2,
                                'bg-gray-200': tier === 'weak' || (tier === 'mid' && n > 2),
                            }"></i>
                        </template>
                    </div>
                    <p class="mt-1.5 text-[11px] font-semibold" x-show="value.length > 0" x-cloak
                        x-text="tier === 'strong' ? 'Kuat — sudah memenuhi huruf besar, kecil, dan angka' : (tier === 'mid' ? 'Sedang — tambahkan huruf besar/kecil dan angka' : 'Lemah — minimal 8 karakter')"
                        :class="tier === 'strong' ? 'text-success-700' : (tier === 'mid' ? 'text-warning-700' : 'text-gray-400')"></p>
                    @error('password') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>

                <div class="mb-[22px]">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Konfirmasi Kata Sandi Baru</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="lock" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="password" name="password_confirmation" placeholder="Ulangi kata sandi baru" required
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                </div>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-portal-500 py-[13px] text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Simpan Kata Sandi Baru
                </button>
            </form>
        </div>
    </div>
</x-layouts.portal-auth>

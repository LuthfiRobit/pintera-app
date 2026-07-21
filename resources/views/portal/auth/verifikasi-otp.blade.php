<x-layouts.portal-auth
    title="Verifikasi Email"
    link-label="Salah data?"
    link-text="Kembali ke form daftar"
    link-route="portal.register"
>
    <div class="mx-auto flex min-h-[460px] max-w-7xl items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
        <div
            class="w-full max-w-[420px] rounded-[20px] border border-gray-200 bg-white p-8 shadow-[0_20px_44px_rgba(30,58,95,0.10)]"
            x-data="{ sisa: {{ $detikTersisa }} }"
            x-init="if (sisa > 0) { const t = setInterval(() => { sisa--; if (sisa <= 0) clearInterval(t); }, 1000); }"
        >
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-portal-50 text-portal-500">
                <x-icon name="mail" class="h-6 w-6" />
            </div>
            <div class="mb-6 text-center">
                <h1 class="text-[21px] font-bold text-gray-900">Verifikasi Email Kamu</h1>
                <p class="mt-1.5 text-[12.5px] text-gray-500">Kami mengirim kode 6 digit ke <span class="font-bold text-gray-900">{{ $email }}</span></p>
            </div>

            <form
                method="POST"
                action="{{ route('portal.verifikasi-otp.store') }}"
                x-data="otpInput()"
                @submit="$refs.kodeTersembunyi.value = kode"
            >
                @csrf
                <div class="mb-2 flex justify-center gap-2 max-[380px]:gap-1.5">
                    @for ($i = 0; $i < 6; $i++)
                        <input
                            type="text"
                            inputmode="numeric"
                            maxlength="1"
                            x-ref="kotak{{ $i }}"
                            :value="digit[{{ $i }}]"
                            @input="isiKotak({{ $i }}, $event)"
                            @keydown="tekanBackspace({{ $i }}, $event)"
                            @paste.prevent="tempel($event)"
                            class="h-[54px] w-[46px] rounded-[11px] border-[1.5px] border-gray-200 text-center text-[20px] font-bold tabular-nums text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20 max-[380px]:h-[48px] max-[380px]:w-[38px] max-[380px]:text-[17px]"
                            @if ($i === 0) autofocus @endif
                        >
                    @endfor
                </div>
                <input type="hidden" name="kode_otp" x-ref="kodeTersembunyi">
                @error('kode_otp') <p class="mb-2 text-center text-[11px] text-error-700">{{ $message }}</p> @enderror

                <p class="mb-[22px] mt-1 text-center text-[12.5px] text-gray-400">
                    <span x-show="sisa > 0" x-cloak>Belum menerima kode? Kirim ulang dalam <b class="text-gray-600 tabular-nums" x-text="String(Math.floor(sisa / 60)).padStart(2, '0') + ':' + String(sisa % 60).padStart(2, '0')"></b></span>
                    <span x-show="sisa <= 0" x-cloak>
                        Belum menerima kode?
                        <button type="submit" form="form-kirim-ulang" class="font-bold text-portal-500 underline">Kirim ulang kode</button>
                    </span>
                </p>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-portal-500 py-[13px] text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Verifikasi &amp; Masuk
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </button>
            </form>

            <form id="form-kirim-ulang" method="POST" action="{{ route('portal.verifikasi-otp.kirim-ulang') }}">
                @csrf
            </form>

            <p class="mt-[18px] text-center text-[12.5px] text-gray-500">Salah alamat email? <a href="{{ route('portal.register') }}" class="font-bold text-portal-500">Ubah di sini</a></p>
        </div>
    </div>
</x-layouts.portal-auth>

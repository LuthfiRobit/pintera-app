<x-layouts.portal-auth
    title="Daftar Akun"
    link-label="Sudah punya akun?"
    link-text="Masuk"
    link-route="portal.login"
>
    <div class="mx-auto grid max-w-7xl gap-7 px-4 py-8 sm:px-6 min-[861px]:grid-cols-[1.15fr_0.85fr] min-[861px]:items-start lg:px-10 lg:py-12">
        <div class="mx-auto w-full max-w-[480px] rounded-[20px] border border-gray-200 bg-white p-8 shadow-[0_20px_44px_rgba(30,58,95,0.10)]">
            <div class="mb-[22px] text-center">
                <h1 class="text-[21px] font-bold text-gray-900">Buat Akun Pendaftar</h1>
                <p class="mt-1.5 text-[12.5px] text-gray-500">Satu akun untuk mendaftar dan memantau seluruh proses seleksi.</p>
            </div>

            @if ($lembaga && $jalurTerpilih)
                <div class="mb-[22px] flex items-center gap-2.5 rounded-xl bg-portal-50 px-3.5 py-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-portal-500 text-white">
                        <x-icon name="school" class="h-4 w-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[10px] font-bold uppercase tracking-wide text-portal-500/75">Mendaftar untuk</span>
                        <span class="block truncate text-[12.5px] font-bold text-portal-500">{{ $lembaga->nama }} — Jalur {{ $jalurTerpilih->nama }}</span>
                    </span>
                    <a href="{{ route('spmb.index', ['lembagaSlug' => $lembaga->slug]) }}" class="shrink-0 text-[11.5px] font-bold text-portal-500 underline">Ganti</a>
                </div>
            @endif

            <form method="POST" action="{{ route('portal.register') }}">
                @csrf
                <div class="mb-4">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Nama Lengkap</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="person" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="text" name="nama" value="{{ old('nama') }}" placeholder="Sesuai nama di KTP/KK orang tua" required autofocus
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                    @error('nama') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4 grid grid-cols-2 gap-3.5 max-[480px]:grid-cols-1">
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Alamat Email</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                                <x-icon name="mail" class="h-[15px] w-[15px]" />
                            </span>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" required
                                class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        </div>
                        @error('email') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">No. HP/WhatsApp</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                                <x-icon name="phone" class="h-[15px] w-[15px]" />
                            </span>
                            <input type="text" name="no_hp_wa" value="{{ old('no_hp_wa') }}" placeholder="08xx-xxxx-xxxx" required
                                class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        </div>
                        @error('no_hp_wa') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mb-4" x-data="passwordStrength()">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kata Sandi</label>
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

                <div class="mb-4">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Konfirmasi Kata Sandi</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="lock" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="password" name="password_confirmation" placeholder="Ulangi kata sandi" required
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                </div>

                <div class="mb-[22px] mt-[18px] flex items-start gap-[9px] text-[12px] leading-[1.5] text-gray-500">
                    <input type="checkbox" name="terms" value="1" required class="mt-[3px] h-[17px] w-[17px] shrink-0 rounded-[5px] border-gray-300 text-portal-500 focus:ring-portal-500/20">
                    <span>Saya menyetujui <a href="#" class="font-semibold text-portal-500">Syarat &amp; Ketentuan</a> serta <a href="#" class="font-semibold text-portal-500">Kebijakan Privasi</a> Pintera.</span>
                </div>
                @error('terms') <p class="-mt-4 mb-4 text-[11px] text-error-700">{{ $message }}</p> @enderror

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-portal-500 py-[13px] text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Buat Akun
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </button>
            </form>

            <p class="mt-[18px] text-center text-[12.5px] text-gray-500">Sudah punya akun? <a href="{{ route('portal.login') }}" class="font-bold text-portal-500">Masuk di sini</a></p>
        </div>

        @if ($lembaga && $jalurTerpilih && $jalurLain->isNotEmpty())
            <aside class="mx-auto w-full max-w-[480px] min-[861px]:sticky min-[861px]:top-[90px] min-[861px]:max-w-[380px]">
                <div class="mb-3.5">
                    <p class="mb-1 text-[11px] font-bold uppercase tracking-wide text-portal-500">{{ $lembaga->nama }}</p>
                    <h3 class="text-[15.5px] font-bold text-gray-900">Jalur Lain yang Tersedia</h3>
                    <p class="mt-1 text-[12px] text-gray-500">Belum yakin dengan Jalur {{ $jalurTerpilih->nama }}? Bandingkan dulu dengan jalur lain di lembaga ini.</p>
                </div>

                @foreach ($jalurLain as $item)
                    @php $jalur = $item['jalur']; $nominal = $item['nominal']; @endphp
                    <div class="mb-3 rounded-[14px] border-[1.5px] p-4 {{ $item['selected'] ? 'border-portal-500 bg-portal-50' : 'border-gray-200 bg-white' }}">
                        <div class="mb-1.5 flex items-center justify-between">
                            <h4 class="text-[14px] font-bold text-gray-900">{{ $jalur->nama }}</h4>
                            @if ($item['selected'])
                                <span class="flex items-center gap-1 text-[10.5px] font-bold text-portal-500">
                                    <x-icon name="check_circle" class="h-3 w-3" /> Dipilih
                                </span>
                            @endif
                        </div>
                        @if ($jalur->deskripsi)
                            <p class="mb-3 text-[11.5px] leading-[1.5] text-gray-500">{{ $jalur->deskripsi }}</p>
                        @endif
                        <div class="flex items-center justify-between">
                            @if ($nominal === null)
                                <span class="text-[10.5px] font-semibold text-warning-700">Menunggu Konfirmasi</span>
                            @elseif ((float) $nominal->nominal === 0.0)
                                <span class="text-[12.5px] font-bold text-success-700">Gratis</span>
                            @else
                                <span class="text-[12.5px] font-bold text-portal-500">Rp{{ number_format($nominal->nominal, 0, ',', '.') }}</span>
                            @endif
                            @unless ($item['selected'])
                                <form method="POST" action="{{ route('spmb.register.ganti-jalur', ['jalur' => $jalur->id]) }}">
                                    @csrf
                                    <button type="submit" class="text-[11.5px] font-bold text-portal-500 underline">Pilih Jalur Ini</button>
                                </form>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </aside>
        @endif
    </div>
</x-layouts.portal-auth>

<section>
    <header>
        <h2 class="font-display text-lg font-semibold text-ink">
            {{ __('Preferensi Notifikasi') }}
        </h2>

        <p class="mt-1 text-sm text-slate">
            {{ __('Atur channel notifikasi Keuangan yang ingin Anda terima. Notifikasi mendesak tetap dikirim lewat semua channel apa pun pengaturan ini.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.notification-preference.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('patch')

        <label class="flex items-center gap-3">
            <input type="checkbox" name="channel_wa" value="1" @checked(old('channel_wa', $preference->channel_wa ?? true)) class="rounded border-slate-300 text-ink focus:ring-ink">
            <span class="text-sm text-ink">{{ __('WhatsApp') }}</span>
        </label>

        <label class="flex items-center gap-3">
            <input type="checkbox" name="channel_email" value="1" @checked(old('channel_email', $preference->channel_email ?? true)) class="rounded border-slate-300 text-ink focus:ring-ink">
            <span class="text-sm text-ink">{{ __('Email') }}</span>
        </label>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Simpan Preferensi') }}</x-primary-button>

            @if (session('status') === 'notification-preference-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-slate"
                >{{ __('Tersimpan.') }}</p>
            @endif
        </div>
    </form>
</section>

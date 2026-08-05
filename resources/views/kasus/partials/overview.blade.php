<div class="space-y-4">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-3">
        <div class="flex items-center gap-2">
            <x-badge tone="{{ $kasus->status->badgeTone() }}">{{ $kasus->status->label() }}</x-badge>
        </div>
        <div>
            <p class="text-xs font-semibold text-gray-500">Kategori Masalah</p>
            <p class="mt-1 text-sm text-gray-900">{{ $kasus->kategori_masalah }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold text-gray-500">Deskripsi</p>
            <p class="mt-1 text-sm text-gray-900">{{ $kasus->deskripsi }}</p>
        </div>
        @if ($kasus->konselorGuru || $kasus->konselorKaryawan)
            <div>
                <p class="text-xs font-semibold text-gray-500">Konselor Penanganan</p>
                <p class="mt-1 text-sm text-gray-900">{{ $kasus->konselorGuru?->nama ?? $kasus->konselorKaryawan?->nama }}</p>
            </div>
        @endif
    </div>

    @if ($isKontakUtama && $kasus->consents->isNotEmpty())
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-3">
            <p class="font-display text-sm font-bold text-gray-900">Persetujuan (Consent)</p>
            @foreach ($kasus->consents as $consent)
                <div class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">
                            {{ $consent->jenis === 'sesi_pendampingan' ? 'Sesi Pendampingan' : 'Pengumpulan Media (Foto/Video)' }}
                        </p>
                        <x-badge tone="{{ $consent->status === 'disetujui' ? 'green' : 'amber' }}" class="mt-1 text-xs">
                            {{ $consent->status === 'disetujui' ? 'Disetujui' : 'Menunggu Persetujuan' }}
                        </x-badge>
                    </div>
                    @if ($consent->status !== 'disetujui')
                        <form method="POST" action="{{ route('kasus.consent.approve', [$kasus, $consent]) }}">
                            @csrf
                            @method('PATCH')
                            <x-primary-button type="submit">Setujui</x-primary-button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

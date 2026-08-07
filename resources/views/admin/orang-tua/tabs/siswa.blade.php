<div x-show="activeTab === 'siswa'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <div class="space-y-6">
        <div class="flex items-center justify-between rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Anak / Siswa Tertaut</h2>
                <p class="text-sm text-gray-500">Daftar siswa yang terhubung dengan orang tua ini.</p>
            </div>
        </div>

        @if ($orangTua->siswa->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50 py-12 text-center">
                <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-200 text-gray-500">
                    <x-icon name="groups" class="h-6 w-6" />
                </div>
                <h3 class="text-sm font-semibold text-gray-900">Belum Ada Anak Tertaut</h3>
                <p class="mt-1 max-w-sm text-xs text-gray-500">Orang tua ini belum dihubungkan dengan profil siswa mana pun. Penautan bisa dilakukan dari halaman Edit Siswa.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($orangTua->siswa as $siswa)
                    <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600 font-bold">
                                    {{ strtoupper(substr($siswa->nama_lengkap, 0, 2)) }}
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">{{ $siswa->nama_lengkap }}</h4>
                                    <p class="text-xs text-gray-500 font-mono">NISN: {{ $siswa->nisn ?: '-' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3">
                            <x-badge tone="slate" class="text-xs flex items-center gap-1">
                                <x-icon name="family_restroom" class="h-3 w-3" />
                                {{ ucfirst($siswa->pivot->hubungan) }}
                            </x-badge>
                            @if ($siswa->pivot->is_kontak_utama)
                                <x-badge tone="green" class="text-xs flex items-center gap-1">
                                    <x-icon name="check_circle" class="h-3 w-3" />
                                    Kontak Utama
                                </x-badge>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

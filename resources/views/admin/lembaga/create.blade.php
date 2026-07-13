<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Data Induk</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Tambah Lembaga</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.lembaga.store') }}" class="space-y-5 p-6">
                @csrf

                <div>
                    <x-input-label value="Yayasan" />
                    <select name="yayasan_id" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @foreach ($yayasanList as $yayasan)
                            <option value="{{ $yayasan->id }}">{{ $yayasan->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label value="NPSN" />
                    <x-text-input type="text" name="npsn" value="{{ old('npsn') }}" class="mt-1.5 font-mono" />
                    <x-input-error :messages="$errors->get('npsn')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Nama Lembaga" />
                    <x-text-input type="text" name="nama" value="{{ old('nama') }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Bentuk Pendidikan" />
                        <select name="bentuk_pendidikan" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            @foreach (['KB','TPA','SPS','TK','SD','SMP','SMA','SMK','SLB'] as $bentuk)
                                <option value="{{ $bentuk }}">{{ $bentuk }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Status Sekolah" />
                        <select name="status_sekolah" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            <option value="negeri">Negeri</option>
                            <option value="swasta">Swasta</option>
                        </select>
                    </div>
                </div>

                <div>
                    <x-input-label value="Naungan" />
                    <select name="naungan" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="kemendikdasmen">Kemendikdasmen</option>
                        <option value="kemenag">Kemenag</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Telepon" />
                        <x-text-input type="text" name="telepon" value="{{ old('telepon') }}" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Email" />
                        <x-text-input type="email" name="email" value="{{ old('email') }}" class="mt-1.5" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>Simpan</x-primary-button>
                    <a href="{{ route('admin.lembaga.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </form>
        </x-panel>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Data Induk</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Tambah Data Guru</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.guru.store') }}" class="space-y-5 p-6">
                @csrf

                <div>
                    <x-input-label value="Akun User (harus sudah punya role guru)" />
                    <select name="user_id" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @foreach ($eligibleUsers as $eligibleUser)
                            <option value="{{ $eligibleUser->id }}">{{ $eligibleUser->name }} ({{ $eligibleUser->email }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('user_id')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="NIK" />
                    <x-text-input type="text" name="nik" value="{{ old('nik') }}" class="mt-1.5 font-mono" />
                    <x-input-error :messages="$errors->get('nik')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Nama" />
                    <x-text-input type="text" name="nama" value="{{ old('nama') }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Jenis Kelamin" />
                        <select name="jenis_kelamin" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Jenis PTK" />
                        <select name="jenis_ptk" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            <option value="guru_kelas">Guru Kelas</option>
                            <option value="guru_mapel">Guru Mapel</option>
                            <option value="kepala_sekolah">Kepala Sekolah</option>
                            <option value="tenaga_administrasi">Tenaga Administrasi</option>
                        </select>
                    </div>
                </div>

                <div>
                    <x-input-label value="Status Kepegawaian" />
                    <select name="status_kepegawaian" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="PNS">PNS</option>
                        <option value="PPPK">PPPK</option>
                        <option value="GTY">GTY</option>
                        <option value="PTY">PTY</option>
                        <option value="Honorer">Honorer</option>
                    </select>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>Simpan</x-primary-button>
                    <a href="{{ route('admin.guru.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </form>
        </x-panel>
    </div>
</x-app-layout>

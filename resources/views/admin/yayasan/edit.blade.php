@php use Illuminate\Support\Facades\Storage; @endphp
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6" x-data="{ mode: {{ $errors->any() ? "'edit'" : "'view'" }}, logoPreview: null }">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm font-medium text-success-700 shadow-sm">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm font-medium text-error-700 shadow-sm">
                Terdapat kesalahan pengisian pada formulir, silakan periksa kembali isian di bawah.
            </div>
        @endif

        @if ($yayasan === null)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
                Belum ada data yayasan pada sistem ini. Hubungi developer untuk inisialisasi data.
            </div>
        @else
            {{-- Hero Card --}}
            <div class="relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card md:p-8">
                <div class="relative flex flex-col gap-6 md:flex-row md:items-center justify-between">
                    <div class="flex items-center gap-6">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-600 via-teal-700 to-emerald-800 text-white shadow-md overflow-hidden">
                            @if ($yayasan->logo)
                                <img src="{{ Storage::disk('public')->url($yayasan->logo) }}" alt="Logo Yayasan" class="h-full w-full object-cover">
                            @else
                                <x-icon name="apartment" class="h-10 w-10" />
                            @endif
                        </div>
                        <div>
                            <h1 class="font-display text-2xl font-bold tracking-tight text-gray-900">{{ $yayasan->nama }}</h1>
                            <p class="mt-1 text-sm text-gray-500">{{ $yayasan->lembaga->count() }} lembaga di bawah naungan</p>
                        </div>
                    </div>
                    <button type="button" @click="mode = (mode === 'view' ? 'edit' : 'view')" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
                        <x-icon name="edit" class="h-4 w-4 text-brand-600" x-show="mode === 'view'" />
                        <x-icon name="visibility" class="h-4 w-4 text-indigo-600" x-show="mode === 'edit'" style="display: none;" />
                        <span x-text="mode === 'view' ? 'Mode Edit Profil' : 'Mode Lihat Profil'">Mode Edit Profil</span>
                    </button>
                </div>
            </div>

            {{-- READ-ONLY VIEW MODE --}}
            <div x-show="mode === 'view'" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                    <h3 class="mb-4 font-display font-bold text-gray-900">Identitas Yayasan</h3>
                    <dl class="space-y-4">
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">NPWP</dt><dd class="mt-1 font-mono text-gray-900">{{ $yayasan->npwp_yayasan ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Alamat</dt><dd class="mt-1 text-gray-900">{{ $yayasan->alamat ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Telepon</dt><dd class="mt-1 text-gray-900">{{ $yayasan->telepon ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Email</dt><dd class="mt-1 text-gray-900">{{ $yayasan->email ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Website</dt><dd class="mt-1 text-gray-900">{{ $yayasan->website ?: '-' }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                    <h3 class="mb-4 font-display font-bold text-gray-900">Legalitas &amp; Kepemimpinan</h3>
                    <dl class="space-y-4">
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">No. Akta Pendirian</dt><dd class="mt-1 text-gray-900">{{ $yayasan->akta_pendirian_nomor ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Tanggal Akta</dt><dd class="mt-1 text-gray-900">{{ $yayasan->akta_pendirian_tanggal?->translatedFormat('d F Y') ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">No. SK Kemenkumham</dt><dd class="mt-1 text-gray-900">{{ $yayasan->sk_kemenkumham_nomor ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Ketua Pembina</dt><dd class="mt-1 text-gray-900">{{ $yayasan->nama_ketua_pembina ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Ketua Pengurus</dt><dd class="mt-1 text-gray-900">{{ $yayasan->nama_ketua_pengurus ?: '-' }}</dd></div>
                    </dl>
                </div>
            </div>

            {{-- EDIT MODE FORM --}}
            <form x-show="mode === 'edit'" method="POST" action="{{ route('admin.yayasan.update') }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 lg:grid-cols-2" style="display: none;">
                @csrf
                @method('PUT')
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                    <h3 class="font-display font-bold text-gray-900">Identitas Yayasan</h3>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nama Yayasan</label>
                        <input type="text" name="nama" value="{{ old('nama', $yayasan->nama) }}" required class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">NPWP</label>
                        <input type="text" name="npwp_yayasan" value="{{ old('npwp_yayasan', $yayasan->npwp_yayasan) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Alamat</label>
                        <textarea name="alamat" rows="3" class="mt-1 w-full rounded-xl border-gray-300 text-sm">{{ old('alamat', $yayasan->alamat) }}</textarea>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Telepon</label>
                        <input type="text" name="telepon" value="{{ old('telepon', $yayasan->telepon) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" value="{{ old('email', $yayasan->email) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Website</label>
                        <input type="url" name="website" value="{{ old('website', $yayasan->website) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Logo</label>
                        <div class="mt-1 flex items-center gap-3">
                            <template x-if="logoPreview">
                                <img :src="logoPreview" class="h-12 w-12 rounded-lg object-cover border border-gray-200">
                            </template>
                            <template x-if="!logoPreview && '{{ $yayasan->logo }}'">
                                <img src="{{ $yayasan->logo ? Storage::disk('public')->url($yayasan->logo) : '' }}" class="h-12 w-12 rounded-lg object-cover border border-gray-200">
                            </template>
                            <input type="file" name="logo" accept=".jpg,.jpeg,.png,.svg" @change="logoPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" class="text-sm">
                        </div>
                        @error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                    <h3 class="font-display font-bold text-gray-900">Legalitas &amp; Kepemimpinan</h3>
                    <div>
                        <label class="text-sm font-medium text-gray-700">No. Akta Pendirian</label>
                        <input type="text" name="akta_pendirian_nomor" value="{{ old('akta_pendirian_nomor', $yayasan->akta_pendirian_nomor) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Tanggal Akta Pendirian</label>
                        <input type="date" name="akta_pendirian_tanggal" value="{{ old('akta_pendirian_tanggal', $yayasan->akta_pendirian_tanggal?->toDateString()) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">No. SK Kemenkumham</label>
                        <input type="text" name="sk_kemenkumham_nomor" value="{{ old('sk_kemenkumham_nomor', $yayasan->sk_kemenkumham_nomor) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nama Ketua Pembina</label>
                        <input type="text" name="nama_ketua_pembina" value="{{ old('nama_ketua_pembina', $yayasan->nama_ketua_pembina) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nama Ketua Pengurus</label>
                        <input type="text" name="nama_ketua_pengurus" value="{{ old('nama_ketua_pengurus', $yayasan->nama_ketua_pengurus) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                            Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>

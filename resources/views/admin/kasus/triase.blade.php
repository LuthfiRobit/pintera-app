<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-5">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast ? $store.toast.push('error', @js($errors->first())) : null">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumbs --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Triase: {{ $kasus->siswa->nama_lengkap }}</h1>
                <p class="text-xs text-gray-500 mt-0.5">Tentukan tingkat urgensi masalah dan alokasikan tenaga konselor pembimbing.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.kasus.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Triase Kasus</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tugaskan Konselor</b>
            </p>
        </div>

        {{-- Case Overview Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card transition hover:shadow-elevated">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3.5 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="assignment" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Kategori Masalah</p>
                        <p class="text-sm font-bold text-gray-900">{{ $kasus->kategori_masalah }}</p>
                    </div>
                </div>
                <x-badge :tone="$kasus->status->badgeTone()">{{ $kasus->status->label() }}</x-badge>
            </div>

            <div>
                <p class="text-xs font-semibold text-gray-500 mb-1.5">Deskripsi Permasalahan & Catatan Pelapor:</p>
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 text-sm leading-relaxed text-gray-800 font-medium">
                    {{ $kasus->deskripsi }}
                </div>
            </div>
        </div>

        {{-- Triase Allocation Form --}}
        <form method="POST" action="{{ route('admin.kasus.assign-konselor', $kasus) }}" 
              class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-6"
              x-data="triaseForm({ 
                  urgensiAwal: @js(old('tingkat_urgensi', 'sedang')),
                  konselorTipeAwal: @js(old('konselor_tipe', '')),
                  konselorIdAwal: @js(old('konselor_id', ''))
              })">
            @csrf

            <div class="border-b border-gray-100 pb-6">
                <h3 class="font-display text-sm font-bold text-gray-900 mb-1">1. Penilaian Tingkat Urgensi</h3>
                <p class="text-xs text-gray-500 mb-4">Tentukan tingkat kesegeraan penanganan berdasarkan deskripsi masalah yang dilaporkan.</p>
                
                {{-- Hidden input untuk submit form backend --}}
                <input type="hidden" name="tingkat_urgensi" x-bind:value="urgensi">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <!-- Urgensi: Rendah -->
                    <button type="button" 
                            x-on:click="setUrgensi('rendah')"
                            x-bind:class="urgensi === 'rendah' ? 'border-emerald-200 bg-emerald-50 ring-2 ring-emerald-500/20' : 'border-gray-200 bg-white hover:border-emerald-200 hover:bg-emerald-50/50'"
                            class="relative flex flex-col items-start gap-1 rounded-xl border p-4 text-left transition-all duration-200">
                        <div class="flex items-center gap-2">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full"
                                  x-bind:class="urgensi === 'rendah' ? 'bg-emerald-500 text-white shadow-sm' : 'bg-emerald-100 text-emerald-500'">
                                <x-icon name="check" class="h-3.5 w-3.5" x-show="urgensi === 'rendah'" />
                            </span>
                            <span class="text-sm font-bold" x-bind:class="urgensi === 'rendah' ? 'text-emerald-900' : 'text-gray-900'">Rendah</span>
                        </div>
                        <p class="text-[11px] font-medium leading-relaxed mt-1" x-bind:class="urgensi === 'rendah' ? 'text-emerald-700' : 'text-gray-500'">Observasi standar, penanganan fleksibel tanpa tekanan waktu.</p>
                    </button>

                    <!-- Urgensi: Sedang -->
                    <button type="button" 
                            x-on:click="setUrgensi('sedang')"
                            x-bind:class="urgensi === 'sedang' ? 'border-amber-200 bg-amber-50 ring-2 ring-amber-500/20' : 'border-gray-200 bg-white hover:border-amber-200 hover:bg-amber-50/50'"
                            class="relative flex flex-col items-start gap-1 rounded-xl border p-4 text-left transition-all duration-200">
                        <div class="flex items-center gap-2">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full"
                                  x-bind:class="urgensi === 'sedang' ? 'bg-amber-500 text-white shadow-sm' : 'bg-amber-100 text-amber-500'">
                                <x-icon name="check" class="h-3.5 w-3.5" x-show="urgensi === 'sedang'" />
                            </span>
                            <span class="text-sm font-bold" x-bind:class="urgensi === 'sedang' ? 'text-amber-900' : 'text-gray-900'">Sedang</span>
                        </div>
                        <p class="text-[11px] font-medium leading-relaxed mt-1" x-bind:class="urgensi === 'sedang' ? 'text-amber-700' : 'text-gray-500'">Penanganan terencana secara berkala, potensi eskalasi moderat.</p>
                    </button>

                    <!-- Urgensi: Tinggi -->
                    <button type="button" 
                            x-on:click="setUrgensi('tinggi')"
                            x-bind:class="urgensi === 'tinggi' ? 'border-error-200 bg-error-50 ring-2 ring-error-500/20' : 'border-gray-200 bg-white hover:border-error-200 hover:bg-error-50/50'"
                            class="relative flex flex-col items-start gap-1 rounded-xl border p-4 text-left transition-all duration-200">
                        <div class="flex items-center gap-2">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full"
                                  x-bind:class="urgensi === 'tinggi' ? 'bg-error-500 text-white shadow-sm' : 'bg-error-100 text-error-500'">
                                <x-icon name="check" class="h-3.5 w-3.5" x-show="urgensi === 'tinggi'" />
                            </span>
                            <span class="text-sm font-bold" x-bind:class="urgensi === 'tinggi' ? 'text-error-900' : 'text-gray-900'">Tinggi</span>
                        </div>
                        <p class="text-[11px] font-medium leading-relaxed mt-1" x-bind:class="urgensi === 'tinggi' ? 'text-error-700' : 'text-gray-500'">Kritis / Segera. Membutuhkan intervensi langsung dan perlindungan cepat.</p>
                    </button>
                </div>
            </div>

            <div>
                <h3 class="font-display text-sm font-bold text-gray-900 mb-1">2. Alokasi Tenaga Konselor Ahli</h3>
                <p class="text-xs text-gray-500 mb-3">Pilih konselor yang akan bertugas mendampingi siswa. Pilihan didasarkan pada ketersediaan dan beban kasus aktif saat ini.</p>
                
                @if ($kandidat->isEmpty())
                    <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 text-center text-amber-800">
                        <x-icon name="info" class="mx-auto h-6 w-6 text-amber-500 mb-1.5" />
                        <p class="text-sm font-bold">Belum ada konselor yang tersedia</p>
                        <p class="mt-0.5 text-xs text-amber-700">Menunggu alokasi tenaga ahli &mdash; tidak ada Guru BK aktif di lembaga atau Karyawan Psikologi/Konselor pool di yayasan saat ini.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($kandidat as $index => $item)
                            @php
                                $activeCount = \App\Models\Kasus::where($item->tipe === 'guru' ? 'konselor_guru_id' : 'konselor_karyawan_id', $item->model->id)
                                    ->whereIn('status', [\App\Enums\StatusKasus::MenungguConsent, \App\Enums\StatusKasus::Ditugaskan, \App\Enums\StatusKasus::Berjalan, \App\Enums\StatusKasus::Eskalasi])
                                    ->count();
                                $maxCount = $item->model->kapasitas_kasus_aktif;
                                $isFull = ($maxCount && $activeCount >= $maxCount);
                            @endphp
                            <label class="flex cursor-pointer items-start justify-between gap-3 rounded-xl border border-gray-200 p-4 text-sm transition hover:border-brand-500 hover:bg-brand-50/10 shadow-2xs {{ $isFull ? 'bg-error-50/30 border-error-200' : 'bg-white' }}">
                                <div class="flex items-start gap-3">
                                    <input type="radio" name="konselor_pilihan" value="{{ $index }}" required
                                        onclick="document.getElementById('konselor_tipe').value='{{ $item->tipe }}'; document.getElementById('konselor_id').value='{{ $item->model->id }}';"
                                        class="mt-1 text-brand-600 focus:ring-brand-500">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-900">{{ $item->model->nama }}</span>
                                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">
                                                {{ $item->tipe === 'guru' ? 'Guru BK' : 'Karyawan Pool' }}
                                            </span>
                                            @if($item->tipe === 'karyawan' && $item->model->jenisKaryawan)
                                                <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">
                                                    {{ $item->model->jenisKaryawan->nama }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Beban Kerja</p>
                                    <p class="text-xs font-bold {{ $isFull ? 'text-error-600' : 'text-brand-600' }} mt-0.5">
                                        {{ $activeCount }} {{ $maxCount ? ' / ' . $maxCount : '' }} <span class="font-normal text-gray-500">Aktif</span>
                                    </p>
                                    @if($isFull)
                                        <span class="inline-block mt-1 rounded bg-error-100 px-1.5 py-0.5 text-[10px] font-bold text-error-700">Kapasitas Penuh</span>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <input type="hidden" name="konselor_tipe" id="konselor_tipe" value="">
                    <input type="hidden" name="konselor_id" id="konselor_id" value="">
                @endif
                <x-input-error :messages="$errors->get('konselor_id')" class="mt-1.5" />
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('admin.kasus.index') }}" class="inline-flex items-center px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition">
                    Batal
                </a>
                <x-primary-button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-bold shadow-sm" :disabled="$kandidat->isEmpty()">
                    <x-icon name="check_circle" class="mr-1.5 h-4 w-4" />
                    Tugaskan Konselor
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>

{{-- resources/views/admin/kehadiran-sdm/scan.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-0" x-data="{
        arah: 'masuk',
        attendancePointId: '',
        token: '',
        loading: false,
        message: null,
        messageType: 'success',
        scanHistory: [],
        async submitScan() {
            if (!this.token.trim()) return;
            this.loading = true;
            this.message = null;
            const submittedToken = this.token;
            try {
                const response = await fetch('{{ route('admin.kehadiran-sdm.scan.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                    },
                    body: JSON.stringify({ 
                        token: submittedToken, 
                        arah: this.arah,
                        attendance_point_id: this.attendancePointId || null
                    }),
                });
                const data = await response.json();
                this.message = data.message;
                this.messageType = response.ok ? 'success' : 'error';
                
                if (response.ok) {
                    this.scanHistory.unshift({
                        id: Date.now(),
                        message: data.message,
                        arah: this.arah,
                        time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
                        success: true
                    });
                    if (this.scanHistory.length > 5) this.scanHistory.pop();
                }
            } catch (e) {
                this.message = 'Gagal menghubungi server. Periksa koneksi jaringan Anda.';
                this.messageType = 'error';
            } finally {
                this.token = '';
                this.loading = false;
                this.$nextTick(() => {
                    if (this.$refs.tokenInput) this.$refs.tokenInput.focus();
                });
            }
        }
    }">
        
        {{-- Inline Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Pemindai QR Presensi SDM</h1>
                <p class="text-xs text-gray-500 mt-0.5">Arahkan barcode scanner ke QR Code pegawai atau ketik token manual.</p>
            </div>
            <div class="flex items-center gap-2">
                <p class="hidden text-sm text-gray-500 sm:block mr-2">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> SDM <span class="mx-1 text-gray-300">&rsaquo;</span> Kehadiran <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Scan QR</b>
                </p>
                <a href="{{ route('admin.kehadiran-sdm.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50">
                    &larr; <span>Kehadiran SDM</span>
                </a>
            </div>
        </div>

        {{-- Grid 2 Kolom --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            
            {{-- Form Scanner --}}
            <div class="lg:col-span-7 space-y-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-5">
                    
                    {{-- Arah Presensi Radio Pills --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Arah Presensi <span class="text-rose-500">*</span></label>
                        <div class="grid grid-cols-2 gap-3">
                            <label 
                                :class="arah === 'masuk' ? 'border-emerald-300 bg-emerald-50/60 text-emerald-800 ring-2 ring-emerald-500/20' : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100'" 
                                class="flex items-center justify-center gap-2 rounded-xl border p-3 text-xs font-bold transition cursor-pointer"
                            >
                                <input type="radio" x-model="arah" value="masuk" class="text-emerald-600 focus:ring-emerald-500">
                                <span>MASUK</span>
                            </label>
                            <label 
                                :class="arah === 'pulang' ? 'border-amber-300 bg-amber-50/60 text-amber-800 ring-2 ring-amber-500/20' : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100'" 
                                class="flex items-center justify-center gap-2 rounded-xl border p-3 text-xs font-bold transition cursor-pointer"
                            >
                                <input type="radio" x-model="arah" value="pulang" class="text-amber-600 focus:ring-amber-500">
                                <span>PULANG</span>
                            </label>
                        </div>
                    </div>

                    {{-- Titik Absen (opsional) --}}
                    @if (isset($titikAbsen) && $titikAbsen->isNotEmpty())
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Lokasi / Titik Absen (opsional)</label>
                            <select x-model="attendancePointId" class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                                <option value="">— Gunakan Titik Absen Default —</option>
                                @foreach ($titikAbsen as $titik)
                                    <option value="{{ $titik->id }}">{{ $titik->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Form Input Token --}}
                    <form @submit.prevent="submitScan()" class="space-y-4">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Token QR Pegawai <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <input 
                                    x-ref="tokenInput" 
                                    x-model="token" 
                                    type="text" 
                                    autofocus 
                                    placeholder="Scan atau ketik token unik..." 
                                    class="w-full rounded-xl border-gray-200 bg-gray-50 p-3 pr-10 font-mono text-sm text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"
                                >
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m0 14v1m8-8h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <button 
                            type="submit" 
                            x-bind:disabled="loading || !token.trim()" 
                            class="w-full rounded-xl bg-brand-600 px-4 py-3 text-xs font-bold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-50 active:scale-[0.98]"
                        >
                            <span x-text="loading ? 'Memproses Presensi...' : 'Catat Presensi SDM'"></span>
                        </button>
                    </form>

                    {{-- Feedback Message Card --}}
                    <template x-if="message">
                        <div 
                            class="rounded-xl border p-4 text-xs font-semibold flex items-center gap-3 transition"
                            :class="messageType === 'success' ? 'border-emerald-200 bg-emerald-50/80 text-emerald-800' : 'border-rose-200 bg-rose-50/80 text-rose-800'"
                        >
                            <template x-if="messageType === 'success'">
                                <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </template>
                            <template x-if="messageType === 'error'">
                                <svg class="h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </template>
                            <span x-text="message"></span>
                        </div>
                    </template>

                </div>
            </div>

            {{-- History Log Sesi ini --}}
            <div class="lg:col-span-5">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-3">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                        <h2 class="font-display text-xs font-bold text-gray-900 uppercase tracking-wider">Riwayat Pemindaian (Sesi Ini)</h2>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600" x-text="scanHistory.length + ' scan'"></span>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <template x-if="scanHistory.length === 0">
                            <div class="py-8 text-center text-gray-400">
                                <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="mt-2 text-xs font-semibold text-gray-600">Belum ada pemindaian di sesi ini.</p>
                                <p class="text-[11px] text-gray-400 mt-0.5">Hasil pemindaian akan tercatat di sini.</p>
                            </div>
                        </template>

                        <template x-for="item in scanHistory" :key="item.id">
                            <div class="py-2.5 flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-xs font-bold text-gray-900" x-text="item.message"></p>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold mt-1" :class="item.arah === 'masuk' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'" x-text="item.arah.toUpperCase()"></span>
                                </div>
                                <span class="font-mono text-[10px] text-gray-400 shrink-0 mt-0.5" x-text="item.time"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

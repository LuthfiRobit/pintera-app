<x-app-layout>
    <div class="mx-auto max-w-lg space-y-6" x-data="{
        arah: 'masuk',
        token: '',
        loading: false,
        message: null,
        messageType: 'success',
        async submitScan() {
            if (!this.token.trim()) return;
            this.loading = true;
            this.message = null;
            try {
                const response = await fetch('{{ route('admin.kehadiran-sdm.scan.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                    },
                    body: JSON.stringify({ token: this.token, arah: this.arah }),
                });
                const data = await response.json();
                this.message = data.message;
                this.messageType = response.ok ? 'success' : 'error';
            } catch (e) {
                this.message = 'Gagal menghubungi server.';
                this.messageType = 'error';
            } finally {
                this.token = '';
                this.loading = false;
                this.$nextTick(() => this.$refs.tokenInput.focus());
            }
        }
    }">
        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">SDM &amp; Kepegawaian</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Scan Kehadiran QR</h1>
            <p class="mt-1 text-sm text-gray-500">Arahkan scanner QR ke kode pegawai, atau ketik token secara manual.</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            <div class="mb-4 flex gap-4">
                <label class="flex items-center gap-2 text-sm"><input type="radio" x-model="arah" value="masuk" checked> Masuk</label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" x-model="arah" value="pulang"> Pulang</label>
            </div>

            <form @submit.prevent="submitScan()">
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Token QR</label>
                <input x-ref="tokenInput" x-model="token" type="text" autofocus placeholder="Scan atau ketik token..." class="w-full rounded-lg border-gray-200 text-sm">
                <button type="submit" x-bind:disabled="loading" class="mt-4 w-full rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600 disabled:opacity-50">
                    <span x-text="loading ? 'Memproses...' : 'Catat Kehadiran'"></span>
                </button>
            </form>

            <template x-if="message">
                <p class="mt-4 rounded-lg p-3 text-sm" :class="messageType === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'" x-text="message"></p>
            </template>
        </div>
    </div>
</x-app-layout>

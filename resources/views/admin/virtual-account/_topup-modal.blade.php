<div
    x-data="{
        open: false,
        siswaId: null,
        siswaNama: '',
        vaNumber: '',
        balance: 0,
        amount: '',
        keterangan: '',
        formatRupiah(num) {
            return new Intl.NumberFormat('id-ID').format(num || 0);
        },
        buka(detail) {
            this.open = true;
            this.siswaId = detail.siswaId;
            this.siswaNama = detail.siswaNama;
            this.vaNumber = detail.vaNumber;
            this.balance = detail.balance;
            this.amount = '';
            this.keterangan = 'Top-up manual admin';
        },
        simpan() {
            if (!this.amount || this.amount <= 0) {
                if (Alpine.store('toast')) {
                    Alpine.store('toast').push('error', 'Masukkan nominal top-up yang valid.');
                }
                return;
            }
            if (Alpine.store('toast')) {
                Alpine.store('toast').push('success', `Simulasi top-up Rp${this.formatRupiah(this.amount)} untuk ${this.siswaNama} berhasil ditampilkan.`);
            }
            this.open = false;
        }
    }"
    x-on:open-topup-modal.window="buka($event.detail)"
    x-show="open"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center"
    x-cloak
    style="display: none;"
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        class="fixed inset-0 transform transition-all"
        @click="open = false"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>

    {{-- Modal Box --}}
    <div
        x-show="open"
        class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-lg sm:w-full z-10 p-6 relative text-left max-h-[90vh] flex flex-col"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200 shrink-0">
            <div>
                <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="payments" class="h-5 w-5 text-brand-600" />
                    <span>Top-up Saldo Manual</span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Form simulasi top-up saldo wallet siswa via admin.</p>
            </div>
            <button @click="open = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>

        {{-- Form Body --}}
        <div class="mt-4 flex-1 overflow-y-auto pr-1 space-y-4">
            <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-3.5 text-xs text-blue-800 space-y-1">
                <p class="font-semibold flex items-center gap-1.5">
                    <x-icon name="info" class="h-4 w-4 text-blue-600" />
                    Simulasi Mode UI
                </p>
                <p class="text-blue-700">Formulir ini disiapkan untuk antarmuka top-up saldo manual tanpa interaksi backend payment gateway.</p>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold text-gray-700">Nama Siswa</label>
                <input type="text" :value="siswaNama" disabled class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm font-semibold text-gray-800">
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-700">Nomor Virtual Account</label>
                    <input type="text" :value="vaNumber" disabled class="w-full rounded-lg border-gray-200 bg-gray-50 font-mono text-sm text-gray-700">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-700">Saldo Wallet Saat Ini</label>
                    <input type="text" :value="'Rp' + formatRupiah(balance)" disabled class="w-full rounded-lg border-gray-200 bg-gray-50 font-mono text-sm font-semibold text-emerald-700">
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold text-gray-700">Nominal Top-up (Rp) <span class="text-error-500">*</span></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-semibold text-gray-400">Rp</span>
                    <input
                        type="number"
                        x-model="amount"
                        min="1000"
                        step="1000"
                        placeholder="Contoh: 100000"
                        class="w-full rounded-lg border-gray-200 pl-9 text-sm font-semibold text-gray-900 focus:border-brand-500 focus:ring-brand-500"
                    >
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold text-gray-700">Keterangan / Catatan</label>
                <input
                    type="text"
                    x-model="keterangan"
                    placeholder="Catatan top-up..."
                    class="w-full rounded-lg border-gray-200 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500"
                >
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100 shrink-0">
            <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">
                Batal
            </button>
            <button type="button" @click="simpan()" class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">
                Simpan Top-up
            </button>
        </div>
    </div>
</div>

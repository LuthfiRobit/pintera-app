export function otpInput() {
    return {
        digit: ['', '', '', '', '', ''],

        get kode() {
            return this.digit.join('');
        },

        isiKotak(index, event) {
            const nilai = event.target.value.replace(/[^0-9]/g, '').slice(-1);
            this.digit[index] = nilai;

            if (nilai && index < 5) {
                this.$refs['kotak' + (index + 1)].focus();
            }
        },

        tekanBackspace(index, event) {
            if (event.key === 'Backspace' && !this.digit[index] && index > 0) {
                this.$refs['kotak' + (index - 1)].focus();
            }
        },

        tempel(event) {
            const teks = (event.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').slice(0, 6);

            if (!teks) {
                return;
            }

            teks.split('').forEach((angka, i) => {
                this.digit[i] = angka;
            });

            this.$refs['kotak' + Math.min(teks.length - 1, 5)]?.focus();
        },
    };
}

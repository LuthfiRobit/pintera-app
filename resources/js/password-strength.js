export function passwordStrength() {
    return {
        value: '',

        get score() {
            let s = 0;
            if (this.value.length >= 8) s++;
            if (/[a-z]/.test(this.value) && /[A-Z]/.test(this.value)) s++;
            if (/[0-9]/.test(this.value)) s++;
            return s;
        },

        get tier() {
            if (!this.value) return 'empty';
            if (this.score >= 3) return 'strong';
            if (this.score >= 1) return 'mid';
            return 'weak';
        },
    };
}

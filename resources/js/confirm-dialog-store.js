export function registerConfirmDialogStore(Alpine) {
    Alpine.store('confirmDialog', {
        show: false,
        title: '',
        message: '',
        confirmLabel: 'Ya, Hapus',
        cancelLabel: 'Batal',
        resolver: null,

        open(title, message, options = {}) {
            this.title = title;
            this.message = message;
            this.confirmLabel = options.confirmLabel ?? 'Ya, Hapus';
            this.cancelLabel = options.cancelLabel ?? 'Batal';
            this.show = true;

            return new Promise((resolve) => {
                this.resolver = resolve;
            });
        },

        confirm() {
            this.show = false;
            this.resolver?.(true);
            this.resolver = null;
        },

        cancel() {
            this.show = false;
            this.resolver?.(false);
            this.resolver = null;
        },
    });

    window.confirmDialog = (title, message, options) => Alpine.store('confirmDialog').open(title, message, options);
}

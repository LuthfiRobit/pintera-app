import { Html5Qrcode } from 'html5-qrcode';

export function qrCameraScanner(config) {
    config = config || {};

    return {
        elementId: config.elementId || 'qr-camera-reader',
        onScanSuccess: config.onScanSuccess || function () {},
        onCameraError: config.onCameraError || function () {},
        html5Qrcode: null,
        cameraActive: false,
        processing: false,

        async startCamera() {
            if (this.cameraActive || this.html5Qrcode) {
                return;
            }
            this.html5Qrcode = new Html5Qrcode(this.elementId);
            try {
                await this.html5Qrcode.start(
                    { facingMode: 'environment' },
                    {
                        fps: 15,
                        aspectRatio: 1.0,
                        qrbox: (viewfinderWidth, viewfinderHeight) => {
                            const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                            const qrboxSize = Math.max(180, Math.floor(minEdge * 0.72));
                            return { width: qrboxSize, height: qrboxSize };
                        },
                    },
                    (decodedText) => this.handleScanSuccess(decodedText),
                    () => {
                        // Dipanggil per-frame saat tidak ada QR terbaca — noise normal, sengaja diabaikan.
                    },
                );
                this.cameraActive = true;
            } catch (error) {
                this.cameraActive = false;
                this.html5Qrcode = null;
                this.onCameraError(this.describeError(error));
            }
        },

        async stopCamera() {
            if (!this.html5Qrcode) {
                return;
            }
            if (this.cameraActive) {
                try {
                    await this.html5Qrcode.stop();
                } catch (error) {
                    // Stream mungkin sudah berhenti duluan (mis. tab pindah) — aman diabaikan.
                }
            }
            this.html5Qrcode = null;
            this.cameraActive = false;
            this.processing = false;
        },

        handleScanSuccess(decodedText) {
            if (this.processing) {
                return;
            }
            this.processing = true;
            if (this.html5Qrcode) {
                this.html5Qrcode.pause(true);
            }
            this.onScanSuccess(decodedText);
            setTimeout(() => {
                this.processing = false;
                if (this.html5Qrcode && this.cameraActive) {
                    this.html5Qrcode.resume();
                }
            }, 2500);
        },

        describeError(error) {
            const name = error && error.name ? error.name : '';
            if (name === 'NotAllowedError') {
                return 'Kamera tidak dapat diakses: izin ditolak oleh browser.';
            }
            if (name === 'NotFoundError' || name === 'OverconstrainedError') {
                return 'Tidak ada kamera yang terdeteksi pada perangkat ini.';
            }
            if (typeof window !== 'undefined' && window.isSecureContext === false) {
                return 'Kamera hanya bisa diakses lewat koneksi HTTPS atau localhost.';
            }
            return 'Kamera tidak dapat diaktifkan. Silakan gunakan Input Manual.';
        },
    };
}

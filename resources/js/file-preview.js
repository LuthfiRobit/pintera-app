// Registers a global Alpine store so any button on a page (a document row, a
// formulir file answer, ...) can open the same preview modal without needing
// its own local state: $store.filePreview.buka(url, mimeType, namaFile).
//
// PDF.js and page-flip are only ever imported the first time a PDF is actually
// opened (dynamic import), so their combined bundle weight never loads on pages
// that don't preview a document.
export function registerFilePreviewStore(Alpine) {
    Alpine.store('filePreview', {
        terbuka: false,
        tipe: null, // 'pdf' | 'image'
        url: null,
        nama: null,
        memuat: false,
        error: null,
        pageFlip: null,

        async buka(url, mimeType, nama) {
            this.url = url;
            this.nama = nama;
            this.terbuka = true;
            this.memuat = true;
            this.error = null;
            this.tipe = mimeType === 'application/pdf' ? 'pdf' : 'image';

            if (this.tipe === 'pdf') {
                try {
                    await this.renderPdf(url);
                } catch (e) {
                    // Surfaced deliberately (not a generic message) while this feature is
                    // still being diagnosed — swallowing the real error here is exactly
                    // what made the first report of this bug impossible to root-cause.
                    console.error('Gagal memuat pratinjau PDF:', e);
                    this.error = 'Gagal memuat pratinjau PDF: ' + (e?.message || String(e));
                }
            }

            this.memuat = false;
        },

        tutup() {
            this.terbuka = false;
            this.tipe = null;
            if (this.pageFlip) {
                this.pageFlip.destroy();
                this.pageFlip = null;
            }
        },

        async renderPdf(url) {
            const [pdfjsLib, { PageFlip }] = await Promise.all([
                import('pdfjs-dist'),
                import('page-flip'),
            ]);

            pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
                'pdfjs-dist/build/pdf.worker.min.mjs',
                import.meta.url
            ).href;

            const dokumen = await pdfjsLib.getDocument({ url }).promise;

            // PageFlip's own destroy() removes the container element it was given
            // (calls .remove() on it internally) — a single static container can't
            // survive a second open/close cycle, so destroy the old flip instance
            // first, then build a brand new container inside the stable wrapper
            // (which PageFlip never touches) for this render.
            if (this.pageFlip) {
                this.pageFlip.destroy();
                this.pageFlip = null;
            }
            const wrapper = document.getElementById('file-preview-flipbook-wrapper');
            wrapper.innerHTML = '';
            const container = document.createElement('div');
            container.id = 'file-preview-flipbook';
            wrapper.appendChild(container);

            let rasioHalaman = 3 / 4;

            for (let nomor = 1; nomor <= dokumen.numPages; nomor++) {
                const halaman = await dokumen.getPage(nomor);
                const viewport = halaman.getViewport({ scale: 1.3 });
                const canvas = document.createElement('canvas');
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                await halaman.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;

                if (nomor === 1) {
                    rasioHalaman = viewport.width / viewport.height;
                }

                const bungkusHalaman = document.createElement('div');
                bungkusHalaman.className = 'file-preview-page';
                bungkusHalaman.appendChild(canvas);
                container.appendChild(bungkusHalaman);
            }

            // 'fixed' (not 'stretch') deliberately — 'stretch' measures the container's
            // live rendered width/height at construction time, but the container used to
            // sit inside a display:none ancestor here (Alpine only flips x-show to visible
            // after `memuat` clears, which happens after this whole function returns) —
            // that measured 0x0 box is why the book used to intermittently render empty.
            // The wrapper is now kept in normal layout flow (visibility:hidden, not
            // display:none) while loading specifically so its real dimensions can be read
            // here — fit the book to that available space while preserving the PDF page's
            // own aspect ratio, rather than a hardcoded size that clipped non-A4 pages.
            const ruangLebar = wrapper.clientWidth || 380;
            const ruangTinggi = wrapper.clientHeight || 520;
            let lebarBuku = ruangLebar;
            let tinggiBuku = lebarBuku / rasioHalaman;
            if (tinggiBuku > ruangTinggi) {
                tinggiBuku = ruangTinggi;
                lebarBuku = tinggiBuku * rasioHalaman;
            }

            this.pageFlip = new PageFlip(container, {
                width: Math.round(lebarBuku),
                height: Math.round(tinggiBuku),
                size: 'fixed',
                maxShadowOpacity: 0.5,
                showCover: false,
                mobileScrollSupport: false,
            });
            this.pageFlip.loadFromHTML(container.querySelectorAll('.file-preview-page'));
        },
    });
}

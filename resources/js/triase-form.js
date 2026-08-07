export function triaseForm(config = {}) {
    return {
        urgensi: config.urgensiAwal || 'sedang',
        konselorTipe: config.konselorTipeAwal || '',
        konselorId: config.konselorIdAwal || '',
        
        setUrgensi(val) {
            this.urgensi = val;
        },
        
        setKonselor(tipe, id) {
            this.konselorTipe = tipe;
            this.konselorId = id;
        },

        init() {
            // Optional: you can add reactivity or listeners here if needed
        }
    }
}

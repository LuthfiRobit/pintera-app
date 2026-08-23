<x-error-page
    code="419"
    icon="schedule"
    title="Sesi Anda Berakhir"
    :message="\App\Support\ErrorPageMessage::resolve($exception ?? null, 'Demi keamanan, sesi otomatis berakhir setelah tidak aktif. Silakan masuk kembali untuk melanjutkan.')"
/>

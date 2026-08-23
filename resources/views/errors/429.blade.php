<x-error-page
    code="429"
    icon="hourglass_top"
    title="Terlalu Banyak Permintaan"
    :message="\App\Support\ErrorPageMessage::resolve($exception ?? null, 'Sistem sedang menerima banyak aktivitas dari perangkat Anda. Mohon tunggu sebentar lalu coba lagi.')"
/>

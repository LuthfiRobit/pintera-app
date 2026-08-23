<x-error-page
    code="422"
    icon="checklist"
    title="Periksa Kembali Data Anda"
    :message="\App\Support\ErrorPageMessage::resolve($exception ?? null, 'Beberapa data yang dikirim belum sesuai. Silakan periksa kembali formulirnya.')"
/>

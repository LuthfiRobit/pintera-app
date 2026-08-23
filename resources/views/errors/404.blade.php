<x-error-page
    code="404"
    icon="book_search"
    title="Halaman Tidak Ditemukan"
    :message="\App\Support\ErrorPageMessage::resolve($exception ?? null, 'Halaman yang Anda cari mungkin sudah dipindahkan atau tidak tersedia lagi.')"
/>

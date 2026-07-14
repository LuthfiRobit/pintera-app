@props(['lembaga', 'title' => null, 'langkah' => null])

@include('layouts.spmb-public', ['lembaga' => $lembaga, 'title' => $title, 'langkah' => $langkah, 'slot' => $slot])

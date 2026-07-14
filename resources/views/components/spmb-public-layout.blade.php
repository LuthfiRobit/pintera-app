@props(['lembaga', 'title' => null])

@include('layouts.spmb-public', ['lembaga' => $lembaga, 'title' => $title, 'slot' => $slot])

@php
    $pageMeta = config('page-meta.' . str_replace('.', '\.', '/' . trim(request()->path(), '/')));
    $storeName = \App\Models\StoreSetting::storeName();
    $metaTitle = $pageMeta['title'] ?? (isset($title) ? "{$title} - {$storeName}" : $storeName);
    $metaDescription = $pageMeta['description'] ?? "{$storeName} — aplikasi kasir & inventaris";
@endphp
<title>{{ $metaTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">

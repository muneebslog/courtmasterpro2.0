@php
    $pageTitle = filled($title ?? null) ? $title.' - '.config('app.name') : config('app.name');
    $pageDescription = filled($description ?? null)
        ? $description
        : 'Public badminton scores and live match scoring for tournament brackets.';
    $pageImage = filled($image ?? null) ? $image : asset('imgs/hero.png');
    $canonicalUrl = url()->current();
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $pageTitle }}</title>
<link rel="canonical" href="{{ $canonicalUrl }}">
<meta name="description" content="{{ $pageDescription }}">
<meta name="robots" content="index,follow">

<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:image" content="{{ $pageImage }}">
<meta property="og:image:alt" content="{{ $pageTitle }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $pageDescription }}">
<meta name="twitter:image" content="{{ $pageImage }}">

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

<script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => config('app.name'),
        'url' => $canonicalUrl,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>

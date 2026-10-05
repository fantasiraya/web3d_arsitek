@php
    $siteName    = 'Pitcharch';
    $seoTitle    = 'Pitcharch — Presentasi 3D Interaktif & Penampil GLB';
    $seoDesc     = 'Ubah cara Anda mempresentasikan produk ke klien. Pitcharch adalah platform presentasi 3D interaktif khusus file .GLB yang mulus, modern, dan profesional.';
    $seoImage    = asset('images/aether_villa_hero.jpg');
    $seoUrl      = url()->current();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- SEO --}}
        <meta name="description" content="{{ $seoDesc }}">
        <meta name="keywords" content="Pitcharch, presentasi 3D, penampil GLB, presentasi GLB, 3D interaktif, pameran produk 3D, aplikasi presentasi 3D, presentasi produk interaktif, platform presentasi, aplikasi meeting 3D">
        <meta name="author" content="{{ $siteName }}">
        <meta name="robots" content="index, follow">
        <meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
        <link rel="canonical" href="{{ $seoUrl }}">

        {{-- Open Graph --}}
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $seoUrl }}">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDesc }}">
        <meta property="og:image" content="{{ $seoImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $siteName }} — Presentasi 3D Interaktif">
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:locale" content="id_ID">

        {{-- Twitter / X --}}
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:site" content="@pitcharch">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDesc }}">
        <meta name="twitter:image" content="{{ $seoImage }}">

        {{-- JSON-LD --}}
        <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $siteName,
            'url' => url('/'),
            'description' => 'Platform presentasi 3D interaktif khusus file .GLB yang mulus, modern, dan profesional.',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'IDR',
                'description' => 'Coba gratis — 1 proyek selamanya',
            ],
            'inLanguage' => 'id',
            'publisher' => [
                '@type' => 'Organization',
                'name' => $siteName,
                'url' => url('/'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
        </script>

        {{-- Favicons --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
        <link rel="icon" href="/favicon-16x16.png" type="image/png" sizes="16x16">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#07080b" media="(prefers-color-scheme: dark)">

        {{-- Dark mode detection --}}
        <script>
            (function () {
                const appearance = '{{ $appearance ?? "system" }}';
                if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <style>
            html { background-color: oklch(1 0 0); }
            html.dark { background-color: oklch(0.145 0 0); }
        </style>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])

        {{-- Satu-satunya <title> --}}
        <x-inertia::head>
            <title>{{ $seoTitle }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @php($brand = \App\Services\Branding::get())
        <title inertia>{{ $brand['name'] }}</title>
        <link rel="icon" href="{{ $brand['favicon_url'] ?? 'data:image/svg+xml,'.rawurlencode('<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 32 32\'><rect width=\'32\' height=\'32\' rx=\'8\' fill=\'#2547d9\'/><path d=\'M9 22V10l7 5 7-5v12\' stroke=\'#fff\' stroke-width=\'2.4\' fill=\'none\' stroke-linejoin=\'round\'/></svg>') }}">
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">window.__BRAND__ = @json($brand['name']);</script>

        <!-- 網址分享縮圖 (Open Graph / Twitter):使用品牌 Logo,沒有 Logo 則用 Favicon -->
        @php($shareImage = $brand['logo_url'] ?? $brand['favicon_url'])
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $brand['name'] }}">
        <meta property="og:title" content="{{ $brand['name'] }}">
        <meta property="og:url" content="{{ url()->current() }}">
        @if ($shareImage)
        <meta property="og:image" content="{{ $shareImage }}">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:image" content="{{ $shareImage }}">
        @endif

        <!-- Dark Mode Anti-FOUC Script -->
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            (function() {
                try {
                    var theme = localStorage.getItem('theme');
                    var isDark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    if (isDark) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                } catch (_) {}
            })();
        </script>

        <!-- PWA:可安裝到手機/桌面主畫面 -->
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#2547d9">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ $brand['name'] }}">
        <link rel="apple-touch-icon" href="/pwa/apple-touch-icon.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|noto-sans-tc:400,500,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes(nonce: \Illuminate\Support\Facades\Vite::cspNonce())
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

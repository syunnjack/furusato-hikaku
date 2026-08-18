<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name') . ' | 返礼品を口コミで比較して選ぶ')</title>
    <meta name="description" content="@yield('description', 'ふるさと納税の返礼品をジャンル・地域・寄付額から検索し、楽天市場の評価と利用者口コミで比較できます。')">
    @php
        // url()->current() はクエリを落とすため、そのまま canonical にすると
        // /search?category=肉 のようなカテゴリ・地域ごとのページが、すべて
        // /search を正規URLとして申告してしまい、個別にインデックスされない。
        // 内容が変わる条件だけを canonical に残す（並び順や計測用の値は含めない）。
        $canonicalKeys = ['category', 'prefecture', 'keyword', 'min_price', 'max_price'];
        $canonicalQuery = array_filter(
            request()->only($canonicalKeys),
            fn ($value) => $value !== null && $value !== ''
        );
        ksort($canonicalQuery);
        $canonicalUrl = url()->current() . ($canonicalQuery ? '?' . http_build_query($canonicalQuery) : '');
    @endphp
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @stack('robots')
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', config('app.name') . ' | 返礼品を口コミで比較して選ぶ')">
    <meta property="og:description" content="@yield('description', '実在するふるさと納税返礼品を、地域・寄付額・口コミで比較できます。')">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:locale" content="ja_JP">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('structured-data')
    @if(config('services.ga4.id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.ga4.id') }}"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','{{ config('services.ga4.id') }}');</script>
    @endif
</head>
<body>
    <header class="site-header">
        <nav class="container site-nav" aria-label="メインナビゲーション">
            <a href="{{ route('furusato.index') }}" class="site-logo"><span>ふるさと</span>納税比較</a>
            <div class="site-nav__links">
                <a href="{{ route('furusato.search', ['sort' => 'popular']) }}">返礼品を探す</a>
                <a href="{{ route('furusato.search', ['category' => '肉']) }}">人気カテゴリ</a>
                <a href="{{ route('municipalities.index') }}">自治体で選ぶ</a>
                <a href="{{ route('about') }}">このサイトについて</a>
            </div>
        </nav>
    </header>

    <main class="container site-main">@yield('content')</main>

    <footer class="site-footer">
        <div class="container site-footer__grid">
            <div><a href="{{ route('furusato.index') }}" class="site-logo site-logo--footer"><span>ふるさと</span>納税比較</a><p>実在する返礼品を、寄付額・地域・レビューから見つける比較情報サイトです。</p></div>
            <div><strong>返礼品を探す</strong><a href="{{ route('furusato.search', ['category' => '肉']) }}">肉</a><a href="{{ route('furusato.search', ['category' => '海鮮・魚介']) }}">海鮮・魚介</a><a href="{{ route('furusato.search', ['category' => '米・パン']) }}">米・パン</a></div>
            <div><strong>寄附先を知る</strong><a href="{{ route('municipalities.index') }}">自治体別の受入額</a><a href="{{ route('municipalities.prefecture', 'hokkaido') }}">北海道の自治体</a><a href="{{ route('municipalities.prefecture', 'miyazaki') }}">宮崎県の自治体</a></div>
            <div><strong>サイト情報</strong><a href="{{ route('about') }}">このサイトについて</a><a href="{{ route('sitemap') }}">サイトマップ</a></div>
        </div>
        <div class="container site-footer__legal">
            <p>当サイトは楽天アフィリエイトを利用しています。掲載内容・寄付額・在庫は変更される場合があります。</p>
            <!-- Rakuten Web Services Attribution Snippet FROM HERE -->
            <a href="https://developers.rakuten.com/" target="_blank" rel="noopener">Supported by Rakuten Developers</a>
            <!-- Rakuten Web Services Attribution Snippet TO HERE -->
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

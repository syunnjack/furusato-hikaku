@extends('layouts.app')

@section('title', config('app.name') . ' | 実データで探すふるさと納税返礼品')
@section('description', number_format($catalogCount) . '件の実在するふるさと納税返礼品を、カテゴリ・地域・寄付額・口コミで比較できます。楽天市場の最新データを毎日更新。')

@push('structured-data')
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => config('app.name'),
    'url' => url('/'),
    'description' => '実在するふるさと納税返礼品をカテゴリ・地域・寄付額・口コミで探せる比較情報サイト。',
    'inLanguage' => 'ja',
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => route('furusato.search') . '?keyword={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')
<section class="hero-panel">
  <div class="hero-panel__content">
    <span class="eyebrow">楽天市場の返礼品データを毎日更新</span>
    <h1>欲しい返礼品を、<br class="d-none d-md-block">地域・寄付額・口コミから。</h1>
    <p>実在する返礼品を横断検索。人気順だけでなく、寄付額や都道府県で絞り込んで、自分に合う一品を見つけられます。</p>

    <form method="GET" action="{{ route('furusato.search') }}" class="hero-search">
      <label for="hero-keyword" class="visually-hidden">返礼品を検索</label>
      <input id="hero-keyword" type="search" name="keyword" placeholder="牛肉、ホタテ、気仙沼市など" required>
      <button type="submit">返礼品を検索</button>
    </form>

    <div class="quick-links">
      <span>人気:</span>
      @foreach(['牛肉', 'ホタテ', '米 10kg', 'シャインマスカット', 'ティッシュ'] as $term)
        <a href="{{ route('furusato.search', ['keyword' => $term]) }}">{{ $term }}</a>
      @endforeach
    </div>
  </div>
</section>

<section class="catalog-stats" aria-label="掲載データ概要">
  <div><strong>{{ number_format($catalogCount) }}</strong><span>掲載返礼品</span></div>
  <div><strong>{{ number_format($prefectureCount) }}</strong><span>都道府県</span></div>
  <div><strong>{{ number_format($municipalityCount) }}</strong><span>自治体</span></div>
  <div><strong>毎日</strong><span>データ更新</span></div>
</section>

<section class="content-section">
  <div class="section-heading">
    <div>
      <span class="eyebrow">CATEGORY</span>
      <h2>カテゴリから探す</h2>
    </div>
    <a href="{{ route('furusato.search', ['sort' => 'popular']) }}">すべて見る →</a>
  </div>
  <div class="category-grid">
    @php($categoryIcons = ['肉' => '🥩', '海鮮・魚介' => '🐟', '米・パン' => '🌾', 'フルーツ' => '🍓', '野菜' => '🥬', 'お酒' => '🍶', 'スイーツ' => '🍰', '日用品' => '🧻', '家電' => '📺', '旅行・体験' => '♨️', '工芸品' => '🏺', 'その他' => '🎁'])
    @foreach($categories as $category)
      <a href="{{ route('furusato.search', ['category' => $category]) }}" class="category-tile">
        <span class="category-tile__icon">{{ $categoryIcons[$category] ?? '🎁' }}</span>
        <span><strong>{{ $category }}</strong><small>{{ number_format((int) ($categoryCounts[$category] ?? 0)) }}件</small></span>
      </a>
    @endforeach
  </div>
</section>

@if($featured->isNotEmpty())
<section class="content-section">
  <div class="section-heading">
    <div><span class="eyebrow">POPULAR</span><h2>レビューで人気の返礼品</h2></div>
    <a href="{{ route('furusato.search', ['sort' => 'popular']) }}">人気順をもっと見る →</a>
  </div>
  <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 g-lg-4">
    @foreach($featured as $item)
      <div class="col">@include('furusato._item-card', ['item' => $item])</div>
    @endforeach
  </div>
</section>
@endif

@if($affordable->isNotEmpty())
<section class="content-section content-section--tint">
  <div class="section-heading">
    <div><span class="eyebrow">UNDER ¥12,000</span><h2>12,000円以下の人気返礼品</h2></div>
    <a href="{{ route('furusato.search', ['max_price' => 12000, 'sort' => 'popular']) }}">この金額帯をもっと見る →</a>
  </div>
  <div class="row row-cols-2 row-cols-md-4 g-3">
    @foreach($affordable as $item)
      <div class="col">@include('furusato._item-card', ['item' => $item])</div>
    @endforeach
  </div>
</section>
@endif

@if($popularPrefectures->isNotEmpty())
<section class="content-section">
  <div class="section-heading"><div><span class="eyebrow">AREA</span><h2>地域から探す</h2></div></div>
  <div class="prefecture-links">
    @foreach($popularPrefectures as $area)
      <a href="{{ route('furusato.search', ['prefecture' => $area->prefecture]) }}">
        {{ $area->prefecture }} <small>{{ number_format($area->total) }}件</small>
      </a>
    @endforeach
  </div>
</section>
@endif

@if($topMunicipalities->isNotEmpty())
<section class="content-section">
  <div class="section-heading">
    <div><span class="eyebrow">MUNICIPALITY</span><h2>寄附が多く集まった自治体</h2></div>
    <a href="{{ route('municipalities.index') }}">全国の受入額を見る →</a>
  </div>
  <p class="text-muted">
    総務省が公表した{{ $municipalityFiscalYear }}の実績です。自治体名から、受入額の推移や寄附の使い道を確認できます。
  </p>
  <div class="prefecture-links">
    @foreach($topMunicipalities as $municipality)
      <a href="{{ route('municipalities.show', [$municipality->prefecture_slug, $municipality->code]) }}">
        {{ $municipality->city }} <small>{{ number_format(round($municipality->amount / 100000000, 1), 1) }}億円</small>
      </a>
    @endforeach
  </div>
</section>
@endif

<section class="guide-panel">
  <div><span class="guide-panel__number">1</span><strong>条件を決める</strong><p>カテゴリ・地域・寄付額を選択</p></div>
  <div><span class="guide-panel__number">2</span><strong>実データで比較</strong><p>寄付額と楽天レビューを確認</p></div>
  <div><span class="guide-panel__number">3</span><strong>公式ページで申込</strong><p>最新の在庫・配送条件を確認</p></div>
</section>
@endsection

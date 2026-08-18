@extends('layouts.app')

@php($pageLabel = $keyword !== '' ? '「' . $keyword . '」' : '選択した条件')
@section('title', $pageLabel . 'のふるさと納税返礼品 ' . number_format($items->total()) . '件 | ' . config('app.name'))
@section('description', $pageLabel . 'に該当する実在のふるさと納税返礼品を、寄付額・地域・楽天レビューで比較できます。')

@push('structured-data')
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => $pageLabel . 'の返礼品検索結果',
    'numberOfItems' => $items->total(),
    'itemListElement' => $items->getCollection()->values()->map(fn ($item, $i) => [
        '@type' => 'ListItem',
        'position' => $items->firstItem() + $i,
        'url' => route('furusato.show', $item),
        'name' => $item->item_name,
    ])->all(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb small"><li class="breadcrumb-item"><a href="{{ route('furusato.index') }}">トップ</a></li><li class="breadcrumb-item active">返礼品検索</li></ol>
</nav>

<section class="search-panel mb-4">
  <form method="GET" action="{{ route('furusato.search') }}" class="row g-3">
    <div class="col-12 col-lg-5">
      <label for="keyword" class="form-label">キーワード</label>
      <input id="keyword" type="search" name="keyword" value="{{ $keyword }}" class="form-control" placeholder="返礼品・自治体名">
    </div>
    <div class="col-6 col-lg-3">
      <label for="category" class="form-label">カテゴリ</label>
      <select id="category" name="category" class="form-select">
        <option value="">すべて</option>
        @foreach($categories as $category)
          <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-6 col-lg-2">
      <label for="prefecture" class="form-label">都道府県</label>
      <select id="prefecture" name="prefecture" class="form-select">
        <option value="">全国</option>
        @foreach($prefectures as $prefecture)
          <option value="{{ $prefecture }}" @selected(($filters['prefecture'] ?? '') === $prefecture)>{{ $prefecture }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-6 col-lg-2">
      <label for="sort" class="form-label">並び順</label>
      <select id="sort" name="sort" class="form-select">
        @foreach(['popular' => 'レビュー件数順', 'rating' => '評価順', 'price-asc' => '寄付額が低い順', 'price-desc' => '寄付額が高い順', 'newest' => '新着順'] as $value => $label)
          <option value="{{ $value }}" @selected(($filters['sort'] ?? 'popular') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-6 col-lg-3">
      <label for="min-price" class="form-label">寄付額（下限）</label>
      <input id="min-price" type="number" name="min_price" value="{{ $filters['min_price'] ?? '' }}" class="form-control" min="0" step="1000" placeholder="5,000">
    </div>
    <div class="col-6 col-lg-3">
      <label for="max-price" class="form-label">寄付額（上限）</label>
      <input id="max-price" type="number" name="max_price" value="{{ $filters['max_price'] ?? '' }}" class="form-control" min="0" step="1000" placeholder="30,000">
    </div>
    <div class="col-12 col-lg-3 d-flex align-items-end gap-2">
      <button type="submit" class="btn btn-primary flex-grow-1">条件を適用</button>
      <a href="{{ route('furusato.index') }}" class="btn btn-outline-secondary">解除</a>
    </div>
  </form>
</section>

<div class="section-heading section-heading--compact">
  <div><span class="eyebrow">SEARCH RESULTS</span><h1>{{ $pageLabel }}の返礼品</h1></div>
  <strong>{{ number_format($items->total()) }}件</strong>
</div>

@if($keyword !== '')
  @php($isWatching = session('line_user_local_id') ? \App\Models\Watch::where('line_user_id', session('line_user_local_id'))->where('keyword', $keyword)->exists() : false)
  {{-- LINEの認証情報が未設定のうちは、押すとLINE側でエラーになるので出さない --}}
  @if (config('services.line.login_channel_id'))
    <form method="POST" action="{{ route('watches.toggle') }}" class="mb-4">
      @csrf
      <input type="hidden" name="keyword" value="{{ $keyword }}">
      <button type="submit" class="btn {{ $isWatching ? 'btn-outline-secondary' : 'btn-line' }} btn-sm">
        {{ $isWatching ? '🔕 ウォッチをやめる' : '🔔 新着・再登場をLINEで通知' }}
      </button>
    </form>
  @else
    <p class="mb-4">
      <button type="button" class="btn btn-secondary btn-sm" disabled>🔔 新着・再登場をLINEで通知（準備中）</button>
    </p>
  @endif
@endif

@if($items->isEmpty())
  <div class="empty-state"><h2>該当する返礼品が見つかりませんでした</h2><p>キーワードを短くするか、寄付額の範囲を広げてください。</p></div>
@else
  <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 g-lg-4">
    @foreach($items as $item)
      <div class="col">@include('furusato._item-card', ['item' => $item])</div>
    @endforeach
  </div>
  <div class="d-flex justify-content-center mt-5">{{ $items->links() }}</div>
@endif

@if(!empty($faq))
<section class="content-section border-top mt-5">
  <div class="section-heading"><div><span class="eyebrow">FAQ</span><h2>よくある質問</h2></div></div>
  <div class="accordion" id="search-faq">
    @foreach($faq as $index => $qa)
      <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button {{ $index ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faq-{{ $index }}">{{ $qa['question'] }}</button></h3><div id="faq-{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" data-bs-parent="#search-faq"><div class="accordion-body">{{ $qa['answer'] }}</div></div></div>
    @endforeach
  </div>
</section>
@endif
@endsection

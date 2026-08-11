@extends('layouts.app')

@section('title', $item->item_name . ' | ' . config('app.name'))
@section('description', $item->item_name . 'の寄付額、自治体、楽天市場レビュー、利用者口コミを確認できます。寄付額' . number_format($item->item_price) . '円。')

@push('structured-data')
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $item->item_name,
    'image' => $item->image_url,
    'description' => $item->catchcopy ?: $item->item_name,
    'offers' => [
        '@type' => 'Offer', 'priceCurrency' => 'JPY', 'price' => $item->item_price,
        'url' => $item->affiliate_url ?: $item->item_url, 'availability' => 'https://schema.org/InStock',
    ],
    'aggregateRating' => $item->review_count > 0 ? [
        '@type' => 'AggregateRating', 'ratingValue' => $item->review_average, 'reviewCount' => $item->review_count,
    ] : null,
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')
<nav aria-label="breadcrumb" class="mb-4"><ol class="breadcrumb small"><li class="breadcrumb-item"><a href="{{ route('furusato.index') }}">トップ</a></li><li class="breadcrumb-item"><a href="{{ route('furusato.search', ['category' => $item->category]) }}">{{ $item->category }}</a></li><li class="breadcrumb-item active">返礼品詳細</li></ol></nav>

@if(session('review_success'))<div class="alert alert-success">口コミを投稿しました。ありがとうございます。</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<article class="gift-detail row g-4 g-lg-5">
  <div class="col-lg-5">
    @if($item->image_url)<img src="{{ $item->image_url }}" alt="{{ $item->item_name }}" class="gift-detail__image">@endif
  </div>
  <div class="col-lg-7">
    <div class="d-flex gap-2 mb-3"><a class="gift-badge" href="{{ route('furusato.search', ['category' => $item->category]) }}">{{ $item->category }}</a>@if($item->prefecture)<a class="gift-badge gift-badge--muted" href="{{ route('furusato.search', ['prefecture' => $item->prefecture]) }}">{{ $item->prefecture }}</a>@endif</div>
    <h1>{{ $item->item_name }}</h1>
    @if($item->catchcopy)<p class="text-muted">{{ $item->catchcopy }}</p>@endif
    <dl class="gift-detail__facts">
      <div><dt>寄付金額</dt><dd>{{ number_format($item->item_price) }}円</dd></div>
      @if($item->municipality)<div><dt>自治体</dt><dd>{{ $item->municipality }}@if($item->prefecture)（{{ $item->prefecture }}）@endif</dd></div>@endif
      <div><dt>掲載元</dt><dd>{{ $item->shop_name ?: '楽天市場' }}</dd></div>
      @if($item->review_count > 0)<div><dt>楽天市場評価</dt><dd><span class="text-warning">★{{ number_format($item->review_average, 1) }}</span>（{{ number_format($item->review_count) }}件）</dd></div>@endif
    </dl>
    <a href="{{ $item->affiliate_url ?: $item->item_url }}" class="btn btn-primary btn-lg w-100" target="_blank" rel="noopener noreferrer sponsored">楽天市場で最新情報を確認・申し込む</a>
    <p class="small text-muted mt-2">寄付額・在庫・発送時期は変更される場合があります。申込前に楽天市場の商品ページで最新情報をご確認ください。</p>
  </div>
</article>

<section class="content-section border-top mt-5">
  <div class="section-heading"><div><span class="eyebrow">REVIEWS</span><h2>この返礼品の口コミ</h2></div><strong>{{ $reviews->count() }}件</strong></div>
  @forelse($reviews as $review)
    <div class="review-card"><div><span class="text-warning">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span> <strong>{{ $review->nickname }}</strong> <small>{{ $review->created_at->format('Y-m-d') }}</small></div><p>{{ $review->comment }}</p></div>
  @empty
    <p class="text-muted">サイト内口コミはまだありません。実際に選んだ感想をお寄せください。</p>
  @endforelse

  <details class="review-form mt-4"><summary>口コミを投稿する</summary>
    <form method="POST" action="{{ route('reviews.store') }}" class="row g-3 mt-2">@csrf
      <input type="hidden" name="item_id" value="{{ $item->item_code }}"><input type="hidden" name="title" value="{{ $item->item_name }}">
      <div style="position:absolute;left:-9999px;" aria-hidden="true"><label>ウェブサイト <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="col-md-4"><label class="form-label">ニックネーム（任意）</label><input type="text" name="nickname" class="form-control" maxlength="30"></div>
      <div class="col-md-3"><label class="form-label">評価</label><select name="rating" class="form-select" required><option value="">選択</option>@for($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}">{{ str_repeat('★', $rating) }}{{ str_repeat('☆', 5 - $rating) }}</option>@endfor</select></div>
      <div class="col-12"><label class="form-label">口コミ</label><textarea name="comment" class="form-control" rows="4" minlength="5" maxlength="1000" required></textarea></div>
      <div class="col-12"><button class="btn btn-outline-primary" type="submit">口コミを投稿</button></div>
    </form>
  </details>
</section>

@if($related->isNotEmpty())
<section class="content-section border-top"><div class="section-heading"><div><span class="eyebrow">RELATED</span><h2>同じカテゴリの人気返礼品</h2></div></div><div class="row row-cols-2 row-cols-md-4 g-3">@foreach($related as $relatedItem)<div class="col">@include('furusato._item-card', ['item' => $relatedItem])</div>@endforeach</div></section>
@endif
@endsection

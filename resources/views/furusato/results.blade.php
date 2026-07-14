@extends('layouts.app')

@section('title', $keyword . 'の返礼品一覧・口コミ比較 | ' . config('app.name'))
@section('description', $keyword . 'に関連するふるさと納税の返礼品一覧です。楽天市場の申し込みリンクと、実際に選んだ人の口コミをまとめて確認できます。')

@push('structured-data')
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $keyword . 'の検索結果'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@if (!empty($faq))
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => collect($faq)->map(fn ($qa) => [
        '@type' => 'Question',
        'name' => $qa['question'],
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text' => $qa['answer'],
        ],
    ])->all(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endif
@if (!empty($results))
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => $keyword . 'の返礼品検索結果',
    'itemListElement' => collect($results)->values()->map(function ($item, $i) use ($reviews) {
        $itemReviews = $reviews->get($item['itemCode'] ?? null);

        $entry = [
            '@type' => 'Product',
            'name' => $item['itemName'] ?? '',
            'url' => $item['itemUrl'] ?? null,
            'image' => $item['mediumImageUrls'][0] ?? null,
        ];

        if ($itemReviews && $itemReviews->count() > 0) {
            $entry['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round($itemReviews->avg('rating'), 1),
                'reviewCount' => $itemReviews->count(),
            ];
        }

        return [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'item' => $entry,
        ];
    })->all(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endif
@endpush

@section('content')
<div class="container">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('furusato.index') }}">{{ config('app.name') }}</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $keyword }}</li>
    </ol>
  </nav>

  <h1>「{{ $keyword }}」の返礼品検索結果</h1>

  @if (session('review_success'))
    <div class="alert alert-success py-2">口コミを投稿しました！</div>
  @endif

  @if(empty($results))
    <p>「{{ $keyword }}」に一致する返礼品が見つかりませんでした。別のキーワードもお試しください。</p>
    <a href="{{ route('furusato.index') }}" class="btn btn-outline-primary">トップに戻る</a>
  @else
    <p class="text-muted">{{ count($results) }}件の返礼品を掲載しています。</p>
    @foreach($results as $item)
      @php
        $itemId = $item['itemCode'] ?? null;
        $itemReviews = $reviews->get($itemId, collect());
      @endphp
      <article class="mb-4 pb-4 border-bottom row">
        <div class="col-3 col-md-2">
          @if(!empty($item['mediumImageUrls'][0]))
            <img src="{{ $item['mediumImageUrls'][0] }}" alt="{{ $item['itemName'] ?? '' }}" class="img-fluid" loading="lazy">
          @endif
        </div>
        <div class="col-9 col-md-10">
          <h2 class="h5">{{ $item['itemName'] ?? '' }}</h2>
          <p class="mb-1 text-muted small">{{ $item['shopName'] ?? '' }}</p>
          @if(!empty($item['itemPrice']))
            <p class="mb-1">寄付金額 {{ number_format($item['itemPrice']) }}円（税込）</p>
          @endif
          @if(!empty($item['reviewCount']))
            <p class="mb-1 small text-muted">楽天市場評価: ★{{ $item['reviewAverage'] ?? '-' }}（{{ $item['reviewCount'] }}件）</p>
          @endif
          <div class="mb-2">
            <a href="{{ $item['affiliateUrl'] ?? ($item['itemUrl'] ?? '#') }}" class="btn btn-sm btn-primary" target="_blank" rel="noopener noreferrer sponsored">楽天市場で見る</a>
          </div>

          @if($itemReviews->isEmpty())
            <p class="text-muted small">まだ口コミがありません。最初の口コミを投稿してみませんか？</p>
          @else
            <p class="fw-bold small mb-2">
              口コミ {{ $itemReviews->count() }}件（平均★{{ round($itemReviews->avg('rating'), 1) }}）
            </p>
            @foreach($itemReviews as $review)
              <div class="border rounded p-2 mb-2 small">
                <div>{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                  <strong>{{ $review->nickname }}</strong>
                  <span class="text-muted">{{ $review->created_at->format('Y-m-d') }}</span>
                </div>
                <div>{{ $review->comment }}</div>
              </div>
            @endforeach
          @endif

          <details class="mt-2">
            <summary class="small">口コミを投稿する</summary>
            <form method="POST" action="{{ route('reviews.store') }}" class="mt-2">
              @csrf
              <input type="hidden" name="item_id" value="{{ $itemId }}">
              <input type="hidden" name="title" value="{{ $item['itemName'] ?? '' }}">
              <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <label>ウェブサイト <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
              </div>
              <div class="mb-2">
                <label class="form-label small">ニックネーム（任意）</label>
                <input type="text" name="nickname" class="form-control form-control-sm" maxlength="30">
              </div>
              <div class="mb-2">
                <label class="form-label small">評価</label>
                <select name="rating" class="form-select form-select-sm" required>
                  <option value="">選択してください</option>
                  <option value="5">★★★★★</option>
                  <option value="4">★★★★☆</option>
                  <option value="3">★★★☆☆</option>
                  <option value="2">★★☆☆☆</option>
                  <option value="1">★☆☆☆☆</option>
                </select>
              </div>
              <div class="mb-2">
                <label class="form-label small">口コミ</label>
                <textarea name="comment" class="form-control form-control-sm" rows="3" minlength="5" maxlength="1000" required></textarea>
              </div>
              @if ($errors->any())
                <p class="text-danger small">{{ $errors->first() }}</p>
              @endif
              <button type="submit" class="btn btn-sm btn-outline-primary">投稿する</button>
            </form>
          </details>
        </div>
      </article>
    @endforeach

    @if(!empty($faq))
      <section class="mt-4 pt-4 border-top">
        <h2 class="h5">よくある質問</h2>
        @foreach($faq as $qa)
          <div class="mb-3">
            <p class="fw-bold mb-1">Q. {{ $qa['question'] }}</p>
            <p class="mb-0">A. {{ $qa['answer'] }}</p>
          </div>
        @endforeach
      </section>
    @endif
  @endif
</div>
@endsection

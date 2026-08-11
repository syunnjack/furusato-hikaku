<article class="gift-card h-100">
  <a href="{{ route('furusato.show', $item) }}" class="gift-card__image-link">
    @if($item->image_url)
      <img src="{{ $item->image_url }}" alt="{{ $item->item_name }}" class="gift-card__image" loading="lazy">
    @else
      <div class="gift-card__placeholder">画像準備中</div>
    @endif
  </a>
  <div class="gift-card__body">
    <div class="d-flex gap-1 flex-wrap mb-2">
      @if($item->category)
        <a href="{{ route('furusato.search', ['category' => $item->category]) }}" class="gift-badge">{{ $item->category }}</a>
      @endif
      @if($item->prefecture)
        <a href="{{ route('furusato.search', ['prefecture' => $item->prefecture]) }}" class="gift-badge gift-badge--muted">{{ $item->prefecture }}</a>
      @endif
    </div>
    <h3 class="gift-card__title">
      <a href="{{ route('furusato.show', $item) }}">{{ $item->item_name }}</a>
    </h3>
    @if($item->municipality || $item->shop_name)
      <p class="gift-card__location">{{ $item->municipality ?: $item->shop_name }}</p>
    @endif
    <div class="gift-card__footer">
      <strong class="gift-card__price">{{ number_format($item->item_price) }}<small>円</small></strong>
      @if($item->review_count > 0)
        <span class="gift-card__rating" aria-label="評価{{ number_format($item->review_average, 1) }}、レビュー{{ number_format($item->review_count) }}件">
          ★{{ number_format($item->review_average, 1) }} <small>({{ number_format($item->review_count) }})</small>
        </span>
      @endif
    </div>
  </div>
</article>

@extends('layouts.app')

@php
    $name = $municipality->display_name;
    $series = collect($municipality->series ?? [])->filter(fn ($row) => $row['amount'] !== null)->values();
    $latest = $series->last();
    $previous = $series->count() > 1 ? $series[$series->count() - 2] : null;
@endphp

@section('title', $name . 'のふるさと納税｜' . $meta['fiscalYear'] . 'の受入額と使い道 | ' . config('app.name'))
@section('description', $municipality->full_name . 'が' . $meta['fiscalYear'] . 'に受け入れたふるさと納税の金額・件数、寄附の使い道、募集にかかった費用の割合を、総務省の公表値でまとめました。')

@push('structured-data')
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => '自治体別の受入額', 'item' => route('municipalities.index')],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $municipality->prefecture, 'item' => route('municipalities.prefecture', $municipality->prefecture_slug)],
        ['@type' => 'ListItem', 'position' => 4, 'name' => $name, 'item' => route('municipalities.show', [$municipality->prefecture_slug, $municipality->code])],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')
<nav aria-label="パンくず" class="breadcrumb small mb-3">
  <a href="{{ route('furusato.index') }}">トップ</a><span class="mx-2 text-muted">/</span>
  <a href="{{ route('municipalities.index') }}">自治体別の受入額</a><span class="mx-2 text-muted">/</span>
  <a href="{{ route('municipalities.prefecture', $municipality->prefecture_slug) }}">{{ $municipality->prefecture }}</a><span class="mx-2 text-muted">/</span>
  <span class="text-muted">{{ $name }}</span>
</nav>

<div class="section-heading">
  <div>
    <span class="eyebrow">{{ $municipality->prefecture }}｜{{ $meta['fiscalYear'] }}の実績</span>
    <h1>{{ $name }}のふるさと納税</h1>
  </div>
  @if($municipality->city)
    <a href="{{ route('furusato.search', ['keyword' => $municipality->city]) }}">{{ $municipality->city }}の返礼品を探す →</a>
  @endif
</div>

<section class="catalog-stats" aria-label="受入実績">
  <div><strong>{{ $municipality->amount ? number_format($municipality->amount) : '—' }}</strong><span>受入額（円）</span></div>
  <div><strong>{{ $municipality->count ? number_format($municipality->count) : '—' }}</strong><span>受入件数</span></div>
  <div><strong>{{ $municipality->average_per_donation ? number_format($municipality->average_per_donation) : '—' }}</strong><span>1件あたり（円）</span></div>
  <div><strong>{{ $municipality->national_rank ? $municipality->national_rank . '位' : '—' }}</strong><span>全国の受入額順位</span></div>
</section>

<section class="content-section">
  <h2 class="h4 fw-bold mb-3">{{ $meta['fiscalYear'] }}の受入状況</h2>
  <dl class="gift-detail__facts">
    <div><dt>受入額</dt><dd>{{ $municipality->amount ? number_format($municipality->amount) . '円' : '公表なし' }}</dd></div>
    <div><dt>受入件数</dt><dd>{{ $municipality->count ? number_format($municipality->count) . '件' : '公表なし' }}</dd></div>
    @if($municipality->outside_amount)
      <div><dt>区域外から</dt><dd>{{ number_format($municipality->outside_amount) }}円（{{ number_format($municipality->outside_count) }}件）</dd></div>
    @endif
    @if($municipality->prefecture_rank)
      <div><dt>{{ $municipality->area_label }}順位</dt><dd>{{ $municipality->prefecture_rank }}位</dd></div>
    @endif
    @if($municipality->cost_total)
      <div><dt>募集にかかった費用</dt><dd>{{ number_format($municipality->cost_total) }}円（受入額の{{ $municipality->cost_ratio }}％）</dd></div>
    @endif
    @if(! is_null($municipality->reward_provided))
      <div><dt>返礼品</dt><dd>{{ $municipality->reward_provided ? '送付している' : '送付していない' }}</dd></div>
    @endif
    @if($municipality->onestop_online)
      <div><dt>ワンストップ特例のオンライン申請</dt><dd>{{ $municipality->onestop_online }}</dd></div>
    @endif
  </dl>
  @if($previous && $latest && $previous['amount'])
    <p class="text-muted small">
      前年度（{{ $previous['year'] }}）の{{ number_format($previous['amount']) }}円と比べると、
      @if($latest['amount'] >= $previous['amount'])
        約{{ number_format(round(($latest['amount'] / $previous['amount'] - 1) * 100, 1), 1) }}％増えました。
      @else
        約{{ number_format(round((1 - $latest['amount'] / $previous['amount']) * 100, 1), 1) }}％減りました。
      @endif
    </p>
  @endif
</section>

@if($municipality->field_breakdown)
<section class="content-section border-top">
  <h2 class="h4 fw-bold mb-3">寄附の使い道</h2>
  <p class="text-muted small">{{ $name }}が申告した、使い道の分野ごとの受入額です。</p>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>分野</th><th class="text-end">受入額</th><th class="text-end">件数</th></tr></thead>
      <tbody>
      @foreach($municipality->field_breakdown as $field)
        <tr>
          <td>{{ $field['field'] }}</td>
          <td class="text-end">{{ $field['amount'] ? number_format($field['amount']) . '円' : '—' }}</td>
          <td class="text-end">{{ $field['count'] ? number_format($field['count']) . '件' : '—' }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

@if($municipality->projects)
<section class="content-section border-top">
  <h2 class="h4 fw-bold mb-3">寄附で進めている事業</h2>
  <p class="text-muted small">{{ $name }}が「特に力を入れている」として申告した事業です。</p>
  @foreach($municipality->projects as $project)
    <article class="review-card">
      <h3 class="h6 fw-bold mb-1">{{ $project['name'] }}</h3>
      <p class="text-muted small mb-1">
        @if($project['field'])分野：{{ $project['field'] }}@endif
        @if($project['reward'])／返礼品：{{ $project['reward'] }}@endif
        @if($project['actual'])／受入額：{{ number_format($project['actual']) }}円@endif
        @if($project['target'])／目標額：{{ number_format($project['target']) }}円@endif
      </p>
      @if($project['summary'])<p>{{ $project['summary'] }}</p>@endif
    </article>
  @endforeach
  @if($municipality->cf_projects)
    <p class="text-muted small mt-3">
      クラウドファンディング型のふるさと納税は{{ number_format($municipality->cf_projects) }}件募集し、
      合計{{ number_format($municipality->cf_amount) }}円を受け入れています。
    </p>
  @endif
</section>
@endif

@if($municipality->donor_relation)
<section class="content-section border-top">
  <h2 class="h4 fw-bold mb-3">寄附したあとの関わり方</h2>
  <p>{{ $municipality->donor_relation }}</p>
  <p class="text-muted small">
    受入額の公表：{{ $municipality->publish_amount ? 'あり' : 'なし' }}／
    使い道の公表：{{ $municipality->publish_usage ? 'あり' : 'なし' }}／
    事業の進捗報告：{{ $municipality->publish_progress ? 'あり' : 'なし' }}
  </p>
</section>
@endif

@if($series->count() > 1)
<section class="content-section border-top">
  <h2 class="h4 fw-bold mb-3">受入額の推移</h2>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>年度</th><th class="text-end">受入額</th><th class="text-end">件数</th></tr></thead>
      <tbody>
      @foreach($series->reverse() as $row)
        <tr>
          <td>{{ $row['year'] }}</td>
          <td class="text-end">{{ number_format($row['amount']) }}円</td>
          <td class="text-end">{{ $row['count'] !== null ? number_format($row['count']) . '件' : '—' }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  <p class="text-muted small">平成20年度〜{{ $meta['fiscalYear'] }}。金額は千円単位で公表された値を円に直しています。</p>
</section>
@endif

@if($items->isNotEmpty())
<section class="content-section border-top">
  <div class="section-heading">
    <div><h2 class="h4 fw-bold mb-0">{{ $name }}の返礼品</h2></div>
    <a href="{{ route('furusato.search', ['keyword' => $municipality->city ?: $municipality->prefecture]) }}">もっと見る →</a>
  </div>
  <div class="row row-cols-2 row-cols-md-4 g-3">
    @foreach($items as $item)<div class="col">@include('furusato._item-card', ['item' => $item])</div>@endforeach
  </div>
</section>
@endif

@if($neighbours->isNotEmpty())
<section class="content-section border-top">
  <h2 class="h4 fw-bold mb-3">{{ $municipality->prefecture }}のほかの自治体</h2>
  <div class="prefecture-links">
    @foreach($neighbours as $other)
      <a href="{{ route('municipalities.show', [$municipality->prefecture_slug, $other->code]) }}">
        {{ $other->city }}<small class="ms-1">{{ $other->amount ? number_format(round($other->amount / 100000000, 1), 1) . '億円' : '' }}</small>
      </a>
    @endforeach
  </div>
  <p class="mt-3"><a href="{{ route('municipalities.prefecture', $municipality->prefecture_slug) }}">{{ $municipality->prefecture }}の全自治体を見る →</a></p>
</section>
@endif

<p class="text-muted small">
  出典：<a href="{{ $meta['sourceUrl'] }}" target="_blank" rel="noopener">{{ $meta['sourceLabel'] }}</a>。
  団体コード{{ $municipality->code }}。金額・件数は{{ $meta['fiscalYear'] }}の決算見込として公表された値で、当サイトによる推計は含みません。
</p>
@endsection

@extends('layouts.app')

@section('title', $prefecture . 'のふるさと納税｜市区町村別の受入額と使い道（' . $meta['fiscalYear'] . '） | ' . config('app.name'))
@section('description', $prefecture . 'の' . $cities->count() . '市区町村について、' . $meta['fiscalYear'] . 'のふるさと納税受入額・件数と、寄附の使い道を総務省の公表値でまとめました。')

@push('structured-data')
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => '自治体別の受入額', 'item' => route('municipalities.index')],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $prefecture, 'item' => route('municipalities.prefecture', $prefectureSlug)],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@php($areaLabel = \App\Models\Municipality::areaLabel($prefecture))

@section('content')
<nav aria-label="パンくず" class="breadcrumb small mb-3">
  <a href="{{ route('furusato.index') }}">トップ</a><span class="mx-2 text-muted">/</span>
  <a href="{{ route('municipalities.index') }}">自治体別の受入額</a><span class="mx-2 text-muted">/</span>
  <span class="text-muted">{{ $prefecture }}</span>
</nav>

<div class="section-heading">
  <div>
    <span class="eyebrow">{{ $meta['fiscalYear'] }}の実績</span>
    <h1>{{ $prefecture }}のふるさと納税</h1>
  </div>
  <a href="{{ route('furusato.search', ['prefecture' => $prefecture]) }}">{{ $prefecture }}の返礼品を見る →</a>
</div>

<p class="text-muted">
  {{ $prefecture }}の{{ $cities->count() }}市区町村が{{ $meta['fiscalYear'] }}に受け入れたふるさと納税は、
  合計{{ number_format(round($totalAmount / 100000000, 1), 1) }}億円・{{ number_format($totalCount) }}件でした。
  自治体名をたどると、受入額の推移や寄附の使い道まで確認できます。
</p>

@if($totalDeductionAmount)
<p class="text-muted">
  反対に、{{ $prefecture }}に住む人が他の自治体へ寄附して受けた住民税の控除は、
  {{ $meta['taxYear'] }}分で合計{{ \App\Models\Municipality::formatYen($totalDeductionAmount) }}でした
  （寄附した額は{{ \App\Models\Municipality::formatYen($totalDeductionDonation) }}）。
</p>
@endif

<section class="content-section">
  <div class="section-heading"><div><span class="eyebrow">CITY</span><h2>市区町村別の受入額</h2></div></div>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>{{ $areaLabel }}順位</th><th>自治体</th><th class="text-end">受入額</th><th class="text-end">件数</th><th class="text-end d-none d-md-table-cell">1件あたり</th></tr></thead>
      <tbody>
      @foreach($cities as $city)
        <tr>
          <td class="text-muted">{{ $city->prefecture_rank }}</td>
          <td><a href="{{ route('municipalities.show', [$prefectureSlug, $city->code]) }}" class="fw-bold text-decoration-none">{{ $city->city }}</a></td>
          <td class="text-end">{{ $city->amount ? number_format($city->amount) . '円' : '—' }}</td>
          <td class="text-end">{{ $city->count ? number_format($city->count) . '件' : '—' }}</td>
          <td class="text-end d-none d-md-table-cell">{{ $city->average_per_donation ? number_format($city->average_per_donation) . '円' : '—' }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  <p class="text-muted small">「1件あたり」は、公表された受入額を受入件数で割った金額です。</p>
</section>

@if($fields->isNotEmpty())
<section class="content-section border-top">
  <div class="section-heading"><div><span class="eyebrow">USE</span><h2>{{ $areaLabel }}で寄附が使われている分野</h2></div></div>
  <p class="text-muted small">使い道を分野で選べる自治体が申告した、分野ごとの受入額を{{ $prefecture }}の全体で合計したものです。</p>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>分野</th><th class="text-end">受入額の合計</th><th class="text-end">申告した自治体数</th></tr></thead>
      <tbody>
      @foreach($fields as $field)
        <tr><td>{{ $field['field'] }}</td><td class="text-end">{{ number_format($field['amount']) }}円</td><td class="text-end">{{ $field['cities'] }}</td></tr>
      @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

@if($prefectureRow)
<section class="content-section border-top">
  <div class="section-heading"><div><span class="eyebrow">PREFECTURE</span><h2>{{ $prefecture }}そのものへの寄附</h2></div></div>
  <p class="text-muted">
    市区町村とは別に、{{ $prefecture }}が直接受け入れたふるさと納税は
    {{ $prefectureRow->amount ? number_format($prefectureRow->amount) . '円' : '—' }}
    （{{ $prefectureRow->count ? number_format($prefectureRow->count) . '件' : '—' }}）でした。
  </p>
  <a href="{{ route('municipalities.show', [$prefectureSlug, $prefectureRow->code]) }}">{{ $prefecture }}分の内訳を見る →</a>
</section>
@endif

@if($items->isNotEmpty())
<section class="content-section border-top">
  <div class="section-heading"><div><span class="eyebrow">GIFT</span><h2>{{ $prefecture }}の返礼品</h2></div>
    <a href="{{ route('furusato.search', ['prefecture' => $prefecture]) }}">すべて見る →</a></div>
  <div class="row row-cols-2 row-cols-md-4 g-3">
    @foreach($items as $item)<div class="col">@include('furusato._item-card', ['item' => $item])</div>@endforeach
  </div>
</section>
@endif

<p class="text-muted small">
  出典：<a href="{{ $meta['sourceUrl'] }}" target="_blank" rel="noopener">{{ $meta['sourceLabel'] }}</a>、
  {{ $meta['deductionSourceLabel'] }}。
</p>
@endsection

@extends('layouts.app')

@section('title', '自治体別ふるさと納税の受入額一覧（' . $meta['fiscalYear'] . '） | ' . config('app.name'))
@section('description', $meta['fiscalYear'] . 'に全国' . number_format($cityCount) . '市区町村が受け入れたふるさと納税の金額と件数を、総務省の公表値で一覧にしました。都道府県から寄附先の自治体を探せます。')

@push('structured-data')
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => '自治体別の受入額', 'item' => route('municipalities.index')],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')
<nav aria-label="パンくず" class="breadcrumb small mb-3">
  <a href="{{ route('furusato.index') }}">トップ</a><span class="mx-2 text-muted">/</span><span class="text-muted">自治体別の受入額</span>
</nav>

<div class="section-heading">
  <div>
    <span class="eyebrow">{{ $meta['fiscalYear'] }}の実績</span>
    <h1>自治体別ふるさと納税の受入額</h1>
  </div>
</div>

<p class="text-muted">
  総務省が毎年公表している調査から、{{ $meta['fiscalYear'] }}に各自治体が受け入れたふるさと納税の金額・件数と、
  集まった寄附の使い道をまとめています。返礼品の内容だけでなく、寄附がその地域で何に使われているかも確かめてから選べます。
</p>

<section class="catalog-stats" aria-label="全国の実績">
  <div><strong>{{ number_format(round($totalAmount / 100000000)) }}億円</strong><span>市区町村の受入額合計</span></div>
  <div><strong>{{ number_format(round($totalCount / 10000)) }}万件</strong><span>受入件数</span></div>
  <div><strong>{{ number_format($cityCount) }}</strong><span>掲載市区町村</span></div>
  <div><strong>47</strong><span>都道府県</span></div>
</section>

<section class="content-section">
  <div class="section-heading"><div><span class="eyebrow">AREA</span><h2>都道府県から探す</h2></div></div>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>都道府県</th><th class="text-end">受入額</th><th class="text-end">件数</th><th class="text-end d-none d-md-table-cell">市区町村</th><th class="d-none d-md-table-cell">最も多い自治体</th></tr></thead>
      <tbody>
      @foreach($prefectures as $row)
        <tr>
          <td><a href="{{ route('municipalities.prefecture', $row['slug']) }}" class="fw-bold text-decoration-none">{{ $row['prefecture'] }}</a></td>
          <td class="text-end">{{ number_format(round($row['amount'] / 100000000, 1), 1) }}億円</td>
          <td class="text-end">{{ number_format($row['count']) }}件</td>
          <td class="text-end d-none d-md-table-cell">{{ $row['cities'] }}</td>
          <td class="d-none d-md-table-cell small">
            <a href="{{ route('municipalities.show', [$row['slug'], $row['top']->code]) }}">{{ $row['top']->city }}</a>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</section>

<section class="content-section border-top">
  <div class="section-heading"><div><span class="eyebrow">RANKING</span><h2>受入額の多い自治体 50</h2></div></div>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>順位</th><th>自治体</th><th class="text-end">受入額</th><th class="text-end">件数</th></tr></thead>
      <tbody>
      @foreach($ranking as $city)
        <tr>
          <td class="text-muted">{{ $city->national_rank }}</td>
          <td>
            <a href="{{ route('municipalities.show', [$city->prefecture_slug, $city->code]) }}" class="fw-bold text-decoration-none">{{ $city->city }}</a>
            <small class="text-muted d-block">{{ $city->prefecture }}</small>
          </td>
          <td class="text-end">{{ number_format(round($city->amount / 100000000, 1), 1) }}億円</td>
          <td class="text-end">{{ number_format($city->count) }}件</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</section>

<p class="text-muted small">
  出典：<a href="{{ $meta['sourceUrl'] }}" target="_blank" rel="noopener">{{ $meta['sourceLabel'] }}</a>。
  金額・件数は{{ $meta['fiscalYear'] }}の決算見込として公表された値です。
</p>
@endsection

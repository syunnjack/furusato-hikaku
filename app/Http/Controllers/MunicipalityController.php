<?php

namespace App\Http\Controllers;

use App\Models\FurusatoItem;
use App\Models\Municipality;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MunicipalityController extends Controller
{
    /** 全国 → 都道府県 → 自治体、と寄附先をたどるページ群。 */
    public function index()
    {
        // 一覧では巨大なJSON列（推移・使い道）まで読み込む必要がない。
        $cities = Municipality::query()
            ->cities()
            ->select(['code', 'prefecture', 'city', 'amount', 'count', 'national_rank'])
            ->get();

        $prefectures = $cities->groupBy('prefecture')
            ->map(fn (Collection $group, string $prefecture) => [
                'prefecture' => $prefecture,
                'slug' => Municipality::slugFor($prefecture),
                'amount' => (int) $group->sum('amount'),
                'count' => (int) $group->sum('count'),
                'cities' => $group->count(),
                'top' => $group->sortByDesc('amount')->first(),
            ])
            ->sortByDesc('amount')
            ->values();

        return view('municipalities.index', [
            'prefectures' => $prefectures,
            'totalAmount' => (int) $cities->sum('amount'),
            'totalCount' => (int) $cities->sum('count'),
            'cityCount' => $cities->count(),
            'ranking' => $cities->sortByDesc('amount')->take(50)->values(),
            'meta' => $this->meta(),
        ]);
    }

    public function prefecture(string $prefectureSlug)
    {
        $prefecture = Municipality::prefectureForSlug($prefectureSlug);

        if ($prefecture === null) {
            throw new NotFoundHttpException;
        }

        $cities = Municipality::query()
            ->where('prefecture', $prefecture)
            ->cities()
            ->orderByDesc('amount')
            ->get();

        if ($cities->isEmpty()) {
            throw new NotFoundHttpException;
        }

        $prefectureRow = Municipality::query()
            ->where('prefecture', $prefecture)
            ->whereNull('city')
            ->first();

        return view('municipalities.prefecture', [
            'prefecture' => $prefecture,
            'prefectureSlug' => $prefectureSlug,
            'prefectureRow' => $prefectureRow,
            'cities' => $cities,
            'totalAmount' => (int) $cities->sum('amount'),
            'totalCount' => (int) $cities->sum('count'),
            'fields' => $this->fieldTotals($cities),
            'items' => FurusatoItem::where('prefecture', $prefecture)
                ->orderByDesc('review_count')
                ->limit(8)
                ->get(),
            'meta' => $this->meta(),
        ]);
    }

    public function show(string $prefectureSlug, string $code)
    {
        $municipality = Municipality::where('code', $code)->firstOrFail();

        if (Municipality::slugFor($municipality->prefecture) !== $prefectureSlug) {
            return redirect()->route('municipalities.show', [
                Municipality::slugFor($municipality->prefecture),
                $municipality->code,
            ], 301);
        }

        $neighbours = Municipality::query()
            ->where('prefecture', $municipality->prefecture)
            ->where('code', '!=', $municipality->code)
            ->cities()
            ->select(['code', 'prefecture', 'city', 'amount', 'prefecture_rank'])
            ->orderByDesc('amount')
            ->limit(12)
            ->get();

        // カタログ側に返礼品があれば結び付ける。まだ同期されていない場合は空になる。
        $items = FurusatoItem::query()
            ->when($municipality->city, fn ($query, $city) => $query->where('municipality', $city))
            ->where('prefecture', $municipality->prefecture)
            ->orderByDesc('review_count')
            ->limit(8)
            ->get();

        return view('municipalities.show', [
            'municipality' => $municipality,
            'neighbours' => $neighbours,
            'items' => $items,
            'meta' => $this->meta(),
        ]);
    }

    /** 都道府県ページ用に、使い道の分野を県内で合計する。 */
    private function fieldTotals(Collection $cities): Collection
    {
        return $cities->flatMap(fn (Municipality $city) => $city->field_breakdown ?? [])
            ->groupBy('field')
            ->map(fn (Collection $group, string $field) => [
                'field' => $field,
                'amount' => (int) $group->sum('amount'),
                'cities' => $group->count(),
            ])
            ->sortByDesc('amount')
            ->take(10)
            ->values();
    }

    /** データの出典。テンプレート側で必ず表示する。 */
    private function meta(): array
    {
        return [
            'fiscalYear' => Municipality::FISCAL_YEAR,
            'sourceLabel' => Municipality::SOURCE_LABEL,
            'sourceUrl' => Municipality::SOURCE_URL,
        ];
    }
}

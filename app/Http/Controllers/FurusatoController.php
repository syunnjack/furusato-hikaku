<?php

namespace App\Http\Controllers;

use App\Models\FurusatoItem;
use App\Models\Review;
use App\Support\RakutenFurusatoSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FurusatoController extends Controller
{
    public function index()
    {
        $catalogCount = FurusatoItem::count();
        $municipalityCount = FurusatoItem::whereNotNull('municipality')->distinct('municipality')->count('municipality');
        $prefectureCount = FurusatoItem::whereNotNull('prefecture')->distinct('prefecture')->count('prefecture');
        $latestSync = FurusatoItem::max('synced_at');

        $featured = FurusatoItem::where('review_count', '>', 0)
            ->orderByDesc('review_count')
            ->limit(12)
            ->get();
        $affordable = FurusatoItem::whereBetween('item_price', [1, 12000])
            ->orderByDesc('review_count')
            ->limit(8)
            ->get();
        $categoryCounts = FurusatoItem::query()
            ->selectRaw('category, COUNT(*) as total')
            ->whereNotNull('category')
            ->groupBy('category')
            ->pluck('total', 'category');
        $popularPrefectures = FurusatoItem::query()
            ->selectRaw('prefecture, COUNT(*) as total')
            ->whereNotNull('prefecture')
            ->groupBy('prefecture')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        return view('furusato.index', [
            'categories' => FurusatoItem::CATEGORIES,
            'catalogCount' => $catalogCount,
            'municipalityCount' => $municipalityCount,
            'prefectureCount' => $prefectureCount,
            'latestSync' => $latestSync,
            'featured' => $featured,
            'affordable' => $affordable,
            'categoryCounts' => $categoryCounts,
            'popularPrefectures' => $popularPrefectures,
        ]);
    }

    public function search(Request $request)
    {
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:128'],
            'category' => ['nullable', 'string', 'max:40'],
            'prefecture' => ['nullable', 'string', 'max:10'],
            'min_price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'max_price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'sort' => ['nullable', 'in:popular,rating,price-asc,price-desc,newest'],
        ]);

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $filters['keyword'] = $keyword;

        if ($request->query() === []) {
            return redirect()->route('furusato.index');
        }

        $query = $this->buildCatalogQuery($filters);

        if ($keyword !== '' && (clone $query)->count() === 0) {
            $this->importKeywordResults($keyword);
            $query = $this->buildCatalogQuery($filters);
        }

        $items = $this->applySort($query, $filters['sort'] ?? 'popular')
            ->paginate(24)
            ->withQueryString();
        $reviews = Review::whereIn('item_id', $items->pluck('item_code'))
            ->latest()
            ->get()
            ->groupBy('item_id');
        $faq = $this->buildFaq($keyword !== '' ? $keyword : '選択した条件', $reviews);

        return view('furusato.results', [
            'items' => $items,
            'keyword' => $keyword,
            'reviews' => $reviews,
            'faq' => $faq,
            'categories' => FurusatoItem::CATEGORIES,
            'prefectures' => FurusatoItem::PREFECTURES,
            'filters' => $filters,
        ]);
    }

    public function show(FurusatoItem $furusatoItem)
    {
        $reviews = Review::where('item_id', $furusatoItem->item_code)->latest()->get();
        $related = FurusatoItem::where('category', $furusatoItem->category)
            ->where('id', '!=', $furusatoItem->getKey())
            ->orderByDesc('review_count')
            ->limit(4)
            ->get();

        return view('furusato.show', [
            'item' => $furusatoItem,
            'reviews' => $reviews,
            'related' => $related,
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function buildCatalogQuery(array $filters): Builder
    {
        return FurusatoItem::query()
            ->when(filled($filters['keyword'] ?? null), function (Builder $query) use ($filters) {
                $keyword = '%'.trim((string) $filters['keyword']).'%';
                $query->where(function (Builder $nested) use ($keyword) {
                    $nested->where('item_name', 'like', $keyword)
                        ->orWhere('catchcopy', 'like', $keyword)
                        ->orWhere('shop_name', 'like', $keyword)
                        ->orWhere('municipality', 'like', $keyword)
                        ->orWhere('prefecture', 'like', $keyword);
                });
            })
            ->when(in_array($filters['category'] ?? null, FurusatoItem::CATEGORIES, true),
                fn (Builder $query) => $query->where('category', $filters['category']))
            ->when(in_array($filters['prefecture'] ?? null, FurusatoItem::PREFECTURES, true),
                fn (Builder $query) => $query->where('prefecture', $filters['prefecture']))
            ->when(filled($filters['min_price'] ?? null),
                fn (Builder $query) => $query->where('item_price', '>=', (int) $filters['min_price']))
            ->when(filled($filters['max_price'] ?? null),
                fn (Builder $query) => $query->where('item_price', '<=', (int) $filters['max_price']));
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'rating' => $query->orderByDesc('review_average')->orderByDesc('review_count'),
            'price-asc' => $query->orderBy('item_price')->orderByDesc('review_count'),
            'price-desc' => $query->orderByDesc('item_price')->orderByDesc('review_count'),
            'newest' => $query->orderByDesc('synced_at')->orderByDesc('id'),
            default => $query->orderByDesc('review_count')->orderByDesc('review_average'),
        };
    }

    private function importKeywordResults(string $keyword): void
    {
        $results = Cache::remember(
            'furusato-api-search:'.hash('sha256', $keyword),
            now()->addHour(),
            fn () => RakutenFurusatoSearch::search($keyword, sort: '-reviewCount')
        );
        $rows = collect($results)
            ->map(fn (array $item) => FurusatoItem::normalizeRakutenItem($item))
            ->filter(fn (array $item) => $item['item_code'] !== '' && $item['item_url'] !== '')
            ->values()
            ->all();

        if ($rows !== []) {
            FurusatoItem::upsert($rows, ['item_code'], [
                'item_name', 'item_price', 'item_url', 'affiliate_url', 'image_url',
                'shop_name', 'shop_code', 'category', 'prefecture', 'municipality',
                'catchcopy', 'review_count', 'review_average', 'synced_at', 'updated_at',
            ]);
        }
    }

    private function buildFaq(string $keyword, Collection $reviews): array
    {
        $topRated = $reviews->filter(fn ($group) => $group->count() > 0)
            ->sortByDesc(fn ($group) => $group->avg('rating'))
            ->first();
        $topRatedTitle = $topRated ? $topRated->first()->title : null;

        $faq = [
            [
                'question' => "「{$keyword}」の返礼品はどこで申し込めますか？",
                'answer' => '返礼品詳細の「楽天市場で申し込む」リンクから、楽天市場のふるさと納税ページで申し込みができます。',
            ],
            [
                'question' => "「{$keyword}」の返礼品はどう並んでいますか？",
                'answer' => '初期表示は楽天市場のレビュー件数と評価を参考にした人気順です。寄付額や評価順にも並べ替えられます。',
            ],
        ];

        if ($topRatedTitle) {
            $faq[] = [
                'question' => "「{$keyword}」で口コミ評価の高い返礼品は？",
                'answer' => "サイト内口コミでは「{$topRatedTitle}」が高い評価を得ています。楽天市場のレビューもあわせてご確認ください。",
            ];
        }

        return $faq;
    }
}

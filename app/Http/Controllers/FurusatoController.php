<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FurusatoController extends Controller
{
    private const CATEGORIES = [
        '肉', '海鮮・魚介', '米・パン', 'フルーツ', 'お酒',
        'スイーツ', '日用品', '家電', '旅行・体験クーポン',
    ];

    public function index()
    {
        return view('furusato.index', ['categories' => self::CATEGORIES]);
    }

    public function search(Request $request)
    {
        $keyword = trim($request->input('keyword', ''));

        if ($keyword === '') {
            return redirect()->route('furusato.index');
        }

        $results = Cache::remember("furusato-search:{$keyword}", now()->addHour(), function () use ($keyword) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'Referer' => config('app.url'),
                        'Origin' => config('app.url'),
                    ])
                    ->get('https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20260701', [
                        'format' => 'json',
                        'formatVersion' => 2,
                        'applicationId' => env('RAKUTEN_APP_ID'),
                        'accessKey' => env('RAKUTEN_ACCESS_KEY'),
                        'affiliateId' => env('RAKUTEN_AFFILIATE_ID'),
                        'keyword' => $keyword,
                        // ふるさと納税ジャンル(genreId=100227)で絞り込む。
                        // キーワードに「ふるさと納税」を含めると0件になるため使用しない。
                        'genreId' => 100227,
                        'hits' => 30,
                        'sort' => 'standard',
                    ]);
            } catch (ConnectionException) {
                return [];
            }

            return $response->successful() ? ($response->json('Items') ?? []) : [];
        });

        $itemIds = collect($results)
            ->map(fn ($item) => $item['itemCode'] ?? null)
            ->filter()
            ->values();

        $reviews = Review::whereIn('item_id', $itemIds)
            ->latest()
            ->get()
            ->groupBy('item_id');

        $faq = $this->buildFaq($keyword, $reviews);

        return view('furusato.results', compact('results', 'keyword', 'reviews', 'faq'));
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
                'answer' => '各返礼品ページの「楽天市場で見る」リンクから、楽天市場のふるさと納税ページで申し込みができます。',
            ],
            [
                'question' => "「{$keyword}」の返礼品の口コミは見られますか？",
                'answer' => '各返礼品ページで、実際に選んだ人が投稿した口コミ（評価とコメント）を確認できます。口コミはどなたでもログイン不要で投稿できます。',
            ],
        ];

        if ($topRatedTitle) {
            $faq[] = [
                'question' => "「{$keyword}」でおすすめの返礼品は？",
                'answer' => "口コミ評価をもとにすると、「{$topRatedTitle}」が現在最も高い評価を得ています。ただし好みは人それぞれのため、他の返礼品の口コミもあわせてご確認ください。",
            ];
        }

        return $faq;
    }
}

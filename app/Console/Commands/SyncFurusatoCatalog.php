<?php

namespace App\Console\Commands;

use App\Models\FurusatoItem;
use App\Support\RakutenFurusatoSearch;
use Illuminate\Console\Command;

class SyncFurusatoCatalog extends Command
{
    protected $signature = 'furusato:sync-catalog
        {--pages=5 : 検索語1つあたりの楽天APIページ数（1ページ30件、最大100）}
        {--delay=800 : APIリクエスト間隔（ミリ秒）}';

    /**
     * 検索語を分けずに取ると、上位が楽天トラベルのクーポンで埋まり、
     * 肉や海鮮のカテゴリがほとんど空になる（150件中84件が旅行・体験だった）。
     * カテゴリごとの語で引いて、まんべんなく集める。
     *
     * @var array<int, string>
     */
    private const KEYWORDS = [
        '', '牛肉', '豚肉', '鶏肉', '海鮮', 'ほたて', 'いくら', 'かに', 'うなぎ',
        '米', 'パン', 'フルーツ', 'いちご', 'メロン', 'ぶどう', 'りんご', 'みかん',
        '野菜', '日本酒', '焼酎', 'ビール', 'ワイン', 'スイーツ', 'アイス',
        'ティッシュ', 'トイレットペーパー', 'タオル', '家電', '旅行', '工芸品',
    ];

    protected $description = '楽天APIから実在するふるさと納税返礼品を取得し、検索カタログを更新する';

    public function handle(): int
    {
        $pages = max(1, min(100, (int) $this->option('pages')));
        $delay = max(250, (int) $this->option('delay'));
        $synced = 0;

        $keywords = self::KEYWORDS;
        $this->info('ふるさと納税カタログを同期します（検索語'.count($keywords)."語 × 最大{$pages}ページ）。");

        foreach ($keywords as $keyword) {
            $label = $keyword === '' ? '（指定なし）' : $keyword;
            $this->line("── {$label}");

            for ($page = 1; $page <= $pages; $page++) {
                $result = $this->fetchPageWithRetry($page, $keyword);

                if ($result['error']) {
                    // 1つの語で失敗しても、他の語の取得は続ける。
                    $this->warn("  {$label} ページ{$page}: {$result['error']}");
                    break;
                }

                $rows = collect($result['items'])
                    ->map(fn (array $item) => FurusatoItem::normalizeRakutenItem($item, $page))
                    ->filter(fn (array $item) => $item['item_code'] !== '' && $item['item_url'] !== '')
                    // 検索語で絞ってはいるが、商品名に「ふるさと納税」が無いものは
                    // 返礼品でない可能性があるため取り込まない。
                    ->filter(fn (array $item) => str_contains($item['item_name'], RakutenFurusatoSearch::REQUIRED_KEYWORD))
                    ->values()
                    ->all();

                if ($rows === []) {
                    break;
                }

                FurusatoItem::upsert(
                    $rows,
                    ['item_code'],
                    [
                        'item_name', 'item_price', 'item_url', 'affiliate_url', 'image_url',
                        'shop_name', 'shop_code', 'category', 'prefecture', 'municipality',
                        'catchcopy', 'review_count', 'review_average', 'catalog_page',
                        'synced_at', 'updated_at',
                    ]
                );

                $synced += count($rows);
                usleep($delay * 1000);
            }
        }

        $total = FurusatoItem::count();
        $this->info('同期完了: '.number_format($synced).'件取得 / '.number_format($total).'件掲載');

        return self::SUCCESS;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, count: int, page: int, pageCount: int, error: ?string}
     */
    private function fetchPageWithRetry(int $page, string $keyword = ''): array
    {
        $result = [];

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $result = RakutenFurusatoSearch::searchPage(
                keyword: $keyword === '' ? null : $keyword,
                page: $page,
                sort: 'standard',
            );

            if ($result['error'] === null) {
                return $result;
            }

            if ($attempt < 3) {
                sleep($attempt * 2);
            }
        }

        return $result;
    }
}

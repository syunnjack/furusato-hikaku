<?php

namespace App\Console\Commands;

use App\Models\FurusatoItem;
use App\Support\RakutenFurusatoSearch;
use Illuminate\Console\Command;

class SyncFurusatoCatalog extends Command
{
    protected $signature = 'furusato:sync-catalog
        {--pages=50 : 同期する楽天APIのページ数（1ページ30件、最大100）}
        {--delay=800 : APIリクエスト間隔（ミリ秒）}';

    protected $description = '楽天APIから実在するふるさと納税返礼品を取得し、検索カタログを更新する';

    public function handle(): int
    {
        $pages = max(1, min(100, (int) $this->option('pages')));
        $delay = max(250, (int) $this->option('delay'));
        $synced = 0;

        $this->info("ふるさと納税カタログを最大{$pages}ページ（".number_format($pages * 30).'件）同期します。');

        for ($page = 1; $page <= $pages; $page++) {
            $result = $this->fetchPageWithRetry($page);

            if ($result['error']) {
                $this->error("ページ{$page}: {$result['error']}");

                return self::FAILURE;
            }

            $rows = collect($result['items'])
                ->map(fn (array $item) => FurusatoItem::normalizeRakutenItem($item, $page))
                ->filter(fn (array $item) => $item['item_code'] !== '' && $item['item_url'] !== '')
                ->values()
                ->all();

            if ($rows === []) {
                $this->warn("ページ{$page}に商品がないため同期を終了します。");
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
            $this->line("ページ {$page}/{$pages}: ".count($rows).'件');

            if ($page < $pages) {
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
    private function fetchPageWithRetry(int $page): array
    {
        $result = [];

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $result = RakutenFurusatoSearch::searchPage(page: $page, sort: 'standard');

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

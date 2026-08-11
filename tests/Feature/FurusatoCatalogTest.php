<?php

namespace Tests\Feature;

use App\Models\FurusatoItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FurusatoCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_home_search_filters_and_detail_use_persisted_catalog(): void
    {
        $item = FurusatoItem::create($this->itemAttributes());

        $this->get('/')->assertOk()->assertSee('北海道産 ホタテ 1kg');
        $this->get('/search?sort=popular')->assertOk()->assertSee('北海道産 ホタテ 1kg');
        $this->get('/search?category='.urlencode('海鮮・魚介').'&prefecture='.urlencode('北海道'))
            ->assertOk()
            ->assertSee('北海道産 ホタテ 1kg');
        $this->get('/search?category='.urlencode('肉'))
            ->assertOk()
            ->assertDontSee('北海道産 ホタテ 1kg');
        $this->get(route('furusato.show', $item))
            ->assertOk()
            ->assertSee('楽天市場で最新情報を確認・申し込む');
    }

    public function test_sync_command_imports_real_api_shape(): void
    {
        config()->set('services.rakuten.app_id', 'test-app');
        config()->set('services.rakuten.access_key', 'test-key');
        Http::fake([
            'openapi.rakuten.co.jp/*' => Http::response([
                'Items' => [[
                    'itemCode' => 'shop:api-item',
                    'itemName' => '北海道別海町 ホタテ 1kg',
                    'itemPrice' => 12000,
                    'itemUrl' => 'https://example.com/item',
                    'affiliateUrl' => 'https://example.com/affiliate',
                    'mediumImageUrls' => [['imageUrl' => 'https://example.com/image.jpg']],
                    'shopName' => '北海道別海町ふるさと納税',
                    'shopCode' => 'shop',
                    'catchcopy' => '大粒ほたて',
                    'reviewCount' => 321,
                    'reviewAverage' => 4.8,
                ]],
                'count' => 1,
                'page' => 1,
                'pageCount' => 1,
            ], 200),
        ]);

        $this->artisan('furusato:sync-catalog', ['--pages' => 1, '--delay' => 250])
            ->assertSuccessful();

        $this->assertDatabaseHas('furusato_items', [
            'item_code' => 'shop:api-item',
            'category' => '海鮮・魚介',
            'prefecture' => '北海道',
            'item_price' => 12000,
        ]);
    }

    /** @return array<string, mixed> */
    private function itemAttributes(): array
    {
        return [
            'item_code' => 'shop:hotate',
            'item_name' => '北海道産 ホタテ 1kg',
            'item_price' => 12000,
            'item_url' => 'https://example.com/item',
            'affiliate_url' => 'https://example.com/affiliate',
            'image_url' => 'https://example.com/image.jpg',
            'shop_name' => '北海道紋別市',
            'shop_code' => 'shop',
            'category' => '海鮮・魚介',
            'prefecture' => '北海道',
            'municipality' => '紋別市',
            'catchcopy' => '大粒で食べ応えのあるホタテ',
            'review_count' => 100,
            'review_average' => 4.8,
            'catalog_page' => 1,
            'synced_at' => now(),
        ];
    }
}

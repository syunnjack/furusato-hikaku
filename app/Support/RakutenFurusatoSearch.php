<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RakutenFurusatoSearch
{
    /** 返礼品以外を拾わないための、必ず付ける検索語。 */
    public const REQUIRED_KEYWORD = 'ふるさと納税';

    /**
     * 楽天APIは Referer を見ており、localhost や空だと 503 を返す。
     * APP_URL が本番のURLになっていない環境でも動くよう、ここで補う。
     */
    private const SITE_URL = 'https://furusato-hikaku.net';

    private static function siteUrl(): string
    {
        $url = (string) config('app.url');

        return str_starts_with($url, 'https://') ? $url : self::SITE_URL;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $keyword, int $page = 1, string $sort = 'standard'): array
    {
        return self::searchPage($keyword, $page, $sort)['items'];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, count: int, page: int, pageCount: int, error: ?string}
     */
    public static function searchPage(?string $keyword = null, int $page = 1, string $sort = 'standard'): array
    {
        $empty = ['items' => [], 'count' => 0, 'page' => $page, 'pageCount' => 0, 'error' => null];
        $appId = (string) config('services.rakuten.app_id');
        $accessKey = (string) config('services.rakuten.access_key');

        if ($appId === '' || $accessKey === '') {
            $missing = implode('と', array_filter([
                $appId === '' ? 'RAKUTEN_APP_ID' : null,
                $accessKey === '' ? 'RAKUTEN_ACCESS_KEY' : null,
            ]));

            return [...$empty, 'error' => "楽天APIの認証情報が設定されていません（{$missing} が空）。"];
        }

        $params = [
            'format' => 'json',
            'formatVersion' => 2,
            'applicationId' => $appId,
            'accessKey' => $accessKey,
            'affiliateId' => config('services.rakuten.affiliate_id'),
            'hits' => 30,
            'page' => max(1, min(100, $page)),
            'sort' => in_array($sort, ['standard', '-reviewCount', '-reviewAverage', '+itemPrice', '-itemPrice', '-updateTimestamp'], true)
                ? $sort
                : 'standard',
            'imageFlag' => 1,
            'availability' => 1,
        ];

        // 「ふるさと納税」を必ず条件に入れる。
        // ジャンル（食品 genreId=100227）だけで絞っていたときは、返礼品でない
        // 通常の商品が9割を占めていた（30件中3件しか返礼品でなかった）。
        // 楽天のキーワードは空白区切りのAND検索なので、利用者の語と並べて渡す。
        $params['keyword'] = filled($keyword)
            ? self::REQUIRED_KEYWORD.' '.trim((string) $keyword)
            : self::REQUIRED_KEYWORD;

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Referer' => self::siteUrl(),
                    'Origin' => self::siteUrl(),
                ])
                ->get('https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20260701', $params);
        } catch (ConnectionException) {
            return [...$empty, 'error' => '楽天APIに接続できませんでした。'];
        }

        if (! $response->successful()) {
            $error = $response->json('error_description') ?: '楽天APIでエラーが発生しました。';
            Log::warning('Rakuten item search failed', ['status' => $response->status(), 'error' => $error]);

            return [...$empty, 'error' => "{$response->status()}: {$error}"];
        }

        return [
            'items' => $response->json('Items') ?? [],
            'count' => (int) ($response->json('count') ?? 0),
            'page' => (int) ($response->json('page') ?? $page),
            'pageCount' => (int) ($response->json('pageCount') ?? 0),
            'error' => null,
        ];
    }
}

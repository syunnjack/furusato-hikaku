<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RakutenFurusatoSearch
{
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
            return [...$empty, 'error' => '楽天APIの認証情報が設定されていません。'];
        }

        $params = [
            'format' => 'json',
            'formatVersion' => 2,
            'applicationId' => $appId,
            'accessKey' => $accessKey,
            'affiliateId' => config('services.rakuten.affiliate_id'),
            'genreId' => 100227,
            'hits' => 30,
            'page' => max(1, min(100, $page)),
            'sort' => in_array($sort, ['standard', '-reviewCount', '-reviewAverage', '+itemPrice', '-itemPrice', '-updateTimestamp'], true)
                ? $sort
                : 'standard',
            'imageFlag' => 1,
            'availability' => 1,
        ];

        if (filled($keyword)) {
            $params['keyword'] = trim((string) $keyword);
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Referer' => config('app.url'),
                    'Origin' => config('app.url'),
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

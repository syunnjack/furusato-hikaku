<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class RakutenFurusatoSearch
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $keyword): array
    {
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
    }
}

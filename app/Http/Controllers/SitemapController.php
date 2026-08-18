<?php

namespace App\Http\Controllers;

use App\Models\FurusatoItem;
use App\Models\Municipality;

class SitemapController extends Controller
{
    public function index()
    {
        $urls = collect([
            ['loc' => route('furusato.index'), 'priority' => '1.0'],
            ['loc' => route('about'), 'priority' => '0.3'],
            ['loc' => route('municipalities.index'), 'priority' => '0.8'],
        ])->merge(
            // 都道府県ページ（47件）
            collect(Municipality::PREFECTURE_SLUGS)->map(fn (string $slug) => [
                'loc' => route('municipalities.prefecture', $slug),
                'priority' => '0.7',
            ])->values()
        )->merge(
            // 自治体ページ。総務省の公表値なので内容は年1回しか変わらない。
            Municipality::query()
                ->select(['code', 'prefecture', 'city', 'updated_at'])
                ->get()
                ->map(fn (Municipality $municipality) => [
                    'loc' => route('municipalities.show', [$municipality->prefecture_slug, $municipality->code]),
                    'priority' => '0.6',
                    'lastmod' => $municipality->updated_at?->toAtomString(),
                ])
        )->merge(
            collect(FurusatoItem::CATEGORIES)->map(fn ($category) => [
                'loc' => route('furusato.search', ['category' => $category]),
                'priority' => '0.8',
            ])
        )->merge(
            FurusatoItem::query()
                ->select(['id', 'updated_at'])
                ->latest('updated_at')
                ->limit(40000)
                ->get()
                ->map(fn (FurusatoItem $item) => [
                    'loc' => route('furusato.show', $item),
                    'priority' => '0.6',
                    'lastmod' => $item->updated_at?->toAtomString(),
                ])
        );

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}

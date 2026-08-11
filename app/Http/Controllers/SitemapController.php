<?php

namespace App\Http\Controllers;

use App\Models\FurusatoItem;

class SitemapController extends Controller
{
    public function index()
    {
        $urls = collect([
            ['loc' => route('furusato.index'), 'priority' => '1.0'],
            ['loc' => route('about'), 'priority' => '0.3'],
        ])->merge(
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

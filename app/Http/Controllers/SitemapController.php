<?php

namespace App\Http\Controllers;

class SitemapController extends Controller
{
    private const CATEGORIES = [
        '肉', '海鮮・魚介', '米・パン', 'フルーツ', 'お酒',
        'スイーツ', '日用品', '家電', '旅行・体験クーポン',
    ];

    public function index()
    {
        $urls = collect([
            ['loc' => route('furusato.index'), 'priority' => '1.0'],
            ['loc' => route('about'), 'priority' => '0.3'],
        ])->merge(
            collect(self::CATEGORIES)->map(fn ($category) => [
                'loc' => route('furusato.search', ['keyword' => $category]),
                'priority' => '0.8',
            ])
        );

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}

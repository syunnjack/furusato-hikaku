<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FurusatoItem extends Model
{
    public const CATEGORIES = [
        '肉', '海鮮・魚介', '米・パン', 'フルーツ', '野菜', 'お酒',
        'スイーツ', '日用品', '家電', '旅行・体験', '工芸品', 'その他',
    ];

    public const PREFECTURES = [
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
        '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
        '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
        '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
    ];

    protected $fillable = [
        'item_code', 'item_name', 'item_price', 'item_url', 'affiliate_url',
        'image_url', 'shop_name', 'shop_code', 'category', 'prefecture',
        'municipality', 'catchcopy', 'review_count', 'review_average',
        'catalog_page', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'item_price' => 'integer',
            'review_count' => 'integer',
            'review_average' => 'float',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function normalizeRakutenItem(array $item, ?int $catalogPage = null): array
    {
        $name = trim((string) ($item['itemName'] ?? ''));
        $shopName = trim((string) ($item['shopName'] ?? ''));
        $searchable = $name.' '.$shopName.' '.($item['catchcopy'] ?? '');
        $image = data_get($item, 'mediumImageUrls.0.imageUrl')
            ?? data_get($item, 'mediumImageUrls.0');

        return [
            'item_code' => (string) ($item['itemCode'] ?? ''),
            'item_name' => $name,
            'item_price' => max(0, (int) ($item['itemPrice'] ?? 0)),
            'item_url' => (string) ($item['itemUrl'] ?? ''),
            'affiliate_url' => ($item['affiliateUrl'] ?? null) ?: null,
            'image_url' => is_string($image) ? $image : null,
            'shop_name' => $shopName ?: null,
            'shop_code' => ($item['shopCode'] ?? null) ?: null,
            'category' => self::detectCategory($searchable),
            'prefecture' => self::detectPrefecture($searchable),
            'municipality' => self::detectMunicipality($searchable),
            'catchcopy' => ($item['catchcopy'] ?? null) ?: null,
            'review_count' => max(0, (int) ($item['reviewCount'] ?? 0)),
            'review_average' => max(0, min(5, (float) ($item['reviewAverage'] ?? 0))),
            'catalog_page' => $catalogPage,
            'synced_at' => now(),
        ];
    }

    private static function detectCategory(string $text): string
    {
        // 地名を先に取り除く。残しておくと「山梨県」の梨でフルーツ、
        // 「魚沼産」の魚で海鮮、「久留米市」の米で米・パンに振り分けられる。
        foreach (self::PREFECTURES as $prefecture) {
            $text = str_ireplace([$prefecture, mb_substr($prefecture, 0, mb_strlen($prefecture) - 1)], ' ', $text);
        }

        $text = preg_replace('/[一-龠々ヶヵぁ-んァ-ヶ]{2,15}(?:市|区|町|村)/u', ' ', $text) ?? $text;

        $rules = [
            '肉' => ['牛肉', '豚肉', '鶏肉', 'ハンバーグ', 'ステーキ', '焼肉', 'もつ鍋', 'ソーセージ'],
            '海鮮・魚介' => ['海鮮', '鮮魚', '魚介', '白身魚', '鮭', 'サーモン', 'ほたて', 'ホタテ', 'いくら', '蟹', 'かに', 'うなぎ', '海老', 'えび', '明太子', '干物', 'まぐろ', 'マグロ', 'ぶり', 'ブリ'],
            '米・パン' => ['米', 'こしひかり', 'コシヒカリ', 'パン', '玄米', 'もち麦', '餅'],
            'フルーツ' => ['果物', 'フルーツ', 'いちご', '苺', 'みかん', '白桃', 'メロン', 'ぶどう', 'シャインマスカット', 'りんご', '和梨', '洋梨', 'ラフランス', 'ラ・フランス', '幸水', '豊水', 'マンゴー', 'さくらんぼ', 'キウイ'],
            '野菜' => ['野菜', '玉ねぎ', 'じゃがいも', 'さつまいも', 'トマト', 'とうもろこし', 'きのこ'],
            'お酒' => ['日本酒', '焼酎', 'ビール', 'ワイン', 'ウイスキー', '酒'],
            'スイーツ' => ['スイーツ', '菓子', 'ケーキ', 'アイス', 'ジェラート', 'チョコ', 'プリン', 'クッキー'],
            '日用品' => ['ティッシュ', 'トイレットペーパー', 'タオル', '洗剤', '日用品', 'せっけん', '石鹸'],
            '家電' => ['家電', 'テレビ', 'パソコン', '掃除機', '炊飯器', 'ドライヤー', 'カメラ', '電化製品'],
            '旅行・体験' => ['旅行', '宿泊', 'ホテル', '旅館', '体験', 'クーポン', '利用券', 'チケット', 'ゴルフ'],
            '工芸品' => ['工芸', '陶器', '食器', '包丁', '家具', '木製', '織物', '焼物'],
        ];

        foreach ($rules as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_stripos($text, $keyword) !== false) {
                    return $category;
                }
            }
        }

        return 'その他';
    }

    private static function detectPrefecture(string $text): ?string
    {
        foreach (self::PREFECTURES as $prefecture) {
            if (mb_stripos($text, $prefecture) !== false) {
                return $prefecture;
            }
        }

        return null;
    }

    private static function detectMunicipality(string $text): ?string
    {
        if (preg_match('/([一-龠々ヶヵぁ-んァ-ヶ]{2,15}(?:市|区|町|村))/u', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

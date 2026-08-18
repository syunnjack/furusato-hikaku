<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    protected $fillable = [
        'code', 'prefecture', 'city', 'amount', 'count', 'outside_amount', 'outside_count',
        'cost_total', 'cost_ratio', 'reward_provided', 'use_selectable', 'use_by_project',
        'use_by_field', 'cf_projects', 'cf_amount', 'projects', 'field_breakdown', 'series',
        'publish_amount', 'publish_usage', 'publish_progress', 'donor_relation',
        'onestop_online', 'national_rank', 'prefecture_rank',
    ];

    // 掲載データの出典。scripts/build-municipality-data.py が読む調査と対応させる。
    public const FISCAL_YEAR = '令和7年度';
    public const SOURCE_LABEL = '総務省「ふるさと納税に関する現況調査結果（令和8年度実施）」';
    public const SOURCE_URL = 'https://www.soumu.go.jp/main_sosiki/jichi_zeisei/czaisei/czaisei_seido/furusato/archive/';

    /** 都道府県のURLに使うローマ字。日本語のままだとURLが長くなり共有しづらいため。 */
    public const PREFECTURE_SLUGS = [
        '北海道' => 'hokkaido', '青森県' => 'aomori', '岩手県' => 'iwate', '宮城県' => 'miyagi',
        '秋田県' => 'akita', '山形県' => 'yamagata', '福島県' => 'fukushima', '茨城県' => 'ibaraki',
        '栃木県' => 'tochigi', '群馬県' => 'gunma', '埼玉県' => 'saitama', '千葉県' => 'chiba',
        '東京都' => 'tokyo', '神奈川県' => 'kanagawa', '新潟県' => 'niigata', '富山県' => 'toyama',
        '石川県' => 'ishikawa', '福井県' => 'fukui', '山梨県' => 'yamanashi', '長野県' => 'nagano',
        '岐阜県' => 'gifu', '静岡県' => 'shizuoka', '愛知県' => 'aichi', '三重県' => 'mie',
        '滋賀県' => 'shiga', '京都府' => 'kyoto', '大阪府' => 'osaka', '兵庫県' => 'hyogo',
        '奈良県' => 'nara', '和歌山県' => 'wakayama', '鳥取県' => 'tottori', '島根県' => 'shimane',
        '岡山県' => 'okayama', '広島県' => 'hiroshima', '山口県' => 'yamaguchi', '徳島県' => 'tokushima',
        '香川県' => 'kagawa', '愛媛県' => 'ehime', '高知県' => 'kochi', '福岡県' => 'fukuoka',
        '佐賀県' => 'saga', '長崎県' => 'nagasaki', '熊本県' => 'kumamoto', '大分県' => 'oita',
        '宮崎県' => 'miyazaki', '鹿児島県' => 'kagoshima', '沖縄県' => 'okinawa',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'count' => 'integer',
            'outside_amount' => 'integer',
            'outside_count' => 'integer',
            'cost_total' => 'integer',
            'cost_ratio' => 'float',
            'reward_provided' => 'boolean',
            'use_selectable' => 'boolean',
            'use_by_project' => 'boolean',
            'use_by_field' => 'boolean',
            'cf_projects' => 'integer',
            'cf_amount' => 'integer',
            'projects' => 'array',
            'field_breakdown' => 'array',
            'series' => 'array',
            'publish_amount' => 'boolean',
            'publish_usage' => 'boolean',
            'publish_progress' => 'boolean',
            'national_rank' => 'integer',
            'prefecture_rank' => 'integer',
        ];
    }

    public static function slugFor(string $prefecture): ?string
    {
        return self::PREFECTURE_SLUGS[$prefecture] ?? null;
    }

    public static function prefectureForSlug(string $slug): ?string
    {
        return array_search($slug, self::PREFECTURE_SLUGS, true) ?: null;
    }

    public function getPrefectureSlugAttribute(): ?string
    {
        return self::slugFor($this->prefecture);
    }

    /**
     * 「宮崎県都城市」。市区町村ではなく都道府県が直接受け入れた分は、
     * 都道府県ページと同じ見出しにならないよう「北海道（道への寄附）」と区別する。
     */
    public function getFullNameAttribute(): string
    {
        return $this->city ? $this->prefecture.$this->city : $this->display_name;
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->city) {
            return $this->city;
        }

        return $this->prefecture.'（'.mb_substr($this->prefecture, -1).'への寄附）';
    }

    /** 1件あたりの平均寄附額。公表された受入額と件数から計算した値。 */
    public function getAveragePerDonationAttribute(): ?int
    {
        if (! $this->amount || ! $this->count) {
            return null;
        }

        return (int) round($this->amount / $this->count);
    }

    /** 「県内順位」の言い方は都道府県で変わる（北海道なら道内、東京都なら都内）。 */
    public static function areaLabel(string $prefecture): string
    {
        return match (true) {
            $prefecture === '北海道' => '道内',
            $prefecture === '東京都' => '都内',
            str_ends_with($prefecture, '府') => '府内',
            default => '県内',
        };
    }

    public function getAreaLabelAttribute(): string
    {
        return self::areaLabel($this->prefecture);
    }

    public function scopeCities($query)
    {
        return $query->whereNotNull('city');
    }
}

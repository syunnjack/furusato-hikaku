<?php

namespace Database\Seeders;

use App\Models\Municipality;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class MunicipalitySeeder extends Seeder
{
    /**
     * 総務省の現況調査から作った自治体データを取り込む。
     *
     * 元データは scripts/build-municipality-data.py が database/data/municipalities.json に
     * 書き出す。年1回の公表なので、更新時はJSONを作り直してこのシーダーを流し直せばよい。
     */
    public function run(): void
    {
        $path = database_path('data/municipalities.json');

        if (! File::exists($path)) {
            $this->command?->warn('database/data/municipalities.json が見つかりません。');

            return;
        }

        $payload = json_decode(File::get($path), true);
        $records = $payload['municipalities'] ?? [];

        if ($records === []) {
            $this->command?->warn('自治体データが空です。');

            return;
        }

        // 全国順位・県内順位は、市区町村どうしの比較として付ける。
        // 都道府県そのものへの寄附は性質が違うので順位からは外す。
        $cities = collect($records)->filter(fn (array $r) => ! empty($r['city']));
        $nationalRanks = $cities->sortByDesc(fn (array $r) => $r['amount'] ?? 0)
            ->values()
            ->mapWithKeys(fn (array $r, int $i) => [$r['code'] => $i + 1]);
        $prefectureRanks = $cities->groupBy('prefecture')
            ->flatMap(fn ($group) => $group->sortByDesc(fn (array $r) => $r['amount'] ?? 0)
                ->values()
                ->mapWithKeys(fn (array $r, int $i) => [$r['code'] => $i + 1]));

        $now = now();
        $rows = collect($records)->map(fn (array $r) => [
            'code' => $r['code'],
            'prefecture' => $r['prefecture'],
            'city' => $r['city'],
            'amount' => $r['amount'],
            'count' => $r['count'],
            'outside_amount' => $r['outside_amount'],
            'outside_count' => $r['outside_count'],
            'cost_total' => $r['cost_total'],
            'cost_ratio' => $r['cost_ratio'],
            'reward_provided' => $r['reward_provided'],
            'use_selectable' => $r['use_selectable'],
            'use_by_project' => $r['use_by_project'],
            'use_by_field' => $r['use_by_field'],
            'cf_projects' => $r['cf_projects'],
            'cf_amount' => $r['cf_amount'],
            'projects' => json_encode($r['projects'], JSON_UNESCAPED_UNICODE),
            'field_breakdown' => json_encode($r['field_breakdown'], JSON_UNESCAPED_UNICODE),
            'series' => json_encode($r['series'], JSON_UNESCAPED_UNICODE),
            'publish_amount' => $r['publish_amount'],
            'publish_usage' => $r['publish_usage'],
            'publish_progress' => $r['publish_progress'],
            'donor_relation' => $r['donor_relation'],
            'onestop_online' => $r['onestop_online'],
            'national_rank' => $nationalRanks[$r['code']] ?? null,
            'prefecture_rank' => $prefectureRanks[$r['code']] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($rows->chunk(200) as $chunk) {
            Municipality::upsert(
                $chunk->all(),
                ['code'],
                [
                    'prefecture', 'city', 'amount', 'count', 'outside_amount', 'outside_count',
                    'cost_total', 'cost_ratio', 'reward_provided', 'use_selectable', 'use_by_project',
                    'use_by_field', 'cf_projects', 'cf_amount', 'projects', 'field_breakdown', 'series',
                    'publish_amount', 'publish_usage', 'publish_progress', 'donor_relation',
                    'onestop_online', 'national_rank', 'prefecture_rank', 'updated_at',
                ]
            );
        }

        $this->command?->info(number_format($rows->count()).'団体を取り込みました（'.($payload['fiscalYear'] ?? '').'実績）。');
    }
}

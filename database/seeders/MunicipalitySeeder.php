<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class MunicipalitySeeder extends Seeder
{
    /**
     * 総務省の現況調査から作った自治体データを取り込む。
     *
     * 元データは scripts/build-municipality-data.py が database/data/municipalities.json に
     * 書き出す。年1回の公表なので、更新時はJSONを作り直してこのシーダーを流し直せばよい。
     */
    /** SQLiteのプレースホルダ上限（既定999）に収まるように小さく分けて書き込む。 */
    private const CHUNK = 30;

    public function run(): void
    {
        $path = database_path('data/municipalities.json');

        if (! File::exists($path)) {
            throw new RuntimeException('database/data/municipalities.json が見つかりません。');
        }

        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        $records = $payload['municipalities'] ?? [];
        $fiscalYear = $payload['fiscalYear'] ?? '';
        unset($payload);

        if ($records === []) {
            throw new RuntimeException('自治体データが空です。');
        }

        [$nationalRanks, $prefectureRanks] = $this->ranks($records);

        $now = now();
        $written = 0;

        // 3,000ページ分のJSONを一度に配列へ広げるとメモリを使い切るため、
        // 使い終わった分は都度捨てながら書き込む。
        while ($records !== []) {
            $chunk = array_splice($records, 0, self::CHUNK);
            $rows = [];

            foreach ($chunk as $r) {
                $rows[] = [
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
                ];
            }

            DB::table('municipalities')->upsert(
                $rows,
                ['code'],
                [
                    'prefecture', 'city', 'amount', 'count', 'outside_amount', 'outside_count',
                    'cost_total', 'cost_ratio', 'reward_provided', 'use_selectable', 'use_by_project',
                    'use_by_field', 'cf_projects', 'cf_amount', 'projects', 'field_breakdown', 'series',
                    'publish_amount', 'publish_usage', 'publish_progress', 'donor_relation',
                    'onestop_online', 'national_rank', 'prefecture_rank', 'updated_at',
                ]
            );

            $written += count($rows);
        }

        $this->command?->info(number_format($written).'団体を取り込みました（'.$fiscalYear.'実績、'
            .number_format(DB::table('municipalities')->count()).'団体を掲載中）。');
    }

    /**
     * 全国順位と都道府県内順位。市区町村どうしの比較として付け、
     * 都道府県そのものへの寄附は性質が違うので順位からは外す。
     *
     * @return array{0: array<string, int>, 1: array<string, int>}
     */
    private function ranks(array $records): array
    {
        $cities = [];

        foreach ($records as $r) {
            if (! empty($r['city'])) {
                $cities[] = ['code' => $r['code'], 'prefecture' => $r['prefecture'], 'amount' => $r['amount'] ?? 0];
            }
        }

        usort($cities, fn (array $a, array $b) => ($b['amount'] ?? 0) <=> ($a['amount'] ?? 0));

        $national = [];
        $prefecture = [];
        $seen = [];

        foreach ($cities as $city) {
            $national[$city['code']] = count($national) + 1;
            $seen[$city['prefecture']] = ($seen[$city['prefecture']] ?? 0) + 1;
            $prefecture[$city['code']] = $seen[$city['prefecture']];
        }

        return [$national, $prefecture];
    }
}

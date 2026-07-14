<?php

namespace App\Console\Commands;

use App\Models\Watch;
use App\Support\LineMessaging;
use App\Support\RakutenFurusatoSearch;
use Illuminate\Console\Command;

class CheckFurusatoWatches extends Command
{
    protected $signature = 'furusato:check-watches';

    protected $description = 'ウォッチ登録されたキーワードを再検索し、新着・再登場の返礼品をLINEで通知する';

    public function handle(): int
    {
        $watches = Watch::with('lineUser')->get();

        foreach ($watches as $watch) {
            if (! $watch->lineUser) {
                continue;
            }

            $results = RakutenFurusatoSearch::search($watch->keyword);
            $currentItemCodes = collect($results)->pluck('itemCode')->filter()->values()->all();

            $previouslySeen = $watch->seen_item_codes ?? [];
            $newItems = array_filter($results, function ($item) use ($previouslySeen) {
                $code = $item['itemCode'] ?? null;

                return $code && ! in_array($code, $previouslySeen, true);
            });

            // 初回チェック時(seen_item_codesが空)は「新着」扱いにせず、以後の差分検知の基準を作るだけにする
            if (! empty($previouslySeen) && ! empty($newItems)) {
                $names = collect($newItems)->pluck('itemName')->take(3)->implode('、');
                LineMessaging::push(
                    $watch->lineUser->line_user_id,
                    "「{$watch->keyword}」で新着・再登場の返礼品が見つかりました: {$names}"
                );
            }

            $watch->update([
                'seen_item_codes' => $currentItemCodes,
                'last_checked_at' => now(),
            ]);
        }

        $this->info("チェック完了: {$watches->count()}件のウォッチを確認しました。");

        return self::SUCCESS;
    }
}

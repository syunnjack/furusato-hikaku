<?php

namespace App\Http\Controllers;

use App\Models\Watch;
use Illuminate\Http\Request;

class WatchController extends Controller
{
    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
        ]);
        $keyword = $validated['keyword'];

        $lineUserLocalId = $request->session()->get('line_user_local_id');

        if (! $lineUserLocalId) {
            return redirect()->route('line.login', ['keyword' => $keyword]);
        }

        $watch = Watch::where('line_user_id', $lineUserLocalId)
            ->where('keyword', $keyword)
            ->first();

        if ($watch) {
            $watch->delete();

            return back()->with('success', 'ウォッチを解除しました。');
        }

        Watch::create([
            'line_user_id' => $lineUserLocalId,
            'keyword' => $keyword,
            'seen_item_codes' => [],
        ]);

        return back()->with('success', '新着・再登場の返礼品が見つかるとLINEでお知らせします。');
    }
}

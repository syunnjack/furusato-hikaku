<?php

namespace App\Http\Controllers;

use App\Models\LineUser;
use App\Models\Watch;
use App\Support\LineMessaging;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LineLoginController extends Controller
{
    public function redirect(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('line_login_state', $state);

        if ($request->filled('keyword')) {
            $request->session()->put('line_login_intended_keyword', $request->input('keyword'));
        }

        return redirect()->away(LineMessaging::authorizeUrl($state));
    }

    public function callback(Request $request)
    {
        $state = $request->query('state');
        $expectedState = $request->session()->pull('line_login_state');

        if (! $state || $state !== $expectedState) {
            return redirect()->route('furusato.index')->withErrors(['line' => 'LINEログインの検証に失敗しました。もう一度お試しください。']);
        }

        if (! $request->filled('code')) {
            return redirect()->route('furusato.index')->withErrors(['line' => 'LINEログインがキャンセルされました。']);
        }

        $token = LineMessaging::exchangeToken($request->input('code'));
        $claims = LineMessaging::verifyIdToken($token['id_token']);

        $lineUser = LineUser::updateOrCreate(
            ['line_user_id' => $claims['sub']],
            ['display_name' => $claims['name'] ?? null]
        );

        $request->session()->put('line_user_local_id', $lineUser->id);

        $intendedKeyword = $request->session()->pull('line_login_intended_keyword');
        if ($intendedKeyword) {
            Watch::firstOrCreate(
                ['line_user_id' => $lineUser->id, 'keyword' => $intendedKeyword],
                ['seen_item_codes' => []]
            );

            return redirect()->route('furusato.search', ['keyword' => $intendedKeyword])
                ->with('success', 'ウォッチ登録が完了しました。新着・再登場の返礼品が見つかるとLINEでお知らせします。');
        }

        return redirect()->route('furusato.index')->with('success', 'LINEログインが完了しました。');
    }
}

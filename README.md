# ふるさと納税比較

楽天市場に掲載されている実在のふるさと納税返礼品を、カテゴリ・都道府県・寄付額・レビューで比較するLaravelアプリです。

## 主な機能

- 楽天市場商品検索APIから最大1,500件をSQLiteへ同期
- キーワード、カテゴリ、都道府県、寄付額による複合検索
- 人気、評価、寄付額、新着順での並べ替え
- 返礼品詳細、関連返礼品、利用者口コミ
- キーワードのLINEウォッチ通知
- 商品詳細URLを含む動的サイトマップ
- GitHub Actionsによる毎日3:20（日本時間）の自動同期

## セットアップ

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build
```

`.env` に `RAKUTEN_APP_ID`、`RAKUTEN_ACCESS_KEY`、必要に応じて `RAKUTEN_AFFILIATE_ID` を設定します。

```bash
php artisan furusato:sync-catalog --pages=50 --delay=800
php artisan serve
```

楽天市場商品検索APIは1ページ30件・最大100ページです。通常はサーバー負荷とAPI利用制限を考慮して50ページを同期します。

## 品質確認

```bash
composer test
npm run build
```

商品情報は楽天ウェブサービスから取得しています。寄付額・在庫・配送条件は申込前に掲載元の商品ページで確認してください。

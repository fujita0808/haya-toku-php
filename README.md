# HAYA-TOKU PHP Starter Kit

HAYA-TOKU のクーポン PoC を **GAS ではなく PHP だけで動かす**ための最小スターターです。

## できること
- 管理画面ログイン
- クーポンプラン登録 / 更新
- JSONストレージ保存（PoC向け）
- 現在有効なクーポンの JSON API 返却
- `kore.html` から API を読み込んで描画
- 利用ログ保存（簡易）
- 将来の MySQL 化に向けた `sql/schema.sql` 同梱

## ディレクトリ構成

```text
haya-toku-php-starter/
├─ public/
│  ├─ index.php
│  └─ kore.html
├─ api/
│  ├─ coupon_current.php
│  └─ coupon_use.php
├─ admin/
│  ├─ login.php
│  ├─ logout.php
│  ├─ dashboard.php
│  ├─ coupon_edit.php
│  └─ coupon_save.php
├─ lib/
│  ├─ bootstrap.php
│  ├─ auth.php
│  ├─ coupon_logic.php
│  ├─ functions.php
│  └─ storage.php
├─ storage/
│  ├─ coupon_plans.json
│  ├─ usage_logs.json
│  └─ admins.php
├─ sql/
│  └─ schema.sql
└─ docs/
   └─ file-structure.md
```

## ローカル起動

```bash
cd haya-toku-php-starter
php -S 127.0.0.1:8000 -t .
```

- フロント: `http://127.0.0.1:8000/public/kore.html`
- 管理画面: `http://127.0.0.1:8000/admin/login.php`

## ログイン情報（初期）
- ID: `admin`
- Password: `hayatoku123`

`storage/admins.php` で変更できます。

## いまの保存方式
PoC を早く動かすため、保存先は JSON ファイルです。
本番化するときは `lib/storage.php` を MySQL 実装へ差し替えてください。

## Vercel について
このスターターの **フロントの確認** はしやすいですが、**標準の Vercel だけで PHP 管理画面 + 永続保存までそのまま本運用する前提にはしていません**。
理由は以下の 2 点です。

1. Vercel には公式 PHP ランタイムが見当たらず、PHP はコミュニティ runtime 扱いです。
2. このスターターは `storage/*.json` に書き込む方式なので、永続ストレージが必要です。

そのため、確認用としては
- `public/kore.html` の見た目レビューは Vercel
- API / 管理画面は通常の PHP サーバーかローカル

の分離が安全です。

## 次段階
- JSON ストレージ → MySQL
- `coupon_plans` と `products` を正式リレーション化
- LIFF の ID トークン検証
- 管理者権限の強化

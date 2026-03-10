# ファイル構造メモ

```text
haya-toku-php-starter/
├─ README.md
├─ public/
│  ├─ index.php                # フロントへのリダイレクト
│  └─ kore.html                # 1ファイル完結フロント
├─ api/
│  ├─ coupon_current.php       # 現在有効なクーポン JSON を返す
│  └─ coupon_use.php           # 利用記録 API
├─ admin/
│  ├─ login.php                # ログイン画面
│  ├─ logout.php               # ログアウト
│  ├─ dashboard.php            # 一覧 / ログ確認
│  ├─ coupon_edit.php          # 登録 / 編集フォーム
│  └─ coupon_save.php          # 保存処理
├─ lib/
│  ├─ bootstrap.php            # 共通読込
│  ├─ auth.php                 # 認証関数
│  ├─ coupon_logic.php         # 減衰ロジック
│  ├─ functions.php            # 汎用関数
│  └─ storage.php              # JSON ストレージ層
├─ storage/
│  ├─ admins.php               # 管理者定義
│  ├─ coupon_plans.json        # クーポンプラン保存先
│  └─ usage_logs.json          # 利用ログ保存先
├─ sql/
│  └─ schema.sql               # 将来の MySQL 化用スキーマ案
└─ docs/
   └─ file-structure.md        # このメモ
```

## 動かし方

```bash
php -S 127.0.0.1:8000 -t .
```

## URL
- フロント: `/public/kore.html`
- 管理画面: `/admin/login.php`
- API: `/api/coupon_current.php`

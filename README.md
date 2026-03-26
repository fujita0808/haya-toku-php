

# HAYA-TOKU（🍊ver / PHP PoC）

公開期間に応じて、日ごとに割引率が変化する「早得クーポン」を提供するシステムです。  
クーポンの割引率は固定ではなく、**利用時点の割引率が適用**されます。

---

## ■ 概要

HAYA-TOKU は、

- 公開期間に応じて割引率が減衰する
- 発行時点で割引率を確定する
- 使用時は再計算しない

という特徴を持つクーポンシステムです。

---

## ■ 0324仕様（確定仕様）

### クーポン仕様

- 減衰基準：**公開期間（日単位）**
- 減衰方式：**線形減衰**
- 割引率：**発行時に確定**
- 使用時：**再計算しない**
- 公開期間外：**発行不可**

---

### 割引率の考え方

- `initial_discount_rate`：初期割引率
- `min_discount_rate`：最低割引率
- 日次減衰率：
  
```

(initial - min) ÷ 公開日数

```

- 発行時割引率：

```

initial - (経過日数 × 日次減衰率)

```

- 最低割引率を下回らない

---

### 割引率の保存形式

- **0〜1 の小数**
- 例：`0.2 = 20%`

---

## ■ システム構成

- フロント：HTML + JavaScript（LIFF / Browser）
- バックエンド：PHP（フレームワークなし）
- DB：PostgreSQL（Render）

---

## ■ API一覧

### クーポン状態取得
```

GET /api/coupon_current.php

```
- 現在発行した場合の割引率
- 公開期間
- タイムライン

---

### クーポン発行
```

POST /api/coupon_issue.php

```
- 発行時に割引率を確定
- `issued_discount_rate` を保存

---

### クーポン取得
```

GET /api/coupon_get.php?couponCode=xxx

```
- 保存済みクーポン情報取得
- 再計算なし

---

### クーポン使用
```

GET /api/coupon_use.php?couponCode=xxx

```
- 使用確定
- `used_discount_rate` に記録

---

### ヘルスチェック
```

GET /api/check_health.php

````

例：

```json
{
  "ok": true,
  "message": "health check ok",
  "db_time": "2026-03-24 14:11:25.004002+09"
}
````

---

## ■ APIエラー形式

```json
{
  "ok": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "エラー内容"
  }
}
```

---

## ■ APIエラーコード一覧

### 共通

| HTTPステータス | code             | message例            | 意味        |
| --------- | ---------------- | ------------------- | --------- |
| 400       | `BAD_REQUEST`    | `couponCode が必要です。` | 必須パラメータ不足 |
| 500       | `INTERNAL_ERROR` | 例外メッセージ             | 想定外エラー    |

---

### coupon_current

| HTTP | code             | 意味       |
| ---- | ---------------- | -------- |
| 404  | `NO_ACTIVE_PLAN` | 有効なプランなし |

---

### coupon_issue

| HTTP | code                            | 意味      |
| ---- | ------------------------------- | ------- |
| 404  | `NO_ACTIVE_PLAN`                | プランなし   |
| 500  | `PLAN_PERIOD_MISSING`           | 期間未設定   |
| 403  | `PLAN_INACTIVE`                 | 非公開     |
| 403  | `OUTSIDE_PUBLIC_PERIOD`         | 公開期間外   |
| 500  | `COUPON_CODE_GENERATION_FAILED` | コード生成失敗 |

---

### coupon_use

| HTTP | code                  | 意味      |
| ---- | --------------------- | ------- |
| 400  | `BAD_REQUEST`         | パラメータ不足 |
| 404  | `COUPON_NOT_FOUND`    | クーポンなし  |
| 403  | `PLAN_INACTIVE`       | 非公開     |
| 409  | `COUPON_ALREADY_USED` | 使用済み    |
| 500  | `ISSUED_RATE_MISSING` | データ不整合  |

---

### coupon_get

| HTTP | code               | 意味      |
| ---- | ------------------ | ------- |
| 400  | `BAD_REQUEST`      | パラメータ不足 |
| 404  | `COUPON_NOT_FOUND` | クーポンなし  |
| 403  | `PLAN_INACTIVE`    | 非公開     |

---

## ■ DB構成

### coupon_plans

* クーポンプラン定義
* 公開期間・割引条件

---

### coupons

* 発行済みクーポン
* `issued_discount_rate` が主役

---

### usage_logs

* 操作ログ
* 将来的な監査用途

---

## ■ 開発・確認手順

### 1. DB初期化

```
scripts/init_db.php を実行
```

---

### 2. API確認

```
/api/check_health.php
```

---

### 3. フロント確認

```
/public/kore.html
```

---

## ■ 設計方針

* 割引率は「発行時に確定」が絶対ルール
* 使用時再計算は禁止
* フロントは表示専用
* ロジックはPHPに集約

---

## ■ 補足

* `couponId` は旧互換
* 現在は `couponCode` を使用

```


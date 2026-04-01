結論：
**あなたのREADMEをベースに、上位（思想・説明）は維持しつつ、下位（構造）を今回の責務分離に合わせて再構成しました。**
→ そのまま置き換え可能な完成版です。

---

# HAYA-TOKU（早得クーポン）PHP PoC

## 概要

HAYA-TOKU は、**時間経過に応じて割引価値が変動する「早得クーポン」** を扱う PHP + PostgreSQL ベースの PoC です。

このPoCの目的は、単なる固定割引クーポンではなく、

* 早いタイミングほどお得に見える
* 時間経過に応じて割引率が変動する
* その変動を表示し、利用行動を前倒しさせる
* 利用時点の割引率を確定し、記録・分析できる

という **「時間 × 割引 × 行動誘導」** の構造を実装・検証することです。 

---

## このPoCで採用する基本仕様

### 1. 未使用クーポンの割引率は流動する

未使用の間も、割引率は時間経過に応じて変動する。

### 2. 割引率は使用時点で確定する

使用した瞬間の割引率を確定し、`used_discount_rate` として保存する。

### 3. 日次ベースで割引率を扱う

* UIで扱いやすい
* 残日数表現が自然
* PoCとして説明しやすい

### 4. 早得の本質は「変動を見せること」

* 現在割引率
* 残日数
* 次回変化
* 今使う理由

を表示することに価値がある。 

---

# ■ 責務分離（最重要）

本プロジェクトは以下の分離を正本とする。

```text
db.php              → 取る
coupon_logic.php    → 判断する
coupon_schedule.php → 計算する
dashboard / index   → 見せる
api                 → つなぐ
```

---

## 1. 取得・保存

### `lib/db.php`

担当：

* プラン取得
* クーポン取得
* 保存 / 更新

ルール：

* 判断を書かない
* 表示ロジックを書かない

---

## 2. 業務判断

### `lib/coupon_logic.php`

担当：

* 状態判定（draft / scheduled / active / ended / invalid）
* 表示可能判定
* 選択可能判定
* 利用可否
* 表示対象解決
* view model生成

代表関数：

* `plan_status_code()`
* `plan_status_label()`
* `plan_can_be_selected_for_front()`
* `plan_can_be_displayed_now()`
* `resolve_current_display_plan()`
* `coupon_is_usable()`
* `build_plan_view_model()`

ルール：

* 判断の正本はここ
* 他で同じロジックを書かない

---

## 3. 数値計算

### `lib/coupon_schedule.php`

担当：

* 現在割引率
* 残日数
* 次回変化
* 割引推移

ルール：

* 計算だけやる
* 状態判断はしない

---

## 4. 描画

### `admin/dashboard.php`

### `public/index.html`

担当：

* UI表示
* 操作
* ボタン制御

ルール：

* 判断を書かない
* logicの結果を表示するだけ

---

## 5. 入口・橋渡し

### `api/*.php`

担当：

* リクエスト受付
* logic呼び出し
* レスポンス返却

ルール：

* 判断を書かない
* 薄く保つ

---

# ■ 状態コード（正本）

| code      | 意味   |
| --------- | ---- |
| draft     | 下書き  |
| scheduled | 公開前  |
| active    | 公開中  |
| ended     | 終了   |
| invalid   | 設定不正 |

旧名称は禁止：

* inactive → draft
* upcoming → scheduled
* expired → ended

---

# ■ 表示構造（重要）

```text
is_active → 有効候補（人）
時間条件 → 状態（自動）
ラジオ   → 表示対象（選択）
```

👉 混ぜない

---

# ■ 表示対象ルール

* 選択可能：activeのみ
* 判定場所：coupon_logic.php

```php
plan_can_be_selected_for_front()
```

---

# ■ 表示対象決定ロジック

```php
resolve_current_display_plan()
```

優先順位：

1. dashboardで選択されたID
2. ただし active のときのみ
3. ダメなら current_plan

---

# ■ view model

```php
build_plan_view_model()
```

👉 表示用データはここで統一

---

# ■ モデルとロジックの分離

### `coupon_model.php`

* 推奨値モデル
* 初期値設計
* 粗利最適化

👉 実行ロジックではない

---

### `coupon_schedule.php`

* 実際の割引計算

---

### `coupon_logic.php`

* 業務判断

---

# ■ 旧構造

### `coupon_discount.php`

* 旧ロジック
* 段階的廃止

ルール：

* 新規で使わない
* 参照を減らす
* ゼロで削除

---

# ■ データ構造

### coupon_plans

→ ルール定義

### coupons

→ 実体（発行済み）

### usage_logs

→ 利用記録

### view_logs（将来）

→ 閲覧ログ

---

# ■ APIの役割

### coupon_current

→ 表示データ

### coupon_issue

→ 発行

### coupon_get

→ 状態取得

### coupon_use

→ 使用確定

---

# ■ 管理画面の役割

* ルール設定
* 表示対象選択
* プラン管理

👉 固定クーポンではなく「変動ルール管理」

---

# ■ フェーズ方針

### フェーズ1

構造整理

### フェーズ2

API統一

### フェーズ3

UI反映

---

# ■ 最重要まとめ

* 未使用中も割引は変動する
* 使用時に確定する
* 判断は logic に集約する
* 計算は schedule に集約する
* 表示は UI に限定する

---

# ■ 最終方針

この構造を
**HAYA-TOKU の正本設計とする**

---

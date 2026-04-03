
# 早得（HAYA-TOKU）（🍊ver / PHP PoC）

**ver: 2026-04-03**

---

## ■ 概要

早得（HAYA-TOKU）は
時間経過に応じて割引率が変動するクーポンを提供するシステムです。

---

## ■ 現在の設計（最重要）

本システムは以下の3要素を分離して扱う

状態（status） = 時間 + is_active
表示（display） = 管理画面の選択（DB）
データ（plan） = DB

---

## ■ 状態の定義

状態は `coupon_logic.php` にて判定される

判定ルール：

* scheduled：now < start_at
* active：start_at <= now <= end_at
* ended：now > end_at
* invalid：日付不正

※ 現在の実装では
`is_active` も併用して有効判定に使用されている

---

## ■ 表示の仕組み（重要）

表示対象 = 管理画面で選択された1件

### 保存先

DB（coupon_plans.is_display_target）

### 取得

`find_display_target_plan()`

### 表示決定

`resolve_current_display_plan()`

---

## ■ 管理画面仕様（dashboard.php）

* プラン一覧を表示
* ラジオボタンで表示対象を選択
* 選択時に即保存（submit不要）
* 選択行を強調表示
* checked 判定は `is_display_target` を使用

---

## ■ 表示対象保存処理

`admin/display_target_save.php`

* `display_plan_id` をPOSTで受信
* `set_display_target_plan_id()` を呼び出し
* DBの is_display_target を更新

  * 全件 false → 対象のみ true

---

## ■ API構成

### coupon_current.php

* 表示対象プランを返す
* 内部で `resolve_current_display_plan()` を使用

---

### coupon_issue.php

* クーポン発行API
* フロントから `plan_id` を受け取る
* 指定されたプランを基準に発行する

※ フロントと完全一致するため
plan_id の明示送信が前提

---

### coupon_use.php

* クーポン使用確定

---

## ■ フロント仕様（index.html）

* `coupon_current.php` で表示対象取得
* 表示中の `plan.id` を保持
* 発行時に `plan_id` を送信
* モーダル表示
* カウントダウン後に使用確定

---

## ■ DB仕様（重要）

### coupon_plans

主なカラム：

* id
* title
* product_name
* start_at
* end_at
* initial_discount_rate
* min_discount_rate
* is_active
* is_display_target ← 表示対象フラグ

---

## ■ 設計思想（重要）

状態と表示を分離する

* 状態 → システム（時間 + is_active）
* 表示 → 管理者（ラジオ選択）
* データ → DB

---

## ■ 現在の到達状態

* 表示対象のJSON依存は廃止済み
* DB一本化（is_display_target）
* dashboard / API / フロントの整合が取れている

---

## ■ 今後の課題

* is_active を完全に排除し「時間のみ」に統一
* 不要ロジックの削除（旧設計の名残）
* UIの最適化

---

## ■ 目標

見てわかる構造
責務が分離されている
迷わないコード

---

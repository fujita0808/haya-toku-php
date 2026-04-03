# 早得（HAYA-TOKU）（🍊ver / PHP PoC）

---

## ■ 概要

早得（HAYA-TOKU）は  
時間経過に応じて割引率が変動するクーポンを提供するシステムです。

---

## ■ 現在の設計（重要）

本システムは以下の3要素を分離して扱う

状態（status） = 時間条件  
表示（display） = 管理画面の選択  
データ（plan） = DB

---

## ■ 状態の定義

状態は coupon_logic.php にて判定される

判定ルール：

開始日時・終了日時のみで決定

- scheduled：now < start_at
- active：start_at <= now <= end_at
- ended：now > end_at
- invalid：日付不正

※ is_active は状態判定に使用しない

---

## ■ フロント表示の仕組み

表示対象 = 管理画面のラジオ選択

- 保存先：storage/display_target.json
- API：coupon_current.php
- 取得関数：resolve_current_display_plan()

---

## ■ 管理画面仕様

dashboard.php

- 商品名・タイトル・説明文を主表示
- IDは表示しない（編集画面のみ表示）
- ラジオボタンで表示対象を選択
- 選択時に即保存（submit不要）
- 選択行を強調表示

---

## ■ coupon_save.php の仕様

- 通常の保存のみ実施
- 他プランの is_active を変更しない
- 一意制御は行わない

---

## ■ is_active の扱い

現状：

状態判定には使用しない

用途：

将来的なON/OFF制御用（現在は未使用に近い）

---

## ■ API構成

coupon_current.php

- 表示対象プランを返す
- resolve_current_display_plan() 使用

---

coupon_issue.php

- クーポン発行
- 表示対象プランを基準に発行する必要あり

---

coupon_use.php

- クーポン使用確定

---

## ■ フロント仕様（index.html）

- 表示対象プラン取得
- 「このクーポンを使う」で発行
- モーダル表示
- カウントダウン後に使用確定

---

## ■ 設計思想（重要）

状態と表示を分離する

- 状態 → システム（時間）
- 表示 → 管理者（意思）

---

## ■ 今後の課題

- coupon_issue.php を表示対象基準に統一
- 不要ロジック（旧JSON系）の削除
- UI整理

---

## ■ 目標

見てわかる構造  
責務が分離されている  
迷わないコード
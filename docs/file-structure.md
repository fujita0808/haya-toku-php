# HAYA-TOKU ファイル構成

---

## ■ 全体構造


/api
/admin
/lib
/public
/scripts
/sql


---

## ■ 各ディレクトリの役割

### /api

APIエンドポイント

- coupon_current.php
- coupon_issue.php
- coupon_get.php
- coupon_use.php
- check_health.php

---

### /lib

ビジネスロジック

- bootstrap.php（初期化）
- db.php（DB接続）
- coupon_discount.php（割引率計算）
- coupon_logic.php（業務ロジック）

---

### /admin

管理画面

- coupon_edit.php（入力UI）
- coupon_save.php（保存処理）

---

### /public

フロント

- kore.html（メインUI）

---

### /scripts

運用スクリプト

- init_db.php（DB初期化）

---

### /sql

DB定義

- schema.sql（最終定義）

---

## ■ どこを見れば何がわかるか

### 割引ロジック

→ lib/coupon_discount.php

---

### クーポンの流れ

→ api/coupon_issue.php  
→ api/coupon_use.php

---

### DB構造

→ sql/schema.sql

---

### UI挙動

→ public/kore.html

---

### システム起動確認

→ api/check_health.php

---

## ■ 設計のポイント

- 発行時確定モデル
- 再計算しない
- 日付ベース減衰
- DB中心設計

---

## ■ 補足

旧仕様（分単位減衰）は廃止済み  
ただしカラムは互換として残存
# 顧客予約システム（弁護士向け）— B案: lawyer_crm DB同居版

弁護士事務所向けカレンダー予約システム。
コードは独立、DBは既存 `lawyer_crm` を共用（`lc_` プレフィックス）。

## 構成

```
reservation_app/
├── public/           ブラウザ公開層
│   ├── index.php     月カレンダー＋スロット
│   ├── confirm.php   入力フォーム→DB登録
│   └── thanks.php    完了画面
├── admin/            管理画面（要ログイン）
│   ├── login.php / logout.php
│   ├── index.php     予約一覧（絞り込み・状態変更）
│   ├── slots.php     営業設定（時間・曜日・スロット長）
│   ├── exceptions.php 例外日（休業・臨時営業）
│   ├── reservation_edit.php 個別予約編集
│   └── _header.php / _footer.php
├── includes/
│   ├── config.php    DB接続定数・テナント定義
│   ├── db.php        PDOシングルトン
│   ├── auth.php      lc_users を流用した認証
│   └── functions.php スロット計算・ヘルパ
├── assets/
│   ├── css/style.css ネイビー+ゴールド
│   └── js/calendar.js
└── db/
    └── schema.sql    DDL（lc_appointments / lc_appointment_slots / lc_settings_reserve）
```

## DBレイアウト（lawyer_crm DB に追加）

| テーブル                | 役割                                              |
| ----------------------- | ------------------------------------------------- |
| `lc_settings_reserve`   | テナント毎の営業設定 (1テナント=1行)              |
| `lc_appointment_slots`  | 例外日（祝日・臨時休業 / 臨時営業）               |
| `lc_appointments`       | 予約レコード                                      |

全テーブルに `tenant_id` を持たせ、lawyer_crm の SaaS 設計と整合。
**認証ユーザーは既存 `lc_users` を流用**（予約用admin専用テーブルは作らない）。

## デプロイ手順

### 1. DDL 実行
```bash
mysql -u root -p lawyer_crm < db/schema.sql
```

### 2. ファイル配置
```bash
scp -r reservation_app/ xserver-vps:/var/www/html/reservation/
ssh xserver-vps "chown -R www-data:www-data /var/www/html/reservation/"
```

### 3. 動作確認URL
- 公開:    `http://162.43.14.130/reservation/public/index.php`
- 管理:    `http://162.43.14.130/reservation/admin/login.php`

### 4. 管理ログイン
既存 `lc_users` で。テナント初期管理者 (例: `admin@example.com`) のパスワードでログイン可能。

## マルチテナント対応

- 公開URLには `?t=N` でテナントIDを指定（未指定は `DEFAULT_TENANT_ID=1`）
- 管理画面はログインユーザの `tenant_id` で自動スコープ
- 同じ予約システム配下で複数事務所（lawyer / tax_accountant / 等）を運用可能

## セキュリティ

- CSRF: 全 POST に `csrf_token` 必須
- 二重予約: `UNIQUE (tenant_id, reservation_date, start_time)` 制約 + 例外ハンドリング
- Honeypot: `confirm.php` に隠しフィールド `website`
- PDO: プリペアドステートメント
- 本番運用前に **HTTPS化必須**

## 既知の制限 / 今後の課題

- メール通知未実装（予約完了時に管理者・顧客へ送る `mb_send_mail()` を後で追加）
- 公開ページのカレンダーは AJAX ではなくフルリロード（スマホで十分なUX想定）
- キャンセル期限による自動拒否はDB上は持つが、顧客側UIからのキャンセル機能は未実装
- `lc_clients` との依頼者紐付けは UI 未提供（`lc_appointments.client_id` カラムは確保済み）

## lawyer_crm との将来統合

- `lc_appointments.client_id` ⇔ `lc_clients.id` で JOIN 可能
- lawyer_crm 側のナビに「予約管理」リンクを足し、本システム `admin/index.php` へ飛ばす設計を想定
- DB同居なので外部キー化も可能（運用後に検討）

## 配色

`lawyer_crm` と統合時に違和感がないよう同色:
- ネイビー: `#1a3a5c`
- ゴールド: `#c8a94a`

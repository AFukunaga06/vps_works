# 小規模クリニック予約管理システム

PHP + MySQL + Bootstrap 5 で構築した小規模クリニック向け予約管理 Web アプリです。

## 機能一覧

| 機能 | 概要 |
|------|------|
| ダッシュボード | 本日の予約一覧・統計・次回受診提案アラート |
| 患者台帳 | 氏名・生年月日・血液型・アレルギー・既往歴などを管理 |
| 予約管理 | 曜日別の空き枠確認・予約登録・ステータス管理 |
| 問診票 | 主訴・痛みスケール・症状チェック・服薬情報・緊急連絡先 |
| 診察記録 | SOAP 形式（主訴/所見/診断/計画）＋バイタル・処方 |
| 次回受診提案 | 診察記録の `next_visit_days` から自動計算し一覧表示 |

## ファイル構成

```
07_clinic_yoyaku/
├── schema.sql              — DB テーブル定義
├── seed.sql                — 架空サンプルデータ（患者 30 名）
├── README.md
├── login.php               — ログイン画面
├── logout.php
├── index.php               — ダッシュボード
├── patients.php            — 患者一覧
├── patient_new.php         — 患者新規登録
├── patient_view.php        — 患者詳細（予約・診察タブ）
├── patient_edit.php        — 患者情報編集
├── appointments.php        — 予約一覧（絞込付き）
├── appointment_new.php     — 新規予約（空き枠検索付き）
├── appointment_view.php    — 予約詳細・ステータス変更
├── questionnaire.php       — 問診票入力・編集
├── consultation_edit.php   — 診察記録（SOAP）入力・編集
├── consultations.php       — 診察履歴一覧
├── includes/
│   ├── config.php          — DB 接続設定
│   ├── auth.php            — ログイン・セッション管理
│   ├── helpers.php         — ユーティリティ関数
│   └── layout.php          — 共通ヘッダー・ナビ・フッター
└── assets/
    ├── css/style.css
    └── js/app.js
```

## セットアップ

### 1. データベース作成

```bash
mysql -u root -p < schema.sql
mysql -u root -p < seed.sql
```

### 2. 設定変更

`includes/config.php` の DB 接続情報を環境に合わせて変更してください。

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'clinic_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Web サーバー配置

Apache / Nginx のドキュメントルートに配置し、ブラウザで `login.php` にアクセスします。

### 4. デモ用ログイン

| ユーザー名 | パスワード | 役割 |
|-----------|-----------|------|
| admin | password | 管理者 |
| dr_yamamoto | password | 医師（内科） |
| dr_tanaka | password | 医師（小児科） |
| nurse01 | password | 受付 |

> **注意：** seed.sql のパスワードハッシュはダミーです。  
> `auth.php` で `$password === 'password'` の場合を許可しているため、  
> **本番環境では必ず `password_hash()` で正しいハッシュを生成してください。**

## テーブル構成

```
users              — スタッフ（管理者・医師・受付）
departments        — 診療科目
doctors            — 医師マスタ
patients           — 患者台帳（30名サンプル）
slot_templates     — 予約枠マスタ（曜日・時間帯・医師別）
appointments       — 予約
questionnaires     — 問診票（1予約につき1票）
consultations      — 診察記録 SOAP（1予約につき1件）
```

## 動作環境

- PHP 8.0 以上
- MySQL 5.7 / 8.0、MariaDB 10.4 以上
- Bootstrap 5.3（CDN）
- Bootstrap Icons 1.11（CDN）

## セキュリティ

- CSRF トークンによるフォーム保護
- `htmlspecialchars()` による XSS 対策（全出力）
- PDO プリペアドステートメントによる SQL インジェクション対策
- セッション固定攻撃対策（`session_regenerate_id(true)`）
- Cookie の `httponly` / `SameSite=Strict` 設定

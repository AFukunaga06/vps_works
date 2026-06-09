# 税理士事務所向け顧問先管理システム（ZeiriCRM）

PHP + MySQL + Bootstrap 5 で構築した税理士事務所向け顧問先管理 Web アプリです。

## 機能一覧

| 機能 | 説明 |
|---|---|
| ダッシュボード | 申告期限アラート・タスク期限アラート・サマリカードを一覧表示 |
| 顧問先台帳 | 会社情報・決算月・月次顧問料の登録・編集・削除・検索 |
| 申告期限管理 | 法人税・消費税・所得税など種別ごとの期限管理。残日数・超過日数を色分け表示 |
| 依頼タスク | カテゴリ・優先度・担当者・進捗ステータス管理。一覧からワンクリックでステータス更新 |
| 月次報酬管理 | 月別請求額・入金管理。月次合計・未入金額のサマリ表示 |
| 進捗ログ | 顧問先ごとのタイムライン形式コメント記録 |
| 期限近接アラート | 30日以内・超過した申告期限・タスクをダッシュボードに赤/黄でアラート表示 |

## ファイル構成

```
04_zeirishi_crm/
├── config.php              # DB接続設定・共通関数
├── index.php               # ダッシュボード
├── clients.php             # 顧問先台帳一覧
├── client_detail.php       # 顧問先詳細（申告期限・タスク・報酬・ログ）
├── deadlines.php           # 申告期限一覧
├── tasks.php               # タスク一覧
├── fees.php                # 月次報酬管理
├── layout/
│   ├── header.php          # 共通ヘッダー・サイドバー
│   ├── footer.php          # 共通フッター
│   └── client_form_fields.php  # 顧問先フォームパーツ
├── schema.sql              # テーブル定義
├── seed.sql                # 架空サンプルデータ15件
└── README.md
```

## セットアップ

### 1. データベース作成

```bash
mysql -u root -p < schema.sql
mysql -u root -p < seed.sql
```

### 2. DB接続設定

`config.php` を編集して接続情報を設定します。

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'zeirishi_crm');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
```

### 3. Web サーバーに配置

Apache / Nginx の公開ディレクトリに配置し、ブラウザで `index.php` を開きます。

ローカル開発時は PHP 組み込みサーバーでも動作します。

```bash
php -S localhost:8000
```

## 動作環境

- PHP 8.1 以上
- MySQL 8.0 以上（または MariaDB 10.6 以上）
- Bootstrap 5.3（CDN）

## アラート設定

`config.php` の `ALERT_DAYS` 定数で、何日前からアラート表示するかを変更できます（デフォルト 30 日）。

```php
define('ALERT_DAYS', 30);
```

## サンプルデータ概要

架空の顧問先 15 件（法人 10 件・個人 5 件）を収録。  
各種申告期限・タスク・月次報酬・進捗ログを含み、初期状態からダッシュボードのアラートや各画面の動作を確認できます。

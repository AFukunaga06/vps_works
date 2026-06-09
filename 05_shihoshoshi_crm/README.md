# 司法書士事務所CRM

PHP + MySQL + Bootstrap5 で構築した司法書士向け案件管理システム。

## 機能

| 機能 | 説明 |
|------|------|
| 依頼人台帳 | 氏名・連絡先・属性の管理 |
| 案件管理 | 種別・状態・担当・法務局の管理 |
| 物件情報 | 登記対象不動産の地番・面積・評価額 |
| 期日管理 | 申請期限・提出日などのリマインド |
| 進捗タイムライン | 面談・申請・登記完了などの活動記録 |
| 報酬計算 | 司法書士報酬・登録免許税・実費の収納管理 |
| 書類チェックリスト | 案件ごとの書類収取状況を管理 |
| ダッシュボード | 直近期日・受任中案件・収支サマリ |

## 対応案件種別

- 所有権移転登記（売買・相続・贈与）
- 抵当権設定 / 抵当権抹消
- 商業法人登記
- 成年後見申立
- 遺言書作成
- 相続放棄

## ファイル構成

```
05_shihoshoshi_crm/
├── config.php          # DB設定・ヘルパー関数・定数
├── login.php           # ログイン
├── logout.php          # ログアウト
├── index.php           # ダッシュボード
├── clients.php         # 依頼人一覧
├── client_form.php     # 依頼人 新規/編集
├── client_detail.php   # 依頼人詳細
├── cases.php           # 案件一覧
├── case_form.php       # 案件 新規/編集
├── case_detail.php     # 案件詳細（物件/期日/進捗/報酬/書類）
├── deadlines.php       # 期日一覧
├── billing.php         # 報酬一覧・集計
├── checklist.php       # 書類チェックリスト（案件横断）
├── setup.php           # DB初期化スクリプト
├── schema.sql          # テーブル定義
├── seed.sql            # サンプルデータ（15件）
├── includes/
│   ├── header.php      # 共通ヘッダー・サイドバー
│   └── footer.php      # 共通フッター
└── uploads/            # ファイルアップロード用ディレクトリ
```

## セットアップ

1. `config.php` の DB 設定を環境に合わせて変更

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'shihoshoshi_crm');
define('DB_USER', 'root');
define('DB_PASS', '');
```

2. ブラウザで `setup.php` にアクセス（DB作成・テーブル作成・サンプルデータ投入）

3. `setup.php` を削除またはアクセス制限する

4. `login.php` からログイン

## デモアカウント

| ユーザー名 | パスワード | 権限 |
|-----------|----------|------|
| admin | password123 | 管理者 |
| staff1 | password123 | スタッフ |

## 動作環境

- PHP 8.1+
- MySQL 8.0+ / MariaDB 10.6+
- Bootstrap 5.3（CDN）
- Bootstrap Icons 1.11（CDN）

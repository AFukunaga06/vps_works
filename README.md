# 🗂 vps_works — Webアプリ制作実績まとめ

PHP / MySQL を中心に、**実運用される業務Webアプリを約57件**自作・本番公開してきました。
企画 → 設計 → 実装 → デプロイ → 運用までを一人で担当しています。

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat&logo=javascript&logoColor=black)
![Python](https://img.shields.io/badge/Python-3776AB?style=flat&logo=python&logoColor=white)
![Linux](https://img.shields.io/badge/Linux_VPS-FCC624?style=flat&logo=linux&logoColor=black)
![Stripe](https://img.shields.io/badge/Stripe-635BFF?style=flat&logo=stripe&logoColor=white)

> 📄 **採用ご担当者の方へ** → 詳しい実績・技術・自己PRは **[PORTFOLIO.md](PORTFOLIO.md)** をご覧ください。

---

## 📊 実績サマリー

| 指標 | 数値 |
|---|---|
| 制作したWebアプリ | **約 57 件**（本番稼働 / 公開中） |
| 総コード行数 | **約 159,000 行** |
| PHPファイル数 | 641 |
| 主な実装 | CRM・予約・決済・外部API連携・AI連携 |

### 品質へのこだわり（独学でも実務水準を意識）
| 観点 | 実装ファイル数 |
|---|---|
| 🛡 SQLインジェクション対策（PDOプリペアド） | **316** |
| 🛡 CSRF対策（トークン方式） | **119** |
| 🔑 パスワードのハッシュ化（bcrypt） | **55** |

---

## ⭐ 代表作

| 予約システム（カレンダー） | SSL期限チェッカー（実データ稼働） |
|---|---|
| ![reservation](screenshots/reservation.png) | ![ssl_checker](screenshots/ssl_checker.png) |

### 🏛 lawyer_crm — 士業向けCRM SaaS（最大規模）
弁護士・税理士・司法書士・行政書士の4業種対応の顧客管理SaaS。
**Stripe決済**によるサブスク課金、**Claude API**を使った契約書のリスク分析・要約（Pythonマイクロサービス化）、案件・見積・契約書管理、管理画面、DBマイグレーションまで実装。

### ⚖️ fhouritu_soudann — 法律相談CRM × Googleカレンダー連携
**Google Calendar API（OAuth2.0）** を自前実装し、相談予約とスケジュールを双方向同期。

### 📚 fuku_ai_terakoya — 予約・決済・自動メール
講座の予約受付＋オンライン決済＋**PHPMailerによる確認メール自動送信**。

### ♟ chess_app — Claude AIと対戦するチェス
**Anthropic Claude API**を対戦相手に。フロント(chessboard.js)×PHPバックエンドで対戦成績も保存。

### 🐡 chess_fugu / othello_fugu — fugu(LLM)と対戦するチェス＆オセロ
**Sakana AI のLLM(fugu)** を対戦相手に組み込んだ姉妹アプリ（[chessfugu.afuku5906.com](https://chessfugu.afuku5906.com) / [othellofugu.afuku5906.com](https://othellofugu.afuku5906.com)）。
難易度3段階＝ランダム / LLM / **ミニマックス探索（αβ枝刈り・評価関数自作）**。
非同期競合による誤判定バグの調査・修正記録も同梱（[chess_fugu/DEBUG_対戦結果誤判定.md](chess_fugu/DEBUG_対戦結果誤判定.md)）。

### 🧰 実用ツール群
`ssl_checker`（SSL期限監視）/ `urlshort`（URL短縮）/ `qr_generator` / `password_generator` ほか多数。

---

## 📂 全制作物カテゴリ一覧

| カテゴリ | 主なプロジェクト |
|---|---|
| **CRM・業務システム** | lawyer_crm（4業種SaaS）, fhouritu_soudann, fsoudann02, gyousei_crm, 04_zeirishi_crm, 05_shihoshoshi_crm, crm |
| **予約・申込・相談** | reservation, fuku_soudan, fuku_ai_terakoya, terakoya_01, 07_clinic_yoyaku, contact-form, mail_toiawase, ch_youbousyo |
| **名簿・出欠管理** | sugitach, sugitach02, tubasa_meibo, church_attendance, Church_Pastoral_Support, tokyoch01, attend_system01, kojinnjyouho |
| **家計簿** | My_kakeibo, My_kakeibo_01 / _02 / _05 |
| **ツール・ユーティリティ** | checklist01, ssl_checker, bookmark, urlshort, qr_generator, password_generator, color-palette, visitor_counter, calendar, calendar02, line_gcal, nippo-form |
| **エンタメ** | chess_app, chess_fugu, othello_fugu, chess0430, shooting_games, youtube_playlist, bbs |
| **ポートフォリオ・他** | fuku_sample_01, sakuhinnsyuu, sakuhinnsyuu01, seikanado_01, Amazon_cyuusyutu, reminder |

---

## 🛠 技術スタック
**言語**: PHP / Python / JavaScript / SQL / HTML / CSS
**DB**: MySQL（PDO・スキーマ設計・マイグレーション）
**決済 / API**: Stripe, Google Calendar API(OAuth2.0), Anthropic Claude API, LINE, PHPMailer(SMTP)
**インフラ**: Linux(Xserver VPS), Apache, Let's Encrypt(SSL), cron自動バックアップ, Composer, Git

> ⚠️ 公開リポジトリのため、DB認証情報・APIキー等の秘密情報はすべてダミー値に置換し、設定/認証ファイルは除外しています。

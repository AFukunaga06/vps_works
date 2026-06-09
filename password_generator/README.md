# パスワード生成ツール

安全なパスワードをブラウザ上で生成するツールです。  
完全クライアントサイド処理（パスワードはサーバーに一切送信されません）。

## 機能

- **3つの生成モード**
  - ランダム: 文字数(4-64)・文字種・紛らわしい文字除外
  - 単語ベース: 英単語3-5語をハイフン連結（頭文字大文字・末尾数字オプション）
  - パスフレーズ: 日本語由来のローマ字単語を連結（例: sakura-yume-tsuki-2047）
- **強度判定**: zxcvbnライブラリによるリアルタイム判定・解読推定時間表示
- **一括生成**: 5個/10個/20個の同時生成
- **履歴管理**: localStorageに直近20件保存・マスク表示・ワンクリックコピー
- **ダークモード**: OS設定に自動追従

## ファイル構成

```
password_generator/
├── index.html   メインHTML
├── style.css    スタイルシート（ダークモード対応）
├── script.js    パスワード生成ロジック・履歴管理
├── words.js     英単語辞書(~200語) + ひらがな辞書(~100語)
└── README.md    本ファイル
```

## 使用ライブラリ

- [zxcvbn](https://github.com/dropbox/zxcvbn) v4.4.2 (MIT, Dropbox)  
  CDN: `https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js`

---

## サーバー配置手順

### 1. ファイルをVPSにコピー

```bash
sudo cp -r ~/qr_generator       /var/www/html/
sudo cp -r ~/password_generator /var/www/html/
sudo chown -R www-data:www-data /var/www/html/qr_generator
sudo chown -R www-data:www-data /var/www/html/password_generator
```

### 2. Apache バーチャルホスト設定

```bash
sudo nano /etc/apache2/sites-available/pw.afuku5906.com.conf
```

以下の内容を記述:

```apache
<VirtualHost *:80>
    ServerName pw.afuku5906.com
    DocumentRoot /var/www/html/password_generator

    <Directory /var/www/html/password_generator>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/pw.afuku5906.com-error.log
    CustomLog ${APACHE_LOG_DIR}/pw.afuku5906.com-access.log combined
</VirtualHost>
```

サイトを有効化してリロード:

```bash
sudo a2ensite pw.afuku5906.com.conf
sudo systemctl reload apache2
```

### 3. QRツールのバーチャルホストも合わせて設定

```bash
sudo nano /etc/apache2/sites-available/qr.afuku5906.com.conf
```

内容は `qr_generator/README.md` を参照。

```bash
sudo a2ensite qr.afuku5906.com.conf
sudo systemctl reload apache2
```

### 4. DNS設定

XserverのDNSコンソールで以下のAレコードをVPSのIPに向けてください:
- `qr.afuku5906.com`
- `pw.afuku5906.com`

### 5. Let's Encrypt SSL 設定

```bash
sudo apt install -y certbot python3-certbot-apache   # 未インストールの場合

# 両ドメイン同時発行
sudo certbot --apache -d qr.afuku5906.com -d pw.afuku5906.com
```

または個別に:

```bash
sudo certbot --apache -d pw.afuku5906.com
```

自動更新確認:

```bash
sudo certbot renew --dry-run
```

---

## 動作確認コマンド

```bash
# HTTP ステータス確認
curl -Is https://pw.afuku5906.com/ | head -5

# HTMLタイトル確認
curl -s https://pw.afuku5906.com/ | grep -o '<title>[^<]*</title>'

# QRツールも確認
curl -Is https://qr.afuku5906.com/ | head -5
```

## セキュリティノート

- パスワード生成に `crypto.getRandomValues()` (CSPRNG) を使用
- 生成されたパスワードはサーバーに送信されない
- 履歴はブラウザの localStorage にのみ保存（クリア可能）
- zxcvbn は CDN から読み込み（オフライン環境では強度判定がフォールバック動作）

## トラブルシューティング

| 症状 | 対処 |
|------|------|
| 強度が表示されない | CDNからzxcvbnが読み込めているか確認 |
| コピーできない | HTTPS環境でないと `navigator.clipboard` が動作しない（HTTP時はフォールバック使用） |
| 履歴が消えた | プライベートブラウズではlocalStorageは揮発 |

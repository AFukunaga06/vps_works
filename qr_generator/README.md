# QRコード生成ツール

URL・vCard・WiFi・メールアドレスのQRコードをブラウザ上で生成するツールです。  
完全クライアントサイド処理（入力データはサーバーに送信されません）。

## 機能

- **4つの入力モード**: URL/テキスト / vCard / WiFi接続情報 / メールアドレス(mailto)
- **カスタマイズ**: 前景色・背景色・サイズ(200/400/800px)・誤り訂正レベル(L/M/Q/H)
- **中央ロゴ画像**: 画像アップロード対応（誤り訂正H推奨）
- **出力**: PNG / SVG ダウンロード・リアルタイムプレビュー

## ファイル構成

```
qr_generator/
├── index.html   メインHTML
├── style.css    スタイルシート
├── script.js    QR生成ロジック
└── README.md    本ファイル
```

## 使用ライブラリ

- [qr-code-styling](https://github.com/kozakdenys/qr-code-styling) v1.5.0 (MIT)  
  CDN: `https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js`

---

## サーバー配置手順

### 1. ファイルをVPSにコピー

```bash
sudo cp -r ~/qr_generator /var/www/html/
sudo chown -R www-data:www-data /var/www/html/qr_generator
```

### 2. Apache バーチャルホスト設定

設定ファイルを作成します。

```bash
sudo nano /etc/apache2/sites-available/qr.afuku5906.com.conf
```

以下の内容を記述:

```apache
<VirtualHost *:80>
    ServerName qr.afuku5906.com
    DocumentRoot /var/www/html/qr_generator

    <Directory /var/www/html/qr_generator>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/qr.afuku5906.com-error.log
    CustomLog ${APACHE_LOG_DIR}/qr.afuku5906.com-access.log combined
</VirtualHost>
```

サイトを有効化してリロード:

```bash
sudo a2ensite qr.afuku5906.com.conf
sudo systemctl reload apache2
```

### 3. DNS設定

XserverのDNSコンソールで `qr.afuku5906.com` のAレコードをVPSのIPに向けてください。  
DNS反映後（数分〜数時間）、以下で疎通確認:

```bash
curl -I http://qr.afuku5906.com/
```

### 4. Let's Encrypt SSL 設定

```bash
sudo apt install -y certbot python3-certbot-apache   # 未インストールの場合
sudo certbot --apache -d qr.afuku5906.com
```

証明書自動更新の確認:

```bash
sudo certbot renew --dry-run
```

certbot が自動的に80→443リダイレクトと HTTPS バーチャルホストを追加します。

---

## 動作確認

```bash
# HTTP レスポンス確認
curl -Is https://qr.afuku5906.com/ | head -5

# HTMLが返ってくるか確認
curl -s https://qr.afuku5906.com/ | grep -o '<title>[^<]*</title>'
```

## トラブルシューティング

| 症状 | 対処 |
|------|------|
| QRコードが生成されない | CDNからJSが読み込めているか確認（ブラウザDevTools→Networkタブ） |
| ロゴ入りQRが読み取れない | 誤り訂正レベルをHに上げ、ロゴサイズを小さくする |
| SVGダウンロードが空 | ブラウザのポップアップブロックを確認 |

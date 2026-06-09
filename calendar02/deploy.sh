#!/bin/bash
# =====================================================
# calendar02 VPSデプロイ手順
# WSL上で実行してください
# =====================================================

VPS="root@162.43.14.130"
REMOTE="/var/www/html/calendar02"
LOCAL="./calendar02"  # ファイルを置いたローカルのパス

echo "=== ① VPSにディレクトリ作成 ==="
ssh -i ~/.ssh/id_ed25519 $VPS "mkdir -p $REMOTE"

echo "=== ② ファイルをVPSに転送 ==="
scp -i ~/.ssh/id_ed25519 -r $LOCAL/* $VPS:$REMOTE/
scp -i ~/.ssh/id_ed25519 $LOCAL/.htaccess $VPS:$REMOTE/

echo "=== ③ 権限設定 ==="
ssh -i ~/.ssh/id_ed25519 $VPS "
    chown -R www-data:www-data $REMOTE
    chmod -R 755 $REMOTE
    chmod 640 $REMOTE/config.php
"

echo "=== ④ MySQLにDB・テーブル作成 ==="
ssh -i ~/.ssh/id_ed25519 $VPS "mysql -u root -p < $REMOTE/setup.sql"

echo "=== ⑤ PHPMailerをインストール ==="
ssh -i ~/.ssh/id_ed25519 $VPS "
    cd $REMOTE
    which composer || curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    composer require phpmailer/phpmailer --no-interaction
    chown -R www-data:www-data $REMOTE/vendor
"

echo "=== 完了 ==="
echo "https://calendar02.afuku5906.com/ または http://162.43.14.130/calendar02/ にアクセス"
echo ""
echo "初期ログイン:"
echo "  ユーザー名: admin"
echo "  パスワード: calendar2025"
echo ""
echo "⚠️  必ず config.php の DB_PASS と SMTP_PASS を設定してください"

-- 管理者シード (初期パスワードは別途展開時に PHP password_hash で生成して埋め込む)
USE fuku_ai_terakoya;

INSERT INTO admins (username, password_hash, name, email)
VALUES ('admin', '__ADMIN_HASH__', '管理者', 'afky5906@gmail.com')
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash);

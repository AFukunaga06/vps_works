#\!/bin/bash
BACKUP_DIR=/var/www/html/sugitach/backup
DATE=$(date +%Y%m%d_%H%M%S)
FILENAME="church_attendance_auto_${DATE}.sql"
mysqldump --host=localhost --user=church_user --password=REDACTED_FOR_PUBLIC --single-transaction --routines church_attendance > "${BACKUP_DIR}/${FILENAME}"
# 30日以上前の自動バックアップを削除
find "${BACKUP_DIR}" -name "church_attendance_auto_*.sql" -mtime +30 -delete

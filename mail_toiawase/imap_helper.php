<?php
require_once __DIR__ . '/config.php';

function imap_connect_gmail() {
    $conn = @imap_open(IMAP_INBOX, GMAIL_USER, GMAIL_PASS);
    if (!$conn) {
        throw new RuntimeException('IMAP接続失敗: ' . imap_last_error());
    }
    return $conn;
}

function fetch_emails(int $limit = 25, bool $unread_only = true): array {
    $conn = imap_connect_gmail();

    if ($unread_only) {
        $ids = imap_search($conn, 'UNSEEN');
    } else {
        $ids = imap_search($conn, 'ALL');
    }

    if (!$ids) { imap_close($conn); return []; }

    rsort($ids); // 新しい順
    $ids = array_slice($ids, 0, $limit);

    $emails = [];
    foreach ($ids as $id) {
        $header  = imap_headerinfo($conn, $id);
        $subject = decode_mime($header->subject ?? '（件名なし）');
        $from    = isset($header->from[0])
                 ? decode_mime($header->from[0]->personal ?? '') . ' <' . ($header->from[0]->mailbox . '@' . $header->from[0]->host) . '>'
                 : '';
        $date    = $header->date ?? '';
        $ts      = strtotime($date);
        $unread  = !($header->Seen ?? false);

        $category = classify_email($subject, '');

        $emails[] = [
            'id'       => $id,
            'subject'  => $subject,
            'from'     => $from,
            'dateStr'  => $ts ? date('m/d H:i', $ts) : $date,
            'unread'   => $unread,
            'category' => $category,
        ];
    }

    imap_close($conn);
    return $emails;
}

function fetch_email_detail(int $id): array {
    $conn    = imap_connect_gmail();
    $header  = imap_headerinfo($conn, $id);
    $subject = decode_mime($header->subject ?? '（件名なし）');
    $from    = isset($header->from[0])
             ? decode_mime($header->from[0]->personal ?? '') . ' <' . ($header->from[0]->mailbox . '@' . $header->from[0]->host) . '>'
             : '';
    $date    = $header->date ?? '';
    $ts      = strtotime($date);

    // 本文取得
    $structure = imap_fetchstructure($conn, $id);
    $body      = get_body($conn, $id, $structure);

    // 既読にする
    imap_setflag_full($conn, (string)$id, '\\Seen');

    imap_close($conn);

    return [
        'id'      => $id,
        'subject' => $subject,
        'from'    => $from,
        'dateStr' => $ts ? date('Y/m/d H:i', $ts) : $date,
        'body'    => $body,
    ];
}

function get_body($conn, int $id, $structure, string $section = ''): string {
    // text/plain を探す
    if (isset($structure->parts) && count($structure->parts)) {
        foreach ($structure->parts as $i => $part) {
            $partNum = $section ? $section . '.' . ($i + 1) : (string)($i + 1);
            if ($part->type === 0 && strtolower($part->subtype) === 'plain') {
                return decode_body(imap_fetchbody($conn, $id, $partNum), $part->encoding);
            }
            if (isset($part->parts)) {
                $result = get_body($conn, $id, $part, $partNum);
                if ($result) return $result;
            }
        }
        // plain がなければ最初のパート
        $partNum = $section ? $section . '.1' : '1';
        return decode_body(imap_fetchbody($conn, $id, $partNum), $structure->parts[0]->encoding ?? 0);
    }

    return decode_body(imap_fetchbody($conn, $id, $section ?: '1'), $structure->encoding ?? 0);
}

function decode_body(string $data, int $encoding): string {
    switch ($encoding) {
        case 3: $data = base64_decode($data); break;       // BASE64
        case 4: $data = quoted_printable_decode($data); break; // QP
    }
    // 文字コード変換
    return mb_convert_encoding($data, 'UTF-8', 'UTF-8,ISO-2022-JP,SJIS,EUC-JP');
}

function decode_mime(string $str): string {
    $decoded = imap_mime_header_decode($str);
    $result  = '';
    foreach ($decoded as $d) {
        $charset = strtoupper($d->charset ?? 'UTF-8');
        $text    = $d->text;
        if ($charset !== 'UTF-8' && $charset !== 'DEFAULT') {
            $text = mb_convert_encoding($text, 'UTF-8', $charset);
        }
        $result .= $text;
    }
    return $result;
}

function classify_email(string $subject, string $body): array {
    $text = $subject . ' ' . $body;
    if (preg_match('/キャンセル|取り消し|中止|やめ/', $text))
        return ['label' => 'キャンセル',   'color' => '#ea4335'];
    if (preg_match('/変更|日程|スケジュール|別の日|振り替え/', $text))
        return ['label' => '日程変更',     'color' => '#fa7b17'];
    if (preg_match('/予約|申込|申し込み|オリエン|本講座|受講/', $text))
        return ['label' => '新規予約',     'color' => '#1a73e8'];
    if (preg_match('/[Zz]oom|ミーティング|リンク|接続/', $text))
        return ['label' => 'Zoom関連',     'color' => '#0f9d58'];
    if (preg_match('/料金|費用|値段|価格|支払|払い|無料/', $text))
        return ['label' => '料金・支払い', 'color' => '#9334e6'];
    return ['label' => '一般質問', 'color' => '#5f6368'];
}

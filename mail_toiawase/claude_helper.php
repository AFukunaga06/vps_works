<?php
require_once __DIR__ . '/config.php';

function generate_reply(string $subject, string $body, string $category): string {
    $body_trim = mb_substr(trim($body), 0, 1500);

    $prompt = "以下のメールに対する返信文を日本語で作成してください。\n\n"
            . "サービス名: " . APP_NAME . "\n"
            . "カテゴリ: {$category}\n"
            . "受信メール件名: {$subject}\n"
            . "受信メール本文:\n{$body_trim}\n\n"
            . "【要件】\n"
            . "- 丁寧で温かみのある文体\n"
            . "- 必要な情報を簡潔に伝える\n"
            . "- 署名は「" . APP_NAME . "」\n"
            . "- 返信文のみ出力（説明不要）";

    $payload = json_encode([
        'model'      => CLAUDE_MODEL,
        'max_tokens' => 1024,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $res      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return "返信文の生成に失敗しました（HTTP {$httpCode}）";
    $data = json_decode($res, true);
    return $data['content'][0]['text'] ?? '返信文を取得できませんでした';
}

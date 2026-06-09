<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (OPENAI_API_KEY === 'YOUR_API_KEY_HERE') {
    http_response_code(503);
    echo json_encode(['error' => 'APIキーが設定されていません。管理者にお問い合わせください。']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$question = trim($input['question'] ?? '');
$history  = $input['history'] ?? [];

if ($question === '') {
    http_response_code(400);
    echo json_encode(['error' => '質問を入力してください。']);
    exit;
}

if (mb_strlen($question) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => '質問は1000文字以内で入力してください。']);
    exit;
}

// システムプロンプト
$system = <<<PROMPT
あなたは「ABC○○法律事務所相談窓口」のAIアシスタントです。
法律に関する一般的な疑問や、専門家への相談前の整理を手伝います。

【対応できること】
- 法律用語の説明
- 一般的な法律知識（相続・離婚・契約・労働・借地借家など）の説明
- 相談前に整理しておくべきことのアドバイス
- 法テラスや弁護士会など公的相談窓口の案内

【必ず守ること】
- 個別案件への具体的な法的判断（「勝てる」「負ける」等の断定）は絶対にしない
- 代理交渉・訴訟対応・法律文書の作成は対応外と案内する
- 回答の末尾には必ず「※このAI回答は一般的な情報提供であり、法的アドバイスではありません。具体的な判断は専門家にご相談ください。」と添える
- 回答は日本語で、わかりやすく丁寧に
- 回答は500文字程度を目安に簡潔にまとめる
- 質問の内容が専門的すぎて回答が難しい場合、または適切な回答が見つからない場合は「この件については、直接ご予約のうえ弁護士にお尋ねください。」と案内する

相談者が専門家への相談が必要と思われる場合は、「ABC○○法律事務所相談窓口」での予約をご案内してください。
PROMPT;

// 会話履歴の組み立て（最大8往復まで保持）
$messages = [['role' => 'system', 'content' => $system]];
foreach (array_slice($history, -16) as $h) {
    if (isset($h['role'], $h['content']) && in_array($h['role'], ['user', 'assistant'])) {
        $messages[] = [
            'role'    => $h['role'],
            'content' => mb_substr($h['content'], 0, 2000),
        ];
    }
}
$messages[] = ['role' => 'user', 'content' => $question];

$payload = [
    'model'       => OPENAI_MODEL,
    'messages'    => $messages,
    'max_tokens'  => 1024,
    'temperature' => 0.7,
];

$ch = curl_init(OPENAI_API_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
    ],
]);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    http_response_code(500);
    echo json_encode(['error' => 'ネットワークエラーが発生しました。']);
    exit;
}

$data = json_decode($response, true);

if ($http_code !== 200 || !isset($data['choices'][0]['message']['content'])) {
    http_response_code(500);
    echo json_encode(["answer" => "この件については、直接ご予約のうえ弁護士にお尋ねください。"]);
    exit;
}

echo json_encode(['answer' => $data['choices'][0]['message']['content']]);

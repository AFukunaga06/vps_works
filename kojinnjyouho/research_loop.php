#!/usr/bin/env php
<?php
/**
 * 調査ループスクリプト
 * 使い方: php research_loop.php
 *
 * autoresearchのループ構造をベースに:
 * LOOP FOREVER:
 *   1. DBからpending件を取得
 *   2. Claude APIで調査
 *   3. 結果をDBに保存
 *   4. 待機してループ継続
 */

require_once __DIR__ . '/config.php';

// --- ユーティリティ ---

function log_msg(string $msg): void {
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] {$msg}\n";
    flush();
}

function build_prompt(array $req): string {
    $topic  = $req['topic'];
    $detail = $req['detail'] ?? '';

    $prompt = "あなたは優秀なリサーチャーです。以下のテーマについて、わかりやすく詳細に調査・解説してください。\n\n";
    $prompt .= "【調査テーマ】\n{$topic}\n";
    if ($detail) {
        $prompt .= "\n【補足・詳細】\n{$detail}\n";
    }
    $prompt .= "\n回答は日本語で、以下の構成でお願いします：\n";
    $prompt .= "1. 概要\n2. 主なポイント（箇条書き）\n3. 詳細説明\n4. まとめ\n";

    return $prompt;
}

// --- メインループ ---

log_msg("=== 調査ループ開始 ===");
log_msg("モデル: " . OPENAI_MODEL);
log_msg("Ctrl+C で停止");
log_msg("");

$iteration_global = 0;

while (true) {
    $db = get_db();

    // 1. pending件を1件取得（最も古い順）
    $stmt = $db->query("SELECT * FROM research_requests WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1");
    $req  = $stmt->fetch();

    if (!$req) {
        log_msg("待機中（pending件なし）... " . LOOP_INTERVAL_SEC . "秒後に再確認");
        sleep(LOOP_INTERVAL_SEC);
        continue;
    }

    $id = $req['id'];
    $iteration_global++;
    $iter = $req['iterations'] + 1;

    log_msg("--- Iteration {$iteration_global} | Request #{$id} ---");
    log_msg("テーマ: " . mb_strimwidth($req['topic'], 0, 80, '…'));
    log_msg("投稿者: " . ($req['submitter'] ?? '匿名'));

    // 2. ステータスを processing に変更
    $db->prepare("UPDATE research_requests SET status = 'processing', iterations = ? WHERE id = ?")
       ->execute([$iter, $id]);

    $prompt = build_prompt($req);
    $status = 'success';
    $result = '';

    // 3. Claude API 呼び出し
    try {
        log_msg("Claude API 呼び出し中...");
        $start  = microtime(true);
        $result = call_gpt($prompt);
        $elapsed = round(microtime(true) - $start, 1);
        log_msg("完了 ({$elapsed}秒)");

    } catch (RuntimeException $e) {
        $status = 'failed';
        $result = 'エラー: ' . $e->getMessage();
        log_msg("ERROR: " . $e->getMessage());
    }

    // 4. ログに記録
    $db->prepare("INSERT INTO research_log (request_id, iteration, prompt_used, result, status) VALUES (?,?,?,?,?)")
       ->execute([$id, $iter, $prompt, $result, $status]);

    // 5. 結果をDBに保存してステータス更新
    $new_status = ($status === 'success') ? 'completed' : 'failed';
    $db->prepare("UPDATE research_requests SET status = ?, result = ?, iterations = ?, completed_at = NOW() WHERE id = ?")
       ->execute([$new_status, $result, $iter, $id]);

    log_msg("Request #{$id} → {$new_status}");
    log_msg("");

    // 6. インターバル
    sleep(LOOP_INTERVAL_SEC);
}

# fuguAIチェス 対戦結果 誤判定バグ 調査メモ

最終更新: 2026-06-30（**解決・本番デプロイ済**）

## ✅ 結論と対応（2026-06-30）

### 原因の切り分け
「AIの勝ち」は `checkGameOver()` の **checkmate かつ turn==='w'** でしか出ない。
チェックメイトは**局面（FEN）だけで決まる純粋関数**で、表示直前に必ず `board.position(game.fen())`
しているため、「AIの勝ち」が出る時点で**表示中の盤は本当に白詰み**＝盤と結果の不一致では無い。
→ 残る可能性は「**ユーザーが意図しない手が global `game` に適用され、想定外の詰み局面へ進む**」の一点。
それを起こせる経路は次の2つだけだった:

1. **minimax が global `game` を破壊**: `search/minimaxMove` が共有 `game` を直接 move/undo していた。
   chess.js 0.10系は不正手で例外でなく null を返すため、万一 `move()` が空振りすると対の `undo()` が
   **直前の正規手を巻き戻し**、局面が静かにズレて誤って詰み判定し得る。
2. **fugu通信待ちの古い応答が残留**: medium の `await fetch`（最大30秒）中、`busy` はドラッグを止めるが
   **`newGame()` は未ガード**。古い応答が後から別局面へ適用される競合。

### 修正（`index.php` / 本番 `/var/www/html/chess_fugu/index.php`）
- minimax を **`new Chess(game.fen())` のクローン上だけで探索**（`search(g,…)`/`evaluate(g)` に引数化）。
  → global `game` は一切触らないので原因1を根絶。
- **ゲーム世代カウンタ `gameGen`** 導入。`aiMove` 開始時に `myGen` を記録し、`await` 後 `myGen!==gameGen`
  なら**古い応答を破棄**。`newGame()` で `gameGen++`。→ 原因2を根絶。
- AI手適用を **黒手番かつ合法手として通った時のみ**に限定（`game.turn()==='b'` && `move()!==null`）。
- `checkGameOver()` 冒頭で `board.position(game.fen())` を**強制再同期**してから判定。

### 検証
- ローカルで実 `chess.min.js` を読み込み、白ランダム vs 黒minimax(depth2) を **25局/1120手**シミュレート:
  **corruption=0・false checkmate=0・全25局が正当な詰みで終了**（`scratchpad/sim.js`）。
- `php -l` OK、本番へ scp デプロイ（旧版は `index.php.bak_20260630` に退避）、
  `https://chessfugu.afuku5906.com/` が HTTP 200 で**修正版JSを配信中**を確認。

> 注: 過去メモの「FALSE POSITIVE」検出は**三回同形ドロー**の取り違え（threefoldは履歴依存で
> FEN単体の `new Chess(fen)` では検出不可）。詰み判定そのものの誤りでは無かった。

---

（以下、当時の調査メモ・原文ママ）

最終更新: 2026-06-29（調査中・未解決）

## 症状（ユーザー報告）
- まだ対局途中なのに、ステータスに「**AIの勝ち**」と表示される。
- 対戦結果が正確でない。

対象: https://chessfugu.afuku5906.com （`/var/www/html/chess_fugu/`）

## 「AIの勝ち」が出る箇所（index.php）
`checkGameOver()` 内の **チェックメイト かつ 白(`turn==='w'`) の手番** ブランチのみ:
```js
if (game.in_checkmate()) {
  if (game.turn() === 'w') { result='lose'; setStatus('チェックメイト… AIの勝ち。'); } // ← ここ
  else { result='win'; setStatus('チェックメイト！ あなたの勝ち！'); }
}
```
※ `resign()`（投了ボタン）も「投了しました。AIの勝ち。」を出すが、今回は対象外と思われる。

`checkGameOver()` は冒頭で `if (!game.game_over()) return false;` とガードしているため、
**理屈上は `game.game_over()` が true の時しか発火しない**。

## ここまでの調査と判明事実

### 1. chess.js のAPIメソッドは正しい（既存chess_appと同一）
`/var/www/html/chess_app/index.php` の実績ある使い方と一致を確認:
`in_checkmate() / in_stalemate() / in_threefold_repetition() / insufficient_material() / in_draw() / in_check() / game_over() / turn()`
→ メソッド名の取り違えは無い。

### 2. chess.js の基本挙動は正常（Nodeで `chess.min.js` を直接evalして確認）
- 非プロモーション手に `promotion:'q'` を渡しても正常（`e2e4` → san "e4" を返す）。
- `game.moves({verbose:true})` の手オブジェクトを `game.move(obj)` に渡して可、`game.undo()` で局面が完全復元される（`fen === before` true）。
- `game_over()/in_checkmate()` は初期局面で false。

### 3. 結果の帰属ロジック自体は正しく見える
- 人間(白)が詰ませた → `turn==='b'` → 「あなたの勝ち」
- AI(黒)が詰ませた → `turn==='w'` → 「AIの勝ち」
- 盤面は各手の後に必ず `board.position(game.fen())` で同期しているので、
  game側が「白が詰み」なら盤面も詰みを表示しているはず。

### 4. タイミング/二重操作の確認
- `aiMove()` は冒頭で `busy=true`（awaitの前）。`onDragStart` は `busy` と `turn!=='w'` で人間操作をブロック。
  → fugu通信待ち中や150msの間に人間が二重に動かす経路は無さそう。

## 未解決・残る仮説
1. **minimax(上級)による状態破壊**: 例外が `search/minimaxMove` 内（move適用後〜undo前）で起きると、
   `aiMove` の `catch` で `randomMove()` にフォールバックするが、**global `game` に余分な手が適用されたまま**になり、
   局面が壊れて `game_over()` が誤発火する可能性。通常フローでは move/undo は均衡しており再現できていない。
2. **盤面(board)とgameの desync**: 何らかの経路で表示局面とgame内部が食い違い、
   ユーザーには途中に見えるがgame内部は詰み、という状態。現状コードでは同期しているはずで未特定。
3. ユーザーが実際に（特に上級=depth3で）正当に詰まされているだけ、の可能性も完全には排除できていない
   （ただしユーザーは「途中」と明言）。

## 実施しようとして中断した検証
- **Playwright(headless Chromium)で実ブラウザ再現**を試みた（`scratchpad/repro.js`）。
  - 方針: ページ内のグローバル関数（`game/board/legalUci/aiMove/checkGameOver/statusEl`）を
    `page.evaluate` から直接叩き、白ランダム合法手→AI応答を繰り返し、
    「AIの勝ち」表示時に `game_over()/in_checkmate()/turn` が実際に終局と整合するかを判定。
  - 不整合（`AIの勝ち` なのに `!over || !mate || turn!=='w'`）を **FALSE POSITIVE** として検出する作り。
  - **easy と hard を対象**（medium はfugu通信が遅いため後回し）。
  - 実行コマンド:
    ```
    export LD_LIBRARY_PATH="$HOME/chromedeps/usr/lib/x86_64-linux-gnu:$LD_LIBRARY_PATH"
    node /tmp/.../scratchpad/repro.js
    ```
  - ※この実行をユーザーが中断（ここでストップ）。

- Nodeでの大量シミュレーション（白ランダム vs 黒minimax）は depth3/depth2 とも
  **タイムアウト（minimaxが重い）**で結果取得に至らず。perfの問題で、バグ判定には未到達。

## 次にやること（再開時）
1. Playwright再現スクリプト `scratchpad/repro.js` を実行し、FALSE POSITIVE が出るか確認。
   - 出れば、その時の FEN と直前の手順から原因を特定。
   - medium(fugu) でも同様に1局回す（通信待ちを考慮しタイムアウト長め）。
2. 仮説1対策（防御的修正・どちらも入れる価値あり）:
   - `aiMove` の `catch` に入った場合、**`game` を壊さない**よう、minimaxは
     ローカルにクローンした `new Chess(game.fen())` 上で探索する（global gameを触らない）。
   - `checkGameOver()` 発火時、`board.position(game.fen())` を**強制再同期**してから判定/表示。
3. 修正後、Playwrightで easy/medium/hard 各複数局を回し、誤判定ゼロを確認。
4. 関連: チェスは現状サーバ側にルールエンジンが無く、勝敗はクライアント(chess.js)判定を信頼して保存。
   必要なら保存前にサーバ再検証も検討（別途）。

## 関連ファイル
- 本番: `/var/www/html/chess_fugu/{index.php,fugu_chess_api.php,stats.php,config.php}`
- ローカル原本: `/home/afky5/chess_fugu/php_deploy/`
- 再現スクリプト: `scratchpad/repro.js`（headless Chromium、`~/live2d_lip/node_modules/playwright` 使用）
- メモリ: `chess_fugu.md`, `othello_fugu.md`

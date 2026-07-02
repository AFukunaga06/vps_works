# fuguAI チェス（`chess_fugu`）

fugu（Sakana AI のLLM）と対戦できるブラウザチェス。難易度3段階・匿名成績記録付き。

- **本番URL**: https://chessfugu.afuku5906.com （SSL / HTTP→HTTPS強制）
- **姉妹アプリ**: [othello_fugu](../othello_fugu/)（同スタイルのオセロ版）

![chess_fugu](../screenshots/chess_fugu.png)

## 技術スタック

PHP 8 / MySQL / JavaScript（chess.js + chessboard.js + jQuery）

## 難易度3段階の設計（AIの使い分け）

| 難易度 | 思考エンジン | 実行場所 |
|---|---|---|
| 初級 | 合法手からランダム | クライアント（JS） |
| 中級 | **fugu API**（FEN＋UCI合法手リストをLLMに渡して選択・失敗時ランダムにフォールバック） | サーバ経由（PHP） |
| 上級 | **ミニマックス＋αβ枝刈り 深さ3**（駒得＋ピーススクエアテーブル評価） | クライアント（JS） |

- 難易度は `localStorage` に保存、切替時は新規対局
- ルール処理は全て chess.js（クライアント）。人間=白先手、AI=黒。プロモーションは自動クイーン

## 構成

```
chess_fugu/
├── index.php            UI＋AIロジック（minimax・fugu呼び出し・終局判定）
├── fugu_chess_api.php   move=fugu着手選択 / save=対局結果保存
├── config.php           DB接続・fugu APIキー取得
└── stats.php            通算・難易度別成績（勝率）・ランキング・直近30件
```

## DB（匿名成績記録）

MySQL `chess_fugu` / table `games`
（player_name, level enum(easy/medium/hard), result enum(win/lose/draw), moves, end_reason, created_at）

## セキュリティ

- **APIキーをコードに置かない**: 環境変数 or webroot外 `/var/www/secrets/` から読み込み
- `config.php`（DB接続情報）はリポジトリ方針により公開対象から除外
- PDO プリペアドステートメント / `htmlspecialchars` によるXSS対策

## 障害対応の記録（面接でも話せる事例）

「対局途中なのにAIの勝ちと表示される」誤判定バグを調査・修正した記録を
[DEBUG_対戦結果誤判定.md](DEBUG_対戦結果誤判定.md) に残しています。

- 原因1: minimax探索が共有ゲーム状態を move/undo で破壊し得る
- 原因2: fugu通信待ち中に新規対局すると古い非同期応答が適用される競合
- 対策: **クローン盤上での探索**＋**ゲーム世代カウンタで古い応答を破棄**
- 検証: 実エンジンで25局/1120手の自動シミュレーションを行い再発ゼロを確認

## 補足

盤面UIアセット（chess.min.js / chessboard.min.js / 駒画像）は [chess_app](../chess_app/) と共通のため本ディレクトリでは省略。

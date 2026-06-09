"""Claude API 呼び出し（条項リスク分析 / 契約要約）

仕様:
  - モデル: claude-sonnet-4-6
  - max_tokens: 1024
  - リトライ: 最大3回。最終的に JSON パース失敗なら risk_level='なし' で返す。
  - JSON のみを返させ、コードフェンス等は受け付けるが緩く正規化する。
"""
from __future__ import annotations

import json
import logging
import os
import re
import time
from typing import TypedDict

from anthropic import Anthropic

log = logging.getLogger("ai_server.claude")

MODEL = "claude-sonnet-4-6"
MAX_TOKENS = 1024
MAX_RETRIES = 3

# 条項分析用システムプロンプト
SYSTEM_CLAUSE = (
    "あなたは日本の契約書レビューを支援するアシスタントです。"
    "ユーザーから与えられた契約条項を、指定の立場（甲/乙）から分析し、"
    "厳密に下記の JSON のみを出力してください。"
    "前置き・コードフェンス・コメントは禁止。\n\n"
    "出力スキーマ:\n"
    "{\n"
    '  "risk_level":  "高" | "中" | "低" | "なし",\n'
    '  "risk_type":   "短いラベル（例: 損害賠償の免除）",\n'
    '  "issue":       "自社にとっての問題点を2〜3文で",\n'
    '  "suggestion":  "具体的な修正提案を1〜2文で",\n'
    '  "related_law": "関連法令（例: 民法415条）。無ければ空文字"\n'
    "}\n\n"
    "判定基準:\n"
    "- 自社（指定された甲または乙）が一方的に不利 → 高 または 中\n"
    "- 業界一般的・標準的で問題なし → なし\n"
    "- 軽微な修正余地 → 低\n"
    "本ツールは弁護士向けの「一次分析」用途であり、最終判断は弁護士が行う前提。"
)

_client: Anthropic | None = None


def _get_client() -> Anthropic:
    global _client
    if _client is None:
        key = os.environ.get("ANTHROPIC_API_KEY", "").strip()
        if not key or key.startswith("sk-ant-REPLACE"):
            raise RuntimeError(
                "ANTHROPIC_API_KEY が未設定です。ai_server/.env を確認してください。"
            )
        _client = Anthropic(api_key=key)
    return _client


class ClauseResult(TypedDict):
    risk_level: str
    risk_type: str
    issue: str
    suggestion: str
    related_law: str


_VALID_LEVELS = ("高", "中", "低", "なし")


def _extract_json(text: str) -> dict | None:
    """応答テキストから最初の JSON オブジェクトを取り出す。失敗時 None。"""
    s = text.strip()
    # コードフェンス除去
    s = re.sub(r"^```(?:json)?\s*", "", s)
    s = re.sub(r"\s*```$", "", s)
    try:
        return json.loads(s)
    except Exception:
        pass
    m = re.search(r"\{[\s\S]*\}", s)
    if not m:
        return None
    try:
        return json.loads(m.group(0))
    except Exception:
        return None


def _default_failure(reason: str) -> ClauseResult:
    return {
        "risk_level":  "なし",
        "risk_type":   "",
        "issue":       reason,
        "suggestion":  "",
        "related_law": "",
    }


def analyze_clause(
    clause_text: str,
    contract_type: str,
    party_side: str,
    clause_number: str = "",
    clause_title: str = "",
) -> ClauseResult:
    """条項1つを分析。リトライ込み。JSON 不可なら risk_level='なし' で返す。"""
    user = (
        f"【契約類型】{contract_type or '不明'}\n"
        f"【自社の立場】{party_side or '甲'}\n"
        f"【条項番号】{clause_number}\n"
        f"【条項タイトル】{clause_title}\n"
        f"【条項本文】\n{clause_text}\n"
    )

    last_err: str = ""
    for attempt in range(1, MAX_RETRIES + 1):
        try:
            resp = _get_client().messages.create(
                model=MODEL,
                max_tokens=MAX_TOKENS,
                system=SYSTEM_CLAUSE,
                messages=[{"role": "user", "content": user}],
            )
            text = "".join(getattr(b, "text", "") for b in resp.content)
            data = _extract_json(text)
            if data is None:
                last_err = "JSON parse failed"
                log.warning(
                    "clause %s: non-JSON response (attempt %d/%d)",
                    clause_number, attempt, MAX_RETRIES,
                )
                continue

            rl = (data.get("risk_level") or "").strip()
            if rl not in _VALID_LEVELS:
                rl = "なし"
            return {
                "risk_level":  rl,
                "risk_type":   str(data.get("risk_type")   or "").strip(),
                "issue":       str(data.get("issue")       or "").strip(),
                "suggestion":  str(data.get("suggestion")  or "").strip(),
                "related_law": str(data.get("related_law") or "").strip(),
            }
        except Exception as e:
            last_err = str(e)
            log.warning(
                "clause %s: claude error (attempt %d/%d): %s",
                clause_number, attempt, MAX_RETRIES, e,
            )
            if attempt < MAX_RETRIES:
                time.sleep(min(2 ** attempt, 8))

    log.error("clause %s: giving up after %d retries (%s)",
              clause_number, MAX_RETRIES, last_err)
    return _default_failure(f"AI分析に失敗しました（{last_err}）。")


def summarize_contract(text: str, contract_type: str, party_side: str) -> str:
    """契約書全体の要旨を2〜3文で返す。失敗時は空文字。"""
    prompt = (
        f"以下は日本の契約書本文（先頭抜粋）です。{party_side or '甲'} の立場で見た"
        "ときの要旨を、2〜3文の日本語で簡潔に説明してください。出力は本文のみ。\n"
        f"---\n契約類型: {contract_type or '不明'}\n---\n本文:\n{text}\n"
    )
    for attempt in range(1, MAX_RETRIES + 1):
        try:
            resp = _get_client().messages.create(
                model=MODEL,
                max_tokens=512,
                messages=[{"role": "user", "content": prompt}],
            )
            return "".join(getattr(b, "text", "") for b in resp.content).strip()
        except Exception as e:
            log.warning("summarize attempt %d/%d: %s", attempt, MAX_RETRIES, e)
            if attempt < MAX_RETRIES:
                time.sleep(min(2 ** attempt, 8))
    return ""

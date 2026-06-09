"""契約条項分割

「第◯条」を区切りにして契約書全文を条項単位に分割する。
全角数字・漢数字・「の枝番」（例: 第1条の2）にも対応。
"""
from __future__ import annotations

import re
from typing import TypedDict

# 第1条 / 第10条 / 第十条 / 第１条 / 第1条の2 にマッチ
CLAUSE_RE = re.compile(
    r"(第[\d０-９零〇一二三四五六七八九十百千]+条(?:の[\d０-９]+)?)"
)

# 条タイトル抽出: 行頭の "（...）" を最大40文字まで拾う
TITLE_RE = re.compile(r"^[（(]([^）)\n]{1,40})[）)]\s*")


class Clause(TypedDict):
    number: str   # 例: "第8条"
    title: str    # 例: "損害賠償"（取れなければ ""）
    text: str     # 条項本文（タイトルを除いた残り）


def split_clauses(text: str) -> list[Clause]:
    """テキストを「第◯条」単位で分割。

    - 第◯条 が 1 つも無ければ、全文を 1 つの条項として返す（タイトル空）。
    - 各条項は、本文の先頭が "（XXX）" 形式ならそれを title として抽出する。
    """
    if not text:
        return []

    parts = CLAUSE_RE.split(text)
    # parts = [前文, "第1条", 本文1, "第2条", 本文2, ...]
    if len(parts) < 3:
        return [{"number": "", "title": "", "text": text.strip()}]

    out: list[Clause] = []
    for i in range(1, len(parts), 2):
        number = parts[i].strip()
        body = (parts[i + 1] if i + 1 < len(parts) else "").strip()

        title = ""
        m = TITLE_RE.match(body)
        if m:
            title = m.group(1).strip()
            body = body[m.end():].strip()

        out.append({"number": number, "title": title, "text": body})

    return out

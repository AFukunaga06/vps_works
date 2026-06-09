<?php
/**
 * 弁護士費用 自動計算（旧日弁連報酬基準 ベース）
 *
 *   2004年に廃止された旧日弁連報酬等基準。現在は各事務所が自由に設定可能だが
 *   慣行的にこの基準を参考にしている事務所が多いため、デフォルト基準として採用。
 *   将来テナント別カスタマイズが必要になれば lc_fee_tables を追加する想定。
 */

if (!function_exists('lc_calc_retainer')) {
    /**
     * 着手金を計算
     * @param int $benefit_yen 経済的利益（円）
     * @return array{amount:int, rate_text:string}
     */
    function lc_calc_retainer(int $benefit_yen): array {
        $benefit_yen = max(0, $benefit_yen);
        if ($benefit_yen === 0) {
            return ['amount' => 100000, 'rate_text' => '最低額'];
        }
        if ($benefit_yen <= 3_000_000) {
            $amt = (int)round($benefit_yen * 0.08);
            return ['amount' => max($amt, 100000), 'rate_text' => '8%'];
        }
        if ($benefit_yen <= 30_000_000) {
            $amt = (int)round($benefit_yen * 0.05) + 90_000;
            return ['amount' => $amt, 'rate_text' => '5% + 9万円'];
        }
        if ($benefit_yen <= 300_000_000) {
            $amt = (int)round($benefit_yen * 0.03) + 690_000;
            return ['amount' => $amt, 'rate_text' => '3% + 69万円'];
        }
        $amt = (int)round($benefit_yen * 0.02) + 3_690_000;
        return ['amount' => $amt, 'rate_text' => '2% + 369万円'];
    }
}

if (!function_exists('lc_calc_success')) {
    /**
     * 報酬金を計算（経済的利益＝想定回収額として）
     * @param int $benefit_yen
     * @return array{amount:int, rate_text:string}
     */
    function lc_calc_success(int $benefit_yen): array {
        $benefit_yen = max(0, $benefit_yen);
        if ($benefit_yen === 0) {
            return ['amount' => 0, 'rate_text' => '—'];
        }
        if ($benefit_yen <= 3_000_000) {
            $amt = (int)round($benefit_yen * 0.16);
            return ['amount' => $amt, 'rate_text' => '16%'];
        }
        if ($benefit_yen <= 30_000_000) {
            $amt = (int)round($benefit_yen * 0.10) + 180_000;
            return ['amount' => $amt, 'rate_text' => '10% + 18万円'];
        }
        if ($benefit_yen <= 300_000_000) {
            $amt = (int)round($benefit_yen * 0.06) + 1_380_000;
            return ['amount' => $amt, 'rate_text' => '6% + 138万円'];
        }
        $amt = (int)round($benefit_yen * 0.04) + 7_380_000;
        return ['amount' => $amt, 'rate_text' => '4% + 738万円'];
    }
}

if (!function_exists('lc_calc_quote')) {
    /**
     * 見積を一括計算して全項目を返す（保存・表示共通）
     */
    function lc_calc_quote(
        int $benefit_yen,
        int $consultation_yen = 5500,
        int $expense_yen = 0,
        float $tax_rate = 0.10
    ): array {
        $r = lc_calc_retainer($benefit_yen);
        $s = lc_calc_success($benefit_yen);
        $subtotal = $r['amount'] + $s['amount'] + $consultation_yen + $expense_yen;
        $tax = (int)round($subtotal * $tax_rate);
        $total = $subtotal + $tax;
        return [
            'retainer_yen'       => $r['amount'],
            'retainer_rate_text' => $r['rate_text'],
            'success_yen'        => $s['amount'],
            'success_rate_text'  => $s['rate_text'],
            'consultation_yen'   => $consultation_yen,
            'expense_yen'        => $expense_yen,
            'subtotal_yen'       => $subtotal,
            'tax_yen'            => $tax,
            'total_yen'          => $total,
        ];
    }
}

if (!function_exists('lc_quote_number')) {
    /**
     * 見積番号 Q-YYYYMMDD-{id4桁ゼロ埋め} を生成
     */
    function lc_quote_number(int $quote_id, ?string $created_at = null): string {
        $d = $created_at ? strtotime($created_at) : time();
        return 'Q-' . date('Ymd', $d) . '-' . str_pad((string)$quote_id, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('lc_quote_status_label')) {
    function lc_quote_status_label(string $s): string {
        return [
            'draft'    => '下書き',
            'sent'     => '送付済',
            'accepted' => '承諾',
            'rejected' => '辞退',
            'canceled' => 'キャンセル',
        ][$s] ?? $s;
    }

    function lc_quote_status_badge(string $s): string {
        $cls = [
            'draft'    => 'secondary',
            'sent'     => 'info',
            'accepted' => 'success',
            'rejected' => 'warning',
            'canceled' => 'dark',
        ][$s] ?? 'secondary';
        return '<span class="badge bg-' . $cls . '">' . htmlspecialchars(lc_quote_status_label($s), ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

<?php
/**
 * 業種（vertical）別のマスタ設定
 *  - ブランド名・サブタイトル
 *  - 案件種別（case_type）の選択肢
 *  - 用語の差替え（依頼者→顧問先 等）
 *  - 案件分野アイコン
 *
 * 利用側:
 *   $v = lc_vertical_config(lc_current_tenant()['vertical'] ?? 'lawyer');
 *   $v['label']        // 弁護士事務所
 *   $v['client_label'] // 依頼者
 *   $v['case_types']   // ['divorce'=>'離婚', ...]
 */

function lc_vertical_config(string $vertical = 'lawyer'): array {
    $configs = lc_all_verticals();
    return $configs[$vertical] ?? $configs['lawyer'];
}

function lc_all_verticals(): array {
    return [
        'lawyer' => [
            'key'    => 'lawyer',
            'label'  => '弁護士事務所',
            'short'  => '弁護士',
            'brand'  => 'LegalDesk',
            'sub'    => '依頼者・案件管理システム',
            'role_lawyer' => '弁護士',
            'client_label' => '依頼者',
            'case_label'   => '案件',
            'icon'   => 'briefcase-fill',
            'case_types' => [
                'divorce'     => '離婚',
                'inheritance' => '相続',
                'criminal'    => '刑事',
                'civil'       => '民事',
                'labor'       => '労働',
                'real_estate' => '不動産',
                'corporate'   => '企業法務',
                'debt'        => '債務整理',
                'other'       => 'その他',
            ],
            'lp_headline' => '紙とExcelの案件管理から、もう卒業しませんか。',
            'lp_sub'      => '個人弁護士・小規模事務所のための、シンプルな依頼者・案件管理CRM。',
        ],

        'tax_accountant' => [
            'key'    => 'tax_accountant',
            'label'  => '税理士事務所',
            'short'  => '税理士',
            'brand'  => 'TaxDesk',
            'sub'    => '顧問先・税務案件管理システム',
            'role_lawyer' => '税理士',
            'client_label' => '顧問先',
            'case_label'   => '案件',
            'icon'   => 'calculator-fill',
            'case_types' => [
                'corp_tax'      => '法人税',
                'income_tax'    => '所得税',
                'inheritance_tax' => '相続税',
                'consumption'   => '消費税',
                'year_end'      => '年末調整',
                'monthly'       => '月次顧問',
                'closing'       => '決算',
                'tax_audit'     => '税務調査',
                'other'         => 'その他',
            ],
            'lp_headline' => '顧問先の月次・決算・期限を、ひとつの画面で。',
            'lp_sub'      => '税理士事務所のための、顧問先・申告期限・月次タスク管理CRM。',
        ],

        'judicial_scrivener' => [
            'key'    => 'judicial_scrivener',
            'label'  => '司法書士事務所',
            'short'  => '司法書士',
            'brand'  => 'JudicialDesk',
            'sub'    => '依頼者・登記案件管理システム',
            'role_lawyer' => '司法書士',
            'client_label' => '依頼者',
            'case_label'   => '案件',
            'icon'   => 'building-fill',
            'case_types' => [
                'real_estate_reg' => '不動産登記',
                'commercial_reg'  => '商業登記',
                'inheritance_reg' => '相続登記',
                'company_setup'   => '会社設立',
                'debt'            => '債務整理',
                'guardian'        => '成年後見',
                'litigation'      => '簡裁訴訟',
                'other'           => 'その他',
            ],
            'lp_headline' => '登記案件と決済期日を、見落とさない仕組みに。',
            'lp_sub'      => '司法書士事務所のための、依頼者・登記案件・期日管理CRM。',
        ],

        'administrative_scrivener' => [
            'key'    => 'administrative_scrivener',
            'label'  => '行政書士事務所',
            'short'  => '行政書士',
            'brand'  => 'AdminDesk',
            'sub'    => '依頼者・許認可案件管理システム',
            'role_lawyer' => '行政書士',
            'client_label' => '依頼者',
            'case_label'   => '案件',
            'icon'   => 'file-earmark-text-fill',
            'case_types' => [
                'construction'   => '建設業許可',
                'visa'           => '在留資格',
                'inheritance'    => '相続',
                'will'           => '遺言',
                'company_setup'  => '会社設立',
                'waste'          => '産廃許可',
                'car_garage'     => '車庫証明',
                'contract'       => '契約書作成',
                'other'          => 'その他',
            ],
            'lp_headline' => '許認可・期限・必要書類を、案件ごとに一元管理。',
            'lp_sub'      => '行政書士事務所のための、許認可案件・進捗管理CRM。',
        ],
    ];
}

/**
 * 現在テナントの vertical 設定を取得（未指定なら lawyer）。
 */
function lc_current_vertical(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $t = function_exists('lc_current_tenant') ? lc_current_tenant() : null;
    $key = $t['vertical'] ?? 'lawyer';
    $cache = lc_vertical_config($key);
    return $cache;
}

/**
 * 案件種別マップ（業種別）。
 */
function lc_case_type_map(?string $vertical = null): array {
    if ($vertical) return lc_vertical_config($vertical)['case_types'];
    return lc_current_vertical()['case_types'];
}

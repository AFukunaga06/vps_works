// =====================================================================
// 公開予約ページ用 — 最小限のJS
//   * 日付セルクリックで該当アンカーへスムーズスクロール
//   * 予約フォーム送信前の確認
// =====================================================================
(function () {
    'use strict';

    // 日付セルのリンクは index.php?...&d=YYYY-MM-DD#slots に飛ぶ。
    // 同一ページ内で読み直しが発生するが、#slots アンカーで該当セクションが見える位置に来る。
    document.addEventListener('DOMContentLoaded', function () {

        // 確認フォームの送信前に二重送信を抑制
        var form = document.querySelector('.public-body form.form-vert');
        if (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = '送信中...';
                }
            });
        }

        // スロットセクションがあれば自動でスクロール
        if (window.location.hash === '#slots') {
            var sec = document.getElementById('slots');
            if (sec) sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
})();

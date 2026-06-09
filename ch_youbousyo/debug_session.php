<?php
session_start();
if (isset(['set'])) {
    ['logged_in'] = true;
    echo 'セッションセット完了。<a href=debug_session.php>確認する</a>';
} else {
    echo 'logged_in: ' . (isset(['logged_in']) ? 'あり' : 'なし') . '<br>';
    echo 'session_id: ' . session_id() . '<br>';
    echo '<a href=debug_session.php?set=1>セッションをセット</a>';
}

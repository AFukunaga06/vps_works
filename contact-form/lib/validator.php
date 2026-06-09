<?php

function validate_fields(array $post, array $fields): array
{
    $errors = [];
    $data   = [];

    foreach ($fields as $field) {
        $name  = $field['name'];
        $label = $field['label'];
        $type  = $field['type'];
        $req   = $field['required'] ?? false;
        $max   = $field['max'] ?? 1000;

        if ($type === 'checkbox') {
            $value = isset($post[$name]) ? (array)$post[$name] : [];
        } else {
            $value = isset($post[$name]) ? trim($post[$name]) : '';
        }

        // 必須チェック
        if ($req) {
            $empty = ($type === 'checkbox') ? empty($value) : ($value === '');
            if ($empty) {
                $errors[$name] = $label . 'は必須項目です。';
                $data[$name]   = $value;
                continue;
            }
        }

        // 文字数チェック
        if ($type !== 'checkbox' && mb_strlen($value) > $max) {
            $errors[$name] = $label . 'は' . $max . '文字以内で入力してください。';
            $data[$name]   = $value;
            continue;
        }

        // 形式チェック
        if ($value !== '' && $type === 'email') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = 'メールアドレスの形式が正しくありません。';
            }
        }

        if ($value !== '' && $type === 'tel') {
            if (!preg_match('/^[\d\-\+\(\)\s]{7,20}$/', $value)) {
                $errors[$name] = '電話番号の形式が正しくありません。';
            }
        }

        // select/radioの選択肢チェック
        if (in_array($type, ['select', 'radio']) && $value !== '') {
            $options = $field['options'] ?? [];
            if (!in_array($value, $options, true)) {
                $errors[$name] = $label . 'の値が不正です。';
            }
        }

        $data[$name] = $value;
    }

    return ['errors' => $errors, 'data' => $data];
}

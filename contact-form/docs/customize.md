# お問い合わせフォーム カスタマイズマニュアル

## フォーム項目の追加・変更

`config.php` の `FORM_FIELDS` 配列を編集するだけです。
他のファイルは一切触る必要はありません。

---

## 項目の種類（type）と書き方

### テキスト入力（1行）
```php
['name' => 'company', 'label' => '会社名', 'type' => 'text', 'required' => false, 'max' => 100],
```

### メールアドレス
```php
['name' => 'email', 'label' => 'メールアドレス', 'type' => 'email', 'required' => true, 'max' => 100],
```

### 電話番号
```php
['name' => 'tel', 'label' => '電話番号', 'type' => 'tel', 'required' => false, 'max' => 20],
```

### 複数行テキスト
```php
['name' => 'message', 'label' => '内容', 'type' => 'textarea', 'required' => true, 'max' => 2000],
```

### プルダウン（選択）
```php
['name' => 'subject', 'label' => '種別', 'type' => 'select', 'required' => true,
 'options' => ['選択肢A', '選択肢B', '選択肢C']],
```

### ラジオボタン
```php
['name' => 'gender', 'label' => '性別', 'type' => 'radio', 'required' => false,
 'options' => ['男性', '女性', '回答しない']],
```

### チェックボックス（複数選択）
```php
['name' => 'interest', 'label' => '興味のある分野', 'type' => 'checkbox', 'required' => false,
 'options' => ['Web制作', 'システム開発', 'デザイン', 'その他']],
```

### 日付
```php
['name' => 'preferred_date', 'label' => 'ご希望日', 'type' => 'date', 'required' => false],
```

---

## 項目を削除する

`FORM_FIELDS` 配列から該当の行を削除するだけです。

---

## 必須・任意の切り替え

```php
'required' => true,   // 必須
'required' => false,  // 任意
```

---

## スパム対策（reCAPTCHA v3）を有効にする

1. Google reCAPTCHA サイトでサイトキーと秘密鍵を取得
2. `config.php` を編集：

```php
define('RECAPTCHA_ENABLED',  true);
define('RECAPTCHA_SITE_KEY', 'ここにサイトキー');
define('RECAPTCHA_SECRET',   'ここに秘密鍵');
```

---

## デザインの変更

`css/form.css` を編集します。
主なカスタマイズポイント：

| 変数/セレクタ | 変更内容 |
|---|---|
| `.btn-primary { background }` | ボタンの色 |
| `.form-wrap { max-width }` | フォームの横幅 |
| `body { font-family }` | フォント |
| `.form-title { color }` | タイトル色 |

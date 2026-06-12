# HXFE — Code-First Forms マニュアル

**バージョン 1.3.4** | 最終更新: 2026-06-03

> このマニュアルは **Smart Manual Hub (SMH)** でそのまま閲覧できます。  
> 各見出しのアンカーリンクから目的のセクションに直接ジャンプできます。

---

## 目次 {#toc}

### 基本編
- [HXFEとは — CF7との違い](#hxfeとは)
- [インストール](#インストール)
- [5分で最初のフォームを作る](#最初のフォーム)
- [フィールドタイプ一覧](#フィールドタイプ)

### メール編
- [管理者宛メールの設定](#管理者メール)
- [自動返信メールの設定](#自動返信)
- [送信先をカテゴリで切り替える](#送信先切り替え)
- [SMTP設定（Gmail / SendGrid など）](#smtp)

### 応用編
- [ステップフォーム（複数ページ）](#ステップフォーム)
- [条件分岐（show_if / required_if）](#条件分岐)
- [ステップのスキップ](#ステップスキップ)
- [reCAPTCHA（スパム対策）](#recaptcha)
- [プライバシーポリシー同意](#プライバシー)

### カスタマイズ編
- [デザインのカスタマイズ](#デザイン)
- [外部CSSファイルの読み込み](#外部css)
- [iframe で別サイトに埋め込む](#iframe)

### リファレンス
- [スキーマ全項目一覧](#リファレンス)
- [よくある質問](#faq)

---

## HXFEとは — CF7との違い {#hxfeとは}

HXFEは **PHPのコード（配列）でフォームを定義する** WordPressフォームプラグインです。

CF7・WPForms・Gravity Forms などの「GUI型」プラグインとは設計思想が根本的に異なります。

### なぜコードで定義するのか？

| 比較項目 | CF7・WPForms（GUI型） | **HXFE（コード型）** |
|---|---|---|
| フォームの定義場所 | 管理画面 → DBに保存 | `functions.php` に記述 |
| Gitでの管理 | ❌ DBに保存されるため差分が取れない | ✅ コードとして差分管理できる |
| サイト移行時 | ❌ DBを別途移行しないとフォームが消える | ✅ コードなので自動的に引き継ぎ |
| 動的な選択肢 | ❌ GUI上での手動入力が必要 | ✅ DBからプログラムで生成できる |
| 条件分岐 | △ GUI上で設定（複雑になりがち） | ✅ PHPで自由に記述できる |

### HXFEが向いている場面

- 開発チームが運用するサイト（Git管理しているプロジェクト）
- ステージング → 本番へのデプロイを繰り返す環境
- 投稿・商品など DB のデータをフォームの選択肢にしたい
- ステップフォーム・条件分岐など、凝ったフォームが必要

### HXFEに向いていない場面

- 非エンジニアがフォームを自分で作りたい → CF7・WPForms を使う
- フォームの送信データをWordPressの管理画面で確認したい → Gravity Forms を使う

[▲ 目次に戻る](#toc)

---

## インストール {#インストール}

### 手順

1. ZIPファイルをダウンロード
2. WordPress管理画面 → **プラグイン → 新規追加 → プラグインのアップロード**
3. ZIPを選択してインストール → 有効化

### 有効化後に追加されるもの

- **設定 → Form Engine** — reCAPTCHA・SMTP・iframeの設定ページ

### 最低要件

| 項目 | 要件 |
|---|---|
| WordPress | 6.0 以上 |
| PHP | 7.4 以上 |
| 必須設定 | 特になし（すぐ使える） |

[▲ 目次に戻る](#toc)

---

## 5分で最初のフォームを作る {#最初のフォーム}

お問い合わせフォームを例に、最小構成を作ります。

### Step 1 — functions.php にスキーマを追加

テーマの `functions.php`（または独自プラグイン）に以下を追加します。

```php
add_filter( 'hxfe_schemas', function( $schemas ) {

    $schemas['contact'] = [

        // ── 基本設定 ──────────────────────────────────────
        'id'      => 'contact',            // フォームID（英数字とハイフンのみ）
        'to'      => 'admin@example.com',  // 送信先メールアドレス
        'subject' => 'お問い合わせ: {name}', // 件名（{フィールドキー}で差し込み可能）

        // ── フィールド定義 ──────────────────────────────────
        'fields'  => [
            [ 'key' => 'name',  'type' => 'text',     'label' => 'お名前',           'required' => true ],
            [ 'key' => 'email', 'type' => 'email',    'label' => 'メールアドレス',   'required' => true ],
            [ 'key' => 'body',  'type' => 'textarea', 'label' => 'お問い合わせ内容', 'required' => true ],
            [ 'key' => 'hp',    'type' => 'honeypot' ], // ← スパム対策。必ず入れること
        ],
    ];

    return $schemas; // ← 必ず return する
});
```

> **ポイント:** `'id'` は固定ページのURLスラッグのように、サイト内でユニークな英数字にしてください。

### Step 2 — ショートコードを投稿・固定ページに貼る

```
[hxfe_form id="contact"]
```

### Step 3 — 完成 🎉

フォームが表示され、**入力 → 確認 → 完了** の3ステップが自動的に機能します。

### 動作の確認ポイント

- [ ] フォームが表示されるか
- [ ] 必須チェックが動作するか
- [ ] 送信後に管理者宛メールが届くか
- [ ] 完了画面が表示されるか

[▲ 目次に戻る](#toc)

---

## フィールドタイプ一覧 {#フィールドタイプ}

### text — テキスト入力 {#field-text}

```php
[
    'key'         => 'name',
    'type'        => 'text',
    'label'       => 'お名前',
    'required'    => true,
    'placeholder' => '例: 山田 太郎',  // 入力欄のヒント文字
    'maxlength'   => 100,              // 最大文字数
]
```

---

### email — メールアドレス {#field-email}

```php
[ 'key' => 'email', 'type' => 'email', 'label' => 'メールアドレス', 'required' => true ]
```

> `@` を含む形式かどうかのバリデーションが自動で行われます。

---

### textarea — 複数行テキスト {#field-textarea}

```php
[
    'key'       => 'body',
    'type'      => 'textarea',
    'label'     => 'お問い合わせ内容',
    'required'  => true,
    'rows'      => 6,     // 表示行数（デフォルト: 4）
    'maxlength' => 2000,  // 最大文字数
]
```

---

### select — プルダウン選択 {#field-select}

```php
[
    'key'     => 'category',
    'type'    => 'select',
    'label'   => 'お問い合わせの種類',
    'required'=> true,
    'options' => [
        [ 'value' => '',        'label' => '--- 選択してください ---' ],
        [ 'value' => 'product', 'label' => '製品について' ],
        [ 'value' => 'support', 'label' => 'サポート' ],
        [ 'value' => 'other',   'label' => 'その他' ],
    ],
]
```

#### 応用: DBから選択肢を動的生成

PHPで書けるので、DBの内容をそのまま選択肢にできます。**フォームを変更しなくても、投稿を追加・削除するだけで自動反映されます。**

```php
// 例①: 投稿タイプ「product」の全件をプルダウンに
'options' => array_merge(
    [ [ 'value' => '', 'label' => '--- 選択してください ---' ] ],
    array_map(
        fn( $p ) => [ 'value' => (string) $p->ID, 'label' => $p->post_title ],
        get_posts( [ 'post_type' => 'product', 'numberposts' => -1 ] )
    )
),

// 例②: カスタム投稿「seminar」の最新10件を日付付きで表示
'options' => array_merge(
    [ [ 'value' => '', 'label' => '--- 選択してください ---' ] ],
    array_map(
        fn( $p ) => [
            'value' => (string) $p->ID,
            'label' => get_the_date( 'Y/m/d', $p ) . ' ' . $p->post_title,
        ],
        get_posts( [
            'post_type'   => 'seminar',
            'numberposts' => 10,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'post_status' => 'publish',
        ] )
    )
),

// 例③: タクソノミー「event_category」の一覧をラジオボタンに
'options' => array_map(
    fn( $t ) => [ 'value' => (string) $t->term_id, 'label' => $t->name ],
    get_terms( [ 'taxonomy' => 'event_category', 'hide_empty' => false ] )
),

// 例④: ACFのオプションページの値を選択肢に
'options' => array_map(
    fn( $item ) => [ 'value' => $item['id'], 'label' => $item['name'] ],
    get_field( 'available_courses', 'option' ) ?? []
),
```

GUIプラグインでは「手動で選択肢を入力・更新」する必要がありますが、HXFEはDBと直結しているので更新不要です。


---

### checkbox — チェックボックス {#field-checkbox}

```php
[
    'key'      => 'agree',
    'type'     => 'checkbox',
    'label'    => '利用規約に同意する',
    'required' => true,  // チェックしないと送信できない
]
```

---

### honeypot — スパム対策（必須） {#field-honeypot}

```php
[ 'key' => 'hp', 'type' => 'honeypot' ]
```

> ⚠️ **全てのフォームに必ず追加してください。**  
> 画面上には表示されませんが、ボットが自動的にこのフィールドを埋めるため、スパム送信を自動ブロックできます。

---

### recaptcha — Google reCAPTCHA {#field-recaptcha}

→ 詳細は [reCAPTCHA セクション](#recaptcha) を参照

---

### privacy — プライバシーポリシー同意 {#field-privacy}

→ 詳細は [プライバシーポリシー同意 セクション](#プライバシー) を参照

[▲ 目次に戻る](#toc)

---

## 管理者宛メールの設定 {#管理者メール}

### 基本設定

```php
$schemas['contact'] = [
    'id'      => 'contact',
    'to'      => 'admin@example.com',      // 送信先
    'subject' => 'お問い合わせ: {name}',   // 件名（{key}で入力値を差し込み）
    ...
];
```

### 件名の差し込み変数

件名に `{フィールドキー}` と書くと、そのフィールドの入力値が差し込まれます。

```php
'subject' => '[{category}] {name} 様からのお問い合わせ',
// → 例: [製品について] 山田太郎 様からのお問い合わせ
```

### 管理者通知をオフにする

```php
'admin_notify' => false,  // 管理者宛メールを送らない
```

### BCC

```php
'bcc' => 'archive@example.com',
// 複数はカンマ区切り
'bcc' => 'archive@example.com, backup@example.com',
```

[▲ 目次に戻る](#toc)

---

## 自動返信メールの設定 {#自動返信}

送信者（ユーザー）に自動返信メールを送る設定です。

```php
$schemas['contact'] = [
    'id'      => 'contact',
    'to'      => 'admin@example.com',
    'subject' => 'お問い合わせ: {name}',

    // ── 自動返信の設定 ──────────────────────────────────────
    'reply_to_field'      => 'email',   // 返信先に使うフィールドのキー
    'autoreply_subject'   => '【{site_name}】お問い合わせを受け付けました',
    'autoreply_body'      => "{name} 様\n\nお問い合わせありがとうございます。\n内容を確認の上、担当者よりご連絡いたします。\n\n---\n■ お問い合わせ内容\n{body}",
    'autoreply_from'      => 'noreply@example.com',
    'autoreply_from_name' => 'サポートチーム',

    'fields' => [ ... ],
];
```

### 自動返信の本文で使える変数

| 変数 | 内容 |
|---|---|
| `{name}` | nameフィールドの入力値 |
| `{email}` | emailフィールドの入力値 |
| `{body}` | bodyフィールドの入力値 |
| `{site_name}` | サイト名（WordPressの設定から自動取得） |
| `{フィールドキー}` | 任意のフィールドの入力値 |

[▲ 目次に戻る](#toc)

---

## 送信先をカテゴリで切り替える {#送信先切り替え}

お問い合わせの種類によって担当者（送信先メール）を変えたい場合に使います。

```php
$schemas['contact'] = [
    'id' => 'contact',

    // to_rules: 条件に一致した最初のルールの 'to' が使われる
    'to_rules' => [
        [ 'when' => [ 'category', '==', 'sales' ],   'to' => 'sales@example.com' ],
        [ 'when' => [ 'category', '==', 'support' ], 'to' => 'support@example.com' ],
        [ 'when' => 'default',                        'to' => 'info@example.com' ],  // どれにも一致しない場合
    ],

    'fields' => [
        [
            'key'     => 'category',
            'type'    => 'select',
            'label'   => 'お問い合わせの種類',
            'options' => [
                [ 'value' => 'sales',   'label' => '営業・購入について' ],
                [ 'value' => 'support', 'label' => 'サポート・不具合' ],
                [ 'value' => 'other',   'label' => 'その他' ],
            ],
        ],
        ...
    ],
];
```

件名も同様に切り替えられます。

```php
'subject_rules' => [
    [ 'when' => [ 'category', '==', 'sales' ],   'subject' => '【営業】{name}様からのお問い合わせ' ],
    [ 'when' => [ 'category', '==', 'support' ], 'subject' => '【サポート】{name}様からのお問い合わせ' ],
    [ 'when' => 'default',                        'subject' => 'お問い合わせ: {name}' ],
],
```

[▲ 目次に戻る](#toc)

---

## SMTP設定（Gmail / SendGrid など） {#smtp}

WordPressのデフォルトのメール送信（PHP mail関数）は届かないことが多いため、SMTPの設定を推奨します。

**設定場所:** 設定 → Form Engine → SMTP Settings

### Gmail の設定手順

| 項目 | 入力値 |
|---|---|
| Provider | Gmail |
| SMTP Host | smtp.gmail.com（自動入力） |
| Port | 587（自動入力） |
| Encryption | TLS（自動入力） |
| Username | Gmailのメールアドレス |
| Password | **アプリパスワード**（Gmailアカウントのパスワードではない） |

#### Googleアプリパスワードの取得方法

1. [Google アカウント](https://myaccount.google.com/) → セキュリティ
2. **2段階認証を有効化**（必須）
3. 「アプリパスワード」で16文字のパスワードを生成
4. 生成されたパスワードをHXFEのPassword欄に入力

### SendGrid の設定

| 項目 | 入力値 |
|---|---|
| Provider | SendGrid |
| Username | `apikey`（この文字列そのまま） |
| Password | SendGridのAPIキー |

### パスワードを wp-config.php で管理する（推奨）

DBにパスワードを保存するより、`wp-config.php` に定数で書く方が安全です。

```php
// wp-config.php に追加

// SMTP認証情報
define( 'HXFE_SMTP_PASSWORD', 'your-gmail-app-password' );  // Gmail等
define( 'HXFE_SMTP_API_KEY',  'SG.xxxxxxxxxxxxxx' );        // SendGrid

// フォームパスワード認証（auth機能を使う場合）
define( 'HXFE_STAFF_PASS', 'ここに実際のパスワード' );

// 管理画面からのPHPファイル直接編集を禁止（推奨）
define( 'DISALLOW_FILE_EDIT', true );
```

定数が設定されていると、管理画面に🔒マークが表示されます。

### テスト送信

設定ページ下部の「Send Test Email」に自分のアドレスを入力して **📨 Send Test** ボタンをクリックすると、設定が正しいか確認できます。

[▲ 目次に戻る](#toc)

---

## ステップフォーム（複数ページ） {#ステップフォーム}

長いフォームを複数のステップ（ページ）に分割できます。各ステップ間にプログレスバーが表示されます。

### グループ型 — 複数フィールドを1ステップにまとめる {#step-group}

```php
$schemas['seminar'] = [
    'id' => 'seminar',
    'to' => 'admin@example.com',

    // steps キーでステップを定義
    'steps' => [
        [
            'label'  => '基本情報',           // プログレスバーに表示されるステップ名
            'fields' => ['name', 'email'],    // このステップで表示するフィールドのキー
        ],
        [
            'label'  => '参加内容',
            'fields' => ['plan', 'note'],
        ],
        [
            'label'  => '確認事項',
            'fields' => ['privacy'],
        ],
    ],

    'fields' => [
        [ 'key' => 'name',    'type' => 'text',     'label' => 'お名前',     'required' => true ],
        [ 'key' => 'email',   'type' => 'email',    'label' => 'メール',     'required' => true ],
        [ 'key' => 'plan',    'type' => 'select',   'label' => '参加プラン', 'required' => true,
          'options' => [
              [ 'value' => 'free',  'label' => '無料コース' ],
              [ 'value' => 'paid',  'label' => '有料コース' ],
          ],
        ],
        [ 'key' => 'note',    'type' => 'textarea', 'label' => '備考' ],
        [ 'key' => 'privacy', 'type' => 'privacy',  'label' => '規約に同意する',
          'policy_url' => 'https://example.com/terms', 'required' => true ],
        [ 'key' => 'hp', 'type' => 'honeypot' ],
    ],
];
```

### 1問1答型 — フィールドを1つずつ表示する {#step-one-by-one}

フィールドに1つずつ答えていくインタラクティブなフォームです。

```php
$schemas['survey'] = [
    'id'        => 'survey',
    'to'        => 'admin@example.com',
    'step_mode' => 'one_by_one',   // これだけ追加するだけ
    'fields'    => [
        [ 'key' => 'name',  'type' => 'text',   'label' => 'お名前',   'required' => true ],
        [ 'key' => 'email', 'type' => 'email',  'label' => 'メール',   'required' => true ],
        [ 'key' => 'score', 'type' => 'select', 'label' => '満足度',   'required' => true,
          'options' => [
              [ 'value' => '5', 'label' => '⭐⭐⭐⭐⭐ 非常に満足' ],
              [ 'value' => '4', 'label' => '⭐⭐⭐⭐ 満足' ],
              [ 'value' => '3', 'label' => '⭐⭐⭐ 普通' ],
              [ 'value' => '2', 'label' => '⭐⭐ 不満' ],
              [ 'value' => '1', 'label' => '⭐ 非常に不満' ],
          ],
        ],
        [ 'key' => 'hp', 'type' => 'honeypot' ],
    ],
];
```

[▲ 目次に戻る](#toc)

---

## 条件分岐（show_if / required_if） {#条件分岐}

フィールドの値に応じて、他のフィールドの表示・必須状態を動的に変えられます。

### 基本的な使い方 — show_if

「個人 / 法人」を選ぶと会社名欄が現れる例です。

```php
'fields' => [
    [
        'key'     => 'type',
        'type'    => 'select',
        'label'   => '種別',
        'options' => [
            [ 'value' => 'personal',  'label' => '個人' ],
            [ 'value' => 'corporate', 'label' => '法人' ],
        ],
    ],
    [
        'key'     => 'company',
        'type'    => 'text',
        'label'   => '会社名',
        // ↓ 「type」が「corporate」のときだけ表示
        'show_if' => [ 'type', '==', 'corporate' ],
    ],
    [
        'key'         => 'company',
        'type'        => 'text',
        'label'       => '会社名',
        'show_if'     => [ 'type', '==', 'corporate' ],
        // ↓ 表示されているときだけ必須にする
        'required_if' => [ 'type', '==', 'corporate' ],
    ],
],
```

### 演算子一覧

| 演算子 | 意味 | 使用例 |
|---|---|---|
| `==` | 等しい | `['type', '==', 'corporate']` |
| `!=` | 等しくない | `['plan', '!=', 'free']` |
| `>` | より大きい | `['age', '>', '18']` |
| `>=` | 以上 | `['quantity', '>=', '10']` |
| `<` | より小さい | `['score', '<', '3']` |
| `<=` | 以下 | `['price', '<=', '1000']` |
| `contains` | 含む | `['memo', 'contains', '緊急']` |
| `not_contains` | 含まない | `['email', 'not_contains', 'example']` |
| `in` | リストに含まれる | `['plan', 'in', 'basic,standard']` |
| `not_in` | リストに含まれない | `['type', 'not_in', 'test,demo']` |
| `empty` | 空である | `['note', 'empty']` |
| `not_empty` | 空でない | `['note', 'not_empty']` |

### AND / OR の複合条件

```php
// AND: 法人 かつ プレミアムプランのときだけ表示
'show_if' => [ 'and', [
    [ 'type', '==', 'corporate' ],
    [ 'plan', '==', 'premium' ],
]],

// OR: 営業 または サポートを選んだときに表示
'show_if' => [ 'or', [
    [ 'category', '==', 'sales' ],
    [ 'category', '==', 'support' ],
]],
```

[▲ 目次に戻る](#toc)

---

## ステップのスキップ {#ステップスキップ}

条件に応じて特定のステップ丸ごとをスキップできます。

```php
'steps' => [
    [
        'label'  => '基本情報',
        'fields' => ['name', 'email', 'type'],
    ],
    [
        'label'   => '法人情報',
        'fields'  => ['company', 'dept'],
        // ↓ 「type」が「corporate」以外のときはこのステップをスキップ
        'skip_if' => [ 'type', '!=', 'corporate' ],
    ],
    [
        'label'  => 'お問い合わせ内容',
        'fields' => ['body'],
    ],
],
```

> **動作:** 「個人」を選んだ場合、「法人情報」ステップは完全に飛ばされ「基本情報 → お問い合わせ内容」と進みます。「法人」を選んだ場合は全ステップが表示されます。

[▲ 目次に戻る](#toc)

---

## reCAPTCHA（スパム対策） {#recaptcha}

### 事前準備

1. [Google reCAPTCHA](https://www.google.com/recaptcha/admin) でサイトを登録
2. WordPress管理画面 → **設定 → Form Engine → reCAPTCHA** でキーを入力

> **シークレットキー未設定時の挙動（v1.3.8〜）**
> `recaptcha` フィールドを置いたのにシークレットキーが未設定の場合、本番環境（WP_DEBUG無効）では送信が**ブロック**されます（未検証のまま素通りさせない安全側の挙動）。開発環境（WP_DEBUG有効）では検証をスキップします。キー未設定のままだと送信が常に失敗するので、必ず設定してください（スキーマlintでも警告されます）。

### v3（非表示タイプ・推奨）

ユーザーには何も表示されず、バックグラウンドでスコア判定します。

```php
[
    'key'       => 'captcha',
    'type'      => 'recaptcha',
    'version'   => 'v3',
    'action'    => 'contact',  // Google Analytics での識別名（任意の英数字）
    'threshold' => 0.5,        // 0〜1。低いほどボット判定が厳しい（デフォルト: 0.5）
]
```

### v2（チェックボックスタイプ）

「私はロボットではありません」チェックボックスを表示します。

```php
[ 'key' => 'captcha', 'type' => 'recaptcha', 'version' => 'v2' ]
```

[▲ 目次に戻る](#toc)

---

## プライバシーポリシー同意 {#プライバシー}

```php
[
    'key'          => 'privacy',
    'type'         => 'privacy',
    'label'        => 'プライバシーポリシーに同意する',
    'policy_url'   => 'https://example.com/privacy',   // ポリシーページのURL
    'policy_label' => 'プライバシーポリシー',            // リンクのテキスト
    'required'     => true,                            // 同意しないと送信できない
]
```

PDFのURLを指定することもできます。

```php
'policy_url' => 'https://example.com/privacy.pdf',
```

[▲ 目次に戻る](#toc)

---

## デザインのカスタマイズ {#デザイン}

HXFEのスタイルは2層構造です。

**Layer 1（推奨）:** CSS カスタムプロパティ（デザイントークン）を上書きするだけで全体が変わる
**Layer 2:** クラス名で個別の要素を細かく上書きする

テーマの `style.css` に書くだけで適用されます。プラグイン本体は変更不要です。

### Layer 1: デザイントークンの上書き（推奨）

```css
/* テーマの style.css に追加するだけ */
.hxfe-wrap {
  --hxfe-color-primary:      #e11d48;  /* ブランドカラー */
  --hxfe-color-primary-dark: #be123c;  /* ホバー時 */
  --hxfe-color-border:       #d1d5db;
  --hxfe-color-border-focus: #e11d48;
  --hxfe-color-bg:           #ffffff;
  --hxfe-color-bg-subtle:    #f9fafb;
  --hxfe-color-text:         #111827;
  --hxfe-color-text-muted:   #6b7280;
  --hxfe-color-text-label:   #374151;
  --hxfe-radius-md:          24px;     /* 入力・ボタンの角丸 */
  --hxfe-radius-lg:          16px;     /* 確認画面・完了画面 */
  --hxfe-font-size-sm:       0.8125rem;
  --hxfe-font-size-base:     0.9375rem;
  --hxfe-spacing-field:      1.25rem;
  max-width: 560px;
}
```

### フィールドに個別のクラスを追加する

```php
[ 'key'         => 'name',
  'type'        => 'text',
  'label'       => 'お名前',
  'field_class' => 'col-half',        // .hxfe-field に追加
  'input_class' => 'form-control',    // <input> に追加（Bootstrap対応）
  'label_class' => 'fw-bold',         // <label> に追加
]
```

### フォームIDごとに個別スタイルを当てる

各フォームのラッパーには `id="hxfe-{フォームID}"` が付きます。

```css
#hxfe-contact.hxfe-wrap { max-width: 800px; }
#hxfe-contact .hxfe-btn-submit { background: #16a34a; }
```

### デフォルトCSSを無効化する

```php
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_style( 'hxfe-forms' );
}, 20 );
```

または **設定 → Form Engine → Disable default CSS globally** にチェックを入れると全フォームに適用されます。

### CSSクラス一覧

**ラッパー・フォーム全体**

| クラス名 | 要素 |
|---|---|
| `.hxfe-wrap` | フォーム全体のラッパー（デザイントークンのスコープ） |
| `.hxfe-form` | `<form>` タグ |
| `.hxfe-error-summary` | フォーム上部のエラーサマリー |

**フィールド共通**

| クラス名 | 要素 |
|---|---|
| `.hxfe-field` | 各フィールドのラッパー `<div>` |
| `.hxfe-field--error` | エラー時に追加 |
| `.hxfe-field--hidden` | 条件分岐で非表示 |
| `.hxfe-label` | `<label>` |
| `.hxfe-required` | 必須マーク（*） |
| `.hxfe-error-msg` | フィールド下のエラーメッセージ |

**入力要素**

| クラス名 | 要素 |
|---|---|
| `.hxfe-input` | `<input type="text/email/tel/url/number/date">` |
| `.hxfe-textarea` | `<textarea>` |
| `.hxfe-select` | `<select>` |
| `.hxfe-file-input` | `<input type="file">` |

**ボタン・アクション**

| クラス名 | 要素 |
|---|---|
| `.hxfe-btn-submit` | 送信・次へボタン |
| `.hxfe-btn-back` | 戻るボタン |
| `.hxfe-btn-download` | ダウンロードボタン（`download_url` 使用時） |

**確認・完了画面**

| クラス名 | 要素 |
|---|---|
| `.hxfe-confirm` | 確認画面のラッパー |
| `.hxfe-confirm-list` | 入力内容一覧の `<dl>` |
| `.hxfe-complete` | 完了メッセージのラッパー |
| `.hxfe-download-wrap` | ダウンロードボタンのラッパー |
| `.hxfe-availability-notice` | 公開期間外のメッセージ |

**ステップフォーム**

| クラス名 | 要素 |
|---|---|
| `.hxfe-progress-label.current` | 現在のステップ |
| `.hxfe-progress-label.done` | 完了済みのステップ |
| `.hxfe-progress-bar-fill` | プログレスバーの塗り部分 |
| `.hxfe-progress-counter` | 「2 / 3」のテキスト |

**chatbot UI**

| クラス名 | 要素 |
|---|---|
| `.hxfe-chatbot-header` | ヘッダー（`bot_name` がある場合のみ） |
| `.hxfe-chatbot-log` | 会話ログのスクロールエリア |
| `.hxfe-chatbot-bubble--bot` | ボット側のバブル |
| `.hxfe-chatbot-bubble--user` | ユーザー側のバブル（青背景） |
| `.hxfe-chatbot-time` | タイムスタンプ |
| `.hxfe-chatbot-send-btn` | 送信ボタン |
| `.hxfe-chatbot-choice-btn` | 選択肢ボタン |

**フェードインアニメーション**

`.hxfe-wrap--fadein` — htmxのswap後にJSが付与するクラス。上書きで変更できます:

```css
.hxfe-wrap--fadein {
  animation: my-fadein 0.4s ease;
}
@keyframes my-fadein {
  from { opacity: 0; transform: translateX(-8px); }
  to   { opacity: 1; transform: translateX(0); }
}
```

[▲ 目次に戻る](#toc)
## 外部CSSファイルの読み込み {#外部css}

`custom_css` に直書きする以外にも、外部CSSファイルを読み込む方法があります。  
本番環境では外部ファイルの方がキャッシュが効きパフォーマンスが良くなります。

### ① wp_enqueue_style() で読み込む（推奨）

サイト全体・複数フォームで同じCSSを使う場合に最もシンプルな方法です。

```php
// functions.php に追加
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'my-form-style',
        get_theme_file_uri( 'css/contact-form.css' ),
        [ 'hxfe-forms' ],  // hxfe-forms.css の後に読み込む
        '1.0.0'
    );
} );

// スキーマ側は custom_css なしで wrapper_class だけ指定
$schemas['contact'] = [
    'id'            => 'contact',
    'wrapper_class' => 'my-contact-form',  // この名前でCSSを書く
    ...
];
```

```css
/* テーマの css/contact-form.css */
.my-contact-form .hxfe-btn-submit {
    background: #2563eb;
    color: #fff;
    border-radius: 6px;
    padding: 10px 28px;
}
.my-contact-form .hxfe-label {
    font-weight: bold;
}
```

---

### ② file_get_contents() でCSSファイルをスキーマに渡す

フォームごとにCSSファイルを分けて管理したい場合に使えます。

```php
$schemas['contact'] = [
    'id'         => 'contact',
    'custom_css' => file_get_contents( get_theme_file_path( 'css/contact-form.css' ) ),
    ...
];
```

> ⚠️ この方法は毎リクエストでファイルを読み込むため、①の方がパフォーマンス面で優れています。

---

### ③ wp_add_inline_style() でHXFEのCSSに追記する

少量の追記だけしたい場合に便利です。

```php
add_action( 'wp_enqueue_scripts', function() {
    $custom = '
        .hxfe-btn-submit { background: #e74c3c; }
        .hxfe-label { font-weight: bold; }
    ';
    // 'hxfe-forms' ハンドルのCSSにインラインで追記
    wp_add_inline_style( 'hxfe-forms', $custom );
} );
```

---

### 方法の使い分け

| 方法 | 向いている場面 |
|---|---|
| **① `wp_enqueue_style()`** | 本番環境・複数フォームで同じCSSを使う |
| **② `file_get_contents()`** | フォームごとにCSSファイルを分けたい |
| **③ `wp_add_inline_style()`** | 少量の追記だけしたい |
| **`custom_css` に直書き** | テスト・プロトタイプ・量が少ない場合 |

[▲ 目次に戻る](#toc)

---

## iframe で別サイトに埋め込む {#iframe}

ランディングページや別ドメインのサイトにフォームを埋め込めます。

### 同一サイト内の埋め込み

```
[hxfe_iframe id="contact"]
```

### 別サイトへの埋め込み手順

#### フォームを設置するサイトA側の設定

1. **設定 → Form Engine → iframe / CORS Settings**
2. 「Allowed iframe origins」に埋め込み先のURLを1行ずつ入力

```
https://landing-page.com
https://campaign-site.com
```

3. **「Save iframe Settings」** で保存
4. プラグインを一度**無効化 → 有効化**（リライトルールを更新するため）

#### 埋め込みたいサイトBのページ

```
[hxfe_iframe id="contact" site="https://site-a.com" title="お問い合わせ"]
```

### ショートコードの属性

| 属性 | デフォルト | 内容 |
|---|---|---|
| `id` | （必須） | フォームID |
| `site` | 同一サイト | フォームを設置しているサイトのURL |
| `height` | `300` | iframeの初期高さ（px）※自動調整されます |
| `title` | `Contact form` | iframeのtitle属性（アクセシビリティ用） |

> フォームの高さはステップ切り替えやエラー表示のたびに自動調整されます。スクロールバーは出ません。

### iframeの直接URLアクセス

```
https://site-a.com/hxfe-iframe/contact/
```

このURLをiframeのsrcに手動で指定することもできます。

[▲ 目次に戻る](#toc)

---

## スキーマ全項目リファレンス {#リファレンス}

### トップレベルキー（フォーム全体の設定）

```php
$schemas['id'] = [

    // ── 必須 ────────────────────────────────────────────────
    'id'                   => 'contact',         // フォームID（英数字・ハイフン）
    'fields'               => [ ... ],           // フィールド定義の配列

    // ── 管理者宛メール ───────────────────────────────────────
    'to'                   => 'admin@example.com',
    'subject'              => 'お問い合わせ: {name}',
    'to_rules'             => [ ... ],           // 送信先の条件分岐
    'subject_rules'        => [ ... ],           // 件名の条件分岐
    'admin_notify'         => true,              // false にすると管理者宛を送らない
    'bcc'                  => 'cc@example.com',  // BCC

    // ── 自動返信メール ───────────────────────────────────────
    'reply_to_field'       => 'email',
    'autoreply_subject'    => '受け付けました',
    'autoreply_body'       => "{name}様\nありがとうございます。",
    'autoreply_from'       => 'noreply@example.com',
    'autoreply_from_name'  => 'サポート',

    // ── ステップフォーム ─────────────────────────────────────
    'steps'                => [ ... ],           // グループ型ステップ定義
    'step_mode'            => 'one_by_one',      // 1問1答型

    // ── デザイン ────────────────────────────────────────────
    'wrapper_class'        => 'my-form',
    'disable_default_css'  => false,
    'custom_css'           => '.hxfe-wrap { ... }',

    // ── iframe ──────────────────────────────────────────────
    'title'                => 'お問い合わせ',    // iframeページのtitleタグ
];
```

### フィールドのキー（各フィールドの設定）

```php
[
    // ── 必須 ─────────────────────────────────────────────────
    'key'          => 'name',              // フィールドキー（英数字・アンダースコア）
    'type'         => 'text',             // フィールドタイプ
    'label'        => 'お名前',           // ラベル

    // ── バリデーション ────────────────────────────────────────
    'required'     => false,              // 必須かどうか
    'required_if'  => [ ... ],           // 条件付き必須
    'maxlength'    => 500,               // 最大文字数

    // ── 表示条件 ──────────────────────────────────────────────
    'show_if'      => [ ... ],           // この条件を満たすときに表示
    'hide_if (deprecated, use show_if)'      => [ ... ],           // この条件を満たすときに非表示

    // ── UI ────────────────────────────────────────────────────
    'placeholder'  => '例: 山田太郎',    // プレースホルダー
    'rows'         => 4,                 // textarea の行数
    'options'      => [ ... ],           // select の選択肢

    // ── スタイル ──────────────────────────────────────────────
    'field_class'  => 'form-group',      // フィールドdivのクラス
    'label_class'  => 'form-label',      // labelのクラス
    'input_class'  => 'form-control',    // inputのクラス
]
```

[▲ 目次に戻る](#toc)

---


---

## v1.3.4 新機能 {#v134}

### ページスラッグによるフォームの集計（v1.3.4）

同じフォームを複数のページで使い回すとき、件名にページスラッグが自動付与されます。

例: `seminar-2026-06` というスラッグのページで `[hxfe_form id="apply"]` を使うと
件名が `申込: 大久保 [apply@seminar-2026-06]` になります。

メールサーバー側でフィルタリングするだけでページ別に集計できます。
スキーマの変更は不要です。

ページスラッグの付与を無効にしたい場合は `disable_context: true` を追加します：

```php
$schemas['contact'] = [
    'id'              => 'contact',
    'disable_context' => true,  // ページスラッグを付与しない
    'to'              => 'admin@example.com',
];
```


### 管理画面のデザイン刷新（v1.3.4）

登録済みフォーム一覧ページのUIを改善しました。

- サマリーをカード型の数値グリッドに変更
- フィールドタイプをコードチップで表示
- ショートコードのコピーボタンをゴーストスタイルに

### スキーマサンプルの追加（v1.3.4）

管理画面の「Registered Forms」ページ下部に7種類のサンプルコードを追加しました。
📋 Copy ボタンで `functions.php` にそのままペーストできます。

### レスポンシブ対応（v1.3.4）

768px以下でスマートフォン向けのレイアウトに自動切り替えされます。

- ボタンが縦並び・横幅100%に
- 確認画面のラベルと値が縦積みに
- chatbotのバブル幅が広がる

### CSS カスタムプロパティ（デザイントークン）（v1.3.4）

テーマのCSSに数行追加するだけで全体のカラーを変更できるようになりました。

```css
.hxfe-wrap {
  --hxfe-color-primary: #e11d48;  /* ブランドカラーに変更 */
  --hxfe-radius-md: 24px;         /* 丸いデザインに */
}
```

詳細は別冊「HXFE スタイルカスタマイズガイド」を参照してください。

### 送信完了後にファイルをダウンロードできる（v1.3.4）

資料請求フォームなどで、送信完了後にダウンロードボタンを表示できます。

```php
$schemas['document'] = [
    'id'             => 'document',
    'to'             => 'admin@example.com',
    'subject'        => '資料請求: {name}',
    'complete_message' => 'ありがとうございます。以下より資料をダウンロードしてください。',
    'download_url'   => 'https://example.com/files/document.pdf',
    'download_label' => '📄 資料をダウンロード',
    'fields'         => [
        [ 'key' => 'name',  'type' => 'text',  'label' => 'お名前', 'required' => true ],
        [ 'key' => 'email', 'type' => 'email', 'label' => 'メール', 'required' => true ],
        [ 'key' => 'hp',    'type' => 'honeypot' ],
    ],
];
```

| キー | 説明 |
|------|------|
| `download_url` | ダウンロードさせるファイルのURL |
| `download_label` | ボタンのラベル（省略時: "Download"） |

### フォームの公開期間を設定できる（v1.3.4）

開始日時・終了日時を設定して、期間外には別のメッセージを表示できます。

```php
$schemas['campaign'] = [
    'id'              => 'campaign',
    'to'              => 'admin@example.com',
    'subject'         => 'キャンペーン応募: {name}',
    'available_from'  => '2026-06-10 00:00:00',
    'available_until' => '2026-06-30 23:59:59',
    'before_html'=> '<p>受付は6月10日から開始します。</p>',
    'after_html'=> '<p>受付は終了しました。</p>',
    'fields'          => [ ... ],
];
```

| キー | 説明 |
|------|------|
| `available_from` | フォーム公開開始日時（WordPress のタイムゾーンに従う） |
| `available_until` | フォーム公開終了日時 |
| `before_html` | 開始前に表示するHTML（省略時: デフォルトメッセージ） |
| `after_html` | 終了後に表示するHTML（省略時: デフォルトメッセージ） |

どちらか一方のみの設定も可能です。

### iframeのCORS制限をフォームごとに設定できる（v1.3.4）

グローバルのCORS設定を上書きして、フォームごとに許可するオリジンを絞れます。

```php
$schemas['contact'] = [
    'id'              => 'contact',
    'allowed_origins' => [ 'https://external-site.com' ],
    // ...
];
```

この設定があるフォームは、指定したオリジンからのiframeのみ許可されます。

### 条件付き完了画面（complete_html_rules）（v1.3.4）

送信完了後に表示するHTMLを、回答内容によって切り替えられます。
`{field_key}` による補間も使えます。

```php
$schemas['diagnosis'] = [
    'id'        => 'diagnosis',
    'to'        => 'admin@example.com',
    'subject'   => '診断結果: {name}',
    'complete_html_rules' => [
        [ 'when' => ['plan', '==', 'basic'],
          'html' => '<h3>ベーシックプランがおすすめです</h3><p>{name}様、<a href="/basic/">詳細はこちら</a></p>' ],
        [ 'when' => ['plan', '==', 'premium'],
          'html' => '<h3>プレミアムプランがおすすめです</h3><p>担当者よりご連絡します。</p>' ],
        [ 'when' => 'default',
          'html' => '<p>{name}様、内容を確認次第ご連絡いたします。</p>' ],
    ],
    'fields' => [ ... ],
];
```

chatbot・通常フォーム・ステップフォームどのUIモードとも組み合わせ可能です。

### chatbot 診断モード（メール送信なし）（v1.3.4）

`to` を省略して `complete_html_rules` だけを使うと、
**メール送信なしで結果を返す診断チャット**が実現できます。

```php
$schemas['diagnosis'] = [
    'id'        => 'diagnosis',
    // 'to' を書かない → メール送信なし
    'step_mode' => 'chatbot',
    'bot_name'  => '診断Bot',
    'greeting'  => 'いくつか質問に答えて、最適なプランを診断します！',
    'complete_html_rules' => [
        [ 'when' => ['plan', '==', 'basic'],
          'html' => '<h3>ベーシックプランがおすすめです</h3><p>{name}様に最適です。</p>' ],
        [ 'when' => ['plan', '==', 'premium'],
          'html' => '<h3>プレミアムプランがおすすめです</h3>' ],
        [ 'when' => 'default',
          'html' => '<p>担当者よりご連絡します。</p>' ],
    ],
    'fields' => [
        [ 'key' => 'name', 'type' => 'text', 'label' => 'お名前', 'required' => true,
          'bot_message' => 'まず、お名前を教えてください。' ],
        [ 'key' => 'plan', 'type' => 'radio', 'label' => 'プラン', 'required' => true,
          'bot_message' => '{name}様、ご希望の規模はどちらですか？',
          'options' => [
              [ 'value' => 'basic',   'label' => '個人・小規模' ],
              [ 'value' => 'premium', 'label' => '法人・大規模' ],
          ],
        ],
        [ 'key' => 'hp', 'type' => 'honeypot' ],
    ],
];
```

`to_rules` と組み合わせて「特定の選択肢のときだけメールを送る」もできます：

```php
'to_rules' => [
    [ 'when' => ['plan', '==', 'premium'], 'to' => 'sales@example.com' ],
    // premium のみメール送信、basic はスキップ
],
'to' => '',
```

### chatbot + 条件分岐（v1.3.4）

chatbotモードでも `show_if` / `required_if` が使えます。
選択肢によって次の質問をスキップ・表示できます。

```php
'fields' => [
    [ 'key' => 'name', 'type' => 'text',  'bot_message' => 'お名前をどうぞ。' ],
    [ 'key' => 'type', 'type' => 'radio', 'bot_message' => 'ご用件をお選びください。',
      'options' => [
          [ 'value' => 'general', 'label' => '一般のお問い合わせ' ],
          [ 'value' => 'support', 'label' => 'サポート' ],
      ],
    ],
    // support のときだけ表示
    [ 'key' => 'detail', 'type' => 'textarea', 'required' => true,
      'bot_message' => 'サポートの詳細をお聞かせください。',
      'show_if' => [ 'type', '==', 'support' ] ],
    [ 'key' => 'email', 'type' => 'email', 'bot_message' => 'メールアドレスをどうぞ。' ],
    [ 'key' => 'hp',    'type' => 'honeypot' ],
],
```

### フィールドのバリデーションをカスタマイズする（v1.3.4）

フィールドに `pattern` / `minlength` / `maxlength` を追加するだけで、
サーバー側バリデーションとHTML属性が同時に設定されます。

```php
[ 'key'           => 'zip',
  'type'          => 'text',
  'label'         => '郵便番号',
  'required'      => true,
  'pattern'       => '^\d{3}-?\d{4}$',   // 正規表現（デリミタなし）
  'minlength'     => 7,
  'maxlength'     => 8,
  'error_message' => '郵便番号の形式が正しくありません（例: 100-0001）' ]
```

より高度なカスタムバリデーションは `hxfe_validate_field` フィルターで：

```php
add_filter( 'hxfe_validate_field', function( $result, $field, $raw ) {
    if ( $field['key'] === 'zip' && '' === $result['error'] ) {
        if ( ! preg_match( '/^\d{3}-?\d{4}$/', $result['value'] ) ) {
            return [ 'value' => $result['value'], 'error' => '郵便番号の形式が正しくありません。' ];
        }
    }
    return $result;
}, 10, 3 );
```

### フォーム全体のバリデーションをカスタマイズする（v1.3.4）

複数フィールドをまたいだ検証には `hxfe_validate_form` フィルターを使います。

```php
add_filter( 'hxfe_validate_form', function( $errors, $values, $schema ) {

    // パスワード一致チェック
    if ( isset( $values['password'], $values['password_confirm'] ) ) {
        if ( $values['password'] !== $values['password_confirm'] ) {
            $errors['password_confirm'] = 'パスワードが一致しません。';
        }
    }

    // 電話番号またはメールアドレスのどちらかは必須
    if ( empty( $values['tel'] ) && empty( $values['email'] ) ) {
        $errors['tel'] = '電話番号またはメールアドレスのどちらかを入力してください。';
    }

    return $errors;
}, 10, 3 );
```

特定のフォームにだけ適用したい場合は `$schema['id']` で絞れます：

```php
add_filter( 'hxfe_validate_form', function( $errors, $values, $schema ) {
    if ( ( $schema['id'] ?? '' ) !== 'register' ) {
        return $errors;  // このフォーム以外はスキップ
    }
    // register フォームのみ適用される処理
    return $errors;
}, 10, 3 );
```

### フィールドの前後にHTMLを挿入する（v1.3.4）

`before_html` / `after_html` で任意のHTMLをフィールドの前後に挿入できます。

```php
[ 'key'         => 'zip',
  'type'        => 'text',
  'label'       => '郵便番号',
  'before_html' => '<p class="field-note">ハイフンあり・なし両方対応しています</p>',
  'after_html'  => '<p class="field-note"><a href="/help/">入力方法はこちら</a></p>' ],

// 区切り線を入れる
[ 'key'         => 'section-2',
  'type'        => 'text',
  'label'       => '会社名',
  'before_html' => '<hr><h3 class="form-section-title">勤務先情報</h3>' ],
```

---

## v1.3.2〜v1.3.3 新機能 {#v132v133}

### アクセス制御（v1.3.2）

フォームにパスワード認証またはIP制限を設定できます。

**パスワード認証:**
```php
'auth' => [
    'users' => [
        [ 'id' => 'staff', 'password' => defined('STAFF_PASS') ? STAFF_PASS : '' ],
    ],
],
```
どのUIモード（ステップ・chatbot等）とも組み合わせ可能です。

**IP制限:**
```php
'allowed_ips'     => [ '203.0.113.0/24' ],
'ip_blocked_html' => '<p>このフォームはご利用いただけません。</p>',
```

> **プロキシ背後で使う場合（v1.3.8〜）**
> IP判定はデフォルトで `REMOTE_ADDR`（接続元IP）のみを見ます。`X-Forwarded-For` などのヘッダはクライアントが偽装できるため無視します。
> Cloudflareやロードバランサーなどのプロキシ背後で運用していて、`allowed_ips` を正しく機能させたい場合は、`wp-config.php` に次の1行を追加してください。
> ```php
> define( 'HXFE_TRUST_PROXY', true );
> ```
> 信頼するヘッダを絞り込みたい場合は `hxfe_trusted_proxy_headers` フィルターで `$_SERVER` のキー名の配列を返します（空配列なら `REMOTE_ADDR` のみ）。

### ブルートフォース対策（v1.3.2）

パスワード認証で5回連続失敗すると15分間ロックアウトされます。

---

## v1.3.1 新機能 {#v131}

### fileフィールドが正式対応（v1.3.1）

アップロードされたファイルが**管理者宛メールに添付**されるようになりました。
送信後にサーバーから**自動削除**されます。
ログイン不要で使えます（Googleフォームと異なる点）。

```php
[ 'key'         => 'attachment',
  'type'        => 'file',
  'label'       => '添付ファイル',
  'accept'      => '.pdf,.doc,.docx',  // ブラウザのファイル選択ダイアログの表示制限（見た目のみ）
  'max_size_mb' => 5,                   // 最大ファイルサイズ（MB）デフォルト: 5
  'mime_types'  => [                    // サーバー側の実際の制限（こちらが本当のセキュリティ）
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  ],
]
```

#### `accept` と `mime_types` の違い

| キー | 役割 | セキュリティ |
|---|---|---|
| `accept` | ブラウザのファイル選択ダイアログで表示する種類を絞る（見た目だけ） | ✗ DevToolsで突破できる |
| `mime_types` | サーバー側でファイルの**中身**を読んでMIMEタイプを判定する | ✅ 拡張子を偽っても通らない |

**両方設定することを推奨します。** `accept` でユーザーが間違えにくくして、`mime_types` でセキュリティを確保します。

#### `mime_types` を省略した場合（デフォルト）

`mime_types` を書かない場合、HXFEが安全なデフォルトリストを自動的に適用します。

```
許可されるもの: PDF / Word / Excel / テキスト / CSV / JPEG / PNG / GIF / WebP
許可されないもの: PHP / HTML / JavaScript / SVG / 実行ファイル（.exe 等）
```

#### 許可するファイル形式のカスタマイズ例

```php
// 書類のみ（PDF・Word）
'mime_types' => [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
],

// 画像のみ
'mime_types' => [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
],

// 書類＋画像
'mime_types' => [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/jpeg',
    'image/png',
],
```

#### 指定できる主なMIMEタイプ一覧

| 形式 | MIMEタイプ |
|---|---|
| PDF | `application/pdf` |
| Word (.doc) | `application/msword` |
| Word (.docx) | `application/vnd.openxmlformats-officedocument.wordprocessingml.document` |
| Excel (.xls) | `application/vnd.ms-excel` |
| Excel (.xlsx) | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` |
| テキスト | `text/plain` |
| CSV | `text/csv` |
| JPEG | `image/jpeg` |
| PNG | `image/png` |
| GIF | `image/gif` |
| WebP | `image/webp` |

### v1.3.0 リファクタリング

- `hxfe_validate_step_request()` を新設し、ステップ系エンドポイントの重複コードを解消
- `renderer.php` を `includes/fields/field-renderers.php` に分割
- `hxfe_process_fields()` / `hxfe_eval_condition()` / `hxfe_interpolate()` に PhpDoc 型定義を追加

### v1.2.2 バグ修正

- `confirm_label` を新設（入力フォームのボタンラベルが英語固定だった問題を修正）
- `hxfe-front.js` が読み込まれていなかった問題を修正
- `custom_css` / `disable_default_css` が機能しなかった問題を修正
- `uninstall.php` が設定を削除しなかった問題を修正

---

## v1.2.1 新機能 {#v121}

### tel / url フィールドタイプ

電話番号・URL入力フィールドが正式タイプとして追加されました。

```php
[ 'key' => 'phone', 'type' => 'tel', 'label' => '電話番号' ]
[ 'key' => 'web',   'type' => 'url', 'label' => 'WebサイトURL', 'placeholder' => 'https://' ]
```

### ラベルカスタマイズ

ボタンと確認画面の見出しをスキーマで変更できます。

```php
$schemas['contact'] = [
    'confirm_label'   => '確認画面へ →',        // 入力フォームの送信ボタン（デフォルト: 'Confirm →'）
    'submit_label'    => '送信する',             // 確認画面の送信ボタン（デフォルト: 'Submit'）
    'back_label'      => '← 入力に戻る',        // 戻るボタン（デフォルト: '← Back'）
    'next_label'      => '次のステップへ →',     // ステップの次へボタン（デフォルト: 'Next →'）
    'confirm_heading' => '入力内容をご確認ください', // 確認画面の見出し
    'error_message'   => '入力内容をご確認ください', // バリデーションエラー時のメッセージ
    ...
];
```

> **confirm_label と submit_label の違い:**
> - `confirm_label` — 入力フォームの「次へ」ボタン（確認画面に進む）
> - `submit_label` — 確認画面の「送信」ボタン（実際に送信する）
> - `confirm: false` の場合は `submit_label` が入力フォームのボタンに使われます

### Webhook送信（Zapier / Make連携）

送信データを外部URLへ自動送信します。フォーム送信は止まりません。

```php
$schemas['contact'] = [
    'webhooks' => [
        // Zapier
        [ 'url' => 'https://hooks.zapier.com/hooks/catch/xxx/yyy/' ],

        // Make（Integromat）
        [ 'url' => 'https://hook.eu1.make.com/xxx', 'format' => 'json' ],

        // 条件付き送信
        [ 'url'  => 'https://example.com/webhook',
          'when' => [ 'category', '==', 'urgent' ] ],

        // カスタムヘッダー付き
        [ 'url'     => 'https://api.example.com/',
          'headers' => [ 'Authorization' => 'Bearer your-token' ] ],
    ],
    ...
];
```

送信ペイロードに `_form_id` / `_site_url` / `_site_name` / `_sent_at` が自動付与されます。

### 確認画面・メール本文の表示改善

| フィールド | 修正前 | 修正後 |
|---|---|---|
| radio / select | `premium`（valueそのまま） | `プレミアムプラン`（label） |
| checkbox_group | `design,dev` | `デザイン, 開発` |
| privacy | `1` | `✓ Agreed` |
| date | `2026-05-30` | WordPressの日付書式 |
| 非表示フィールド | 確認画面・メールに表示 | 除外 |
| 空値フィールド | メールに空欄で表示 | メールから除外 |

### ステップフォームの confirm:false 対応

ステップフォーム（`steps` 配列）でも `confirm: false` が動作するようになりました。

```php
$schemas['survey'] = [
    'confirm' => false,  // ← ステップ完了後も確認画面なしで即送信
    'steps'   => [ ... ],
    ...
];
```

### Cookie不使用の確認

HXFE v1.2.1 はCookieを一切使用しません。
値の保持はhidden inputのJSON（サーバーサイド）で行っています。
EUのGDPR / Cookie規制の観点でも安全に使用できます。


---

## スキーマファイルの分割管理 {#schema-files}

フォームのスキーマは `functions.php` に直接書かなくても大丈夫です。
別のファイルに切り出して読み込む方法もよく使われます。

**フォームが1〜2個のうちは** `functions.php` に書いてしまっても問題ありません。
**フォームが増えてきたり、チームで管理する場合は** 以下のように別ファイルに分けると整理しやすくなります。

### 方法① テーマフォルダに別ファイルを作って読み込む（シンプル・おすすめ）

`functions.php` の末尾に1行追加して、別ファイルを読み込みます。

```php
// テーマの functions.php の末尾に追加
require_once get_template_directory() . '/inc/hxfe-forms.php';
```

```php
// テーマフォルダ/inc/hxfe-forms.php
<?php
add_filter( 'hxfe_schemas', function( $schemas ) {

    $schemas['contact'] = [
        'id'     => 'contact',
        'to'     => 'admin@example.com',
        'fields' => [ ... ],
    ];

    return $schemas;
});
```

### 方法② 専用プラグインとして管理する（テーマを変えても安心）

テーマの外にフォーム定義を置く方法です。
テーマを変更・更新してもフォームが消えません。
「フォームはサイトの設定」として長期的に管理したい場合に向いています。

```
wp-content/
  plugins/
    my-forms/
      my-forms.php       ← メインファイル
      schemas/
        contact.php      ← お問い合わせ
        seminar.php      ← セミナー申込
        survey.php       ← アンケート
```

```php
// my-forms.php
<?php
/**
 * Plugin Name: My Forms
 * Description: HXFEのフォーム定義
 */

add_filter( 'hxfe_schemas', function( $schemas ) {
    // schemas/ フォルダ内の全PHPファイルを自動読み込み
    foreach ( glob( __DIR__ . '/schemas/*.php' ) as $file ) {
        require_once $file;
    }
    return $schemas;
});
```

```php
// schemas/contact.php
<?php
$schemas['contact'] = [
    'id'      => 'contact',
    'to'      => 'admin@example.com',
    'subject' => 'お問い合わせ: {name}',
    'fields'  => [
        [ 'key' => 'name',  'type' => 'text',     'label' => 'お名前', 'required' => true ],
        [ 'key' => 'email', 'type' => 'email',    'label' => 'メール', 'required' => true ],
        [ 'key' => 'body',  'type' => 'textarea', 'label' => '内容',   'required' => true ],
        [ 'key' => 'hp',    'type' => 'honeypot' ],
    ],
];
```

`glob()` を使うと `schemas/` フォルダにファイルを追加するだけで自動的に読み込まれます。

### どちらを選ぶか

| 状況 | 推奨 |
|---|---|
| テーマとセットで管理したい | ① `inc/hxfe-forms.php` |
| テーマを変えてもフォームを維持したい | ② 独立プラグイン |
| フォームだけGitで別管理したい | ② 独立プラグイン |
| 複数サイトで同じフォームを使い回したい | ② 独立プラグイン |

> **ポイント:** テーマはデザインの都合で変えることがあります。フォームはサイトの「機能」なので、テーマとは別管理にしておくと安心です。フォームが増えてきたら、方法②への移行を検討してみてください。

[▲ 目次に戻る](#toc)

## 管理画面のlint警告一覧 {#lint}

管理画面（設定 → Form Engine）のフォーム一覧で `⚠` マークが表示された場合、スキーマに問題があります。以下の警告メッセージと対処法を参照してください。

| 警告メッセージ | 原因 | 対処法 |
|---|---|---|
| `Missing: id` | `id` キーがない | `'id' => 'フォームID'` を追加 |
| `Missing: 'to' or 'to_rules'` | 送信先メールがない | `'to' => 'admin@example.com'` を追加 |
| `Invalid email in 'to'` | メールアドレスが不正 | 正しいメールアドレスに修正 |
| `Missing: 'subject'` | 件名がない | `'subject' => '件名: {name}'` を追加 |
| `Missing or invalid: 'fields'` | fieldsがない | `'fields' => [...]` を追加 |
| `Missing 'key'` | フィールドにkeyがない | `'key' => 'フィールド名'` を追加 |
| `Duplicate key` | 同じkeyが重複している | 各フィールドのkeyを一意にする |
| `Unknown type` | 存在しないフィールドタイプ | 15種類の有効なtypeを使用する |
| `needs 'options' array` | select/radio/checkbox_groupにoptionsがない | `'options' => [...]` を追加 |
| `Missing 'label'` | フィールドにlabelがない | `'label' => 'フィールド名'` を追加 |
| `cascade_from requires cascade_options` | cascade_fromにcascade_optionsがない | `'cascade_options' => [...]` を追加 |
| `chatbot mode requires 'bot_message'` | chatbotモードのフィールドにbot_messageがない | `'bot_message' => '質問文'` を追加 |
| `recaptcha field has no secret key` | reCAPTCHAのシークレットキーが未設定 | 設定 → HXFE でシークレットキーを設定 |
| `Recommended: add a honeypot field` | スパム対策フィールドがない | `[ 'key' => 'hp', 'type' => 'honeypot' ]` を追加 |
| `steps[N]: Missing 'fields'` | ステップにfieldsがない | ステップに `'fields' => ['key1', 'key2']` を追加 |
| `steps[N]: Unknown field key` | ステップに存在しないフィールドkeyがある | フィールドkeyのスペルを確認 |

---

## よくある質問 {#faq}

### Q: フォームを送信してもメールが届かない {#faq-mail}

**A:** サーバーのPHPメール設定の問題がほとんどです。

**解決方法:**
1. **設定 → Form Engine → SMTP Settings** でSMTPを設定する
2. Gmailのアプリパスワードを使うのが最も簡単

---

### Q: functions.phpに書いたのにフォームが表示されない {#faq-not-show}

**A:** 以下を確認してください。

1. ショートコードのIDがスキーマのIDと一致しているか
   ```
   [hxfe_form id="contact"]  ← ここの "contact"
   $schemas['contact'] = [   ← ここの "contact"
   ```
2. `add_filter` の中で `return $schemas;` を書いているか
3. PHPの文法エラーがないか（サイトが壊れている場合はPHPエラーが原因）

---

### Q: ファイルアップロードはできますか？ {#faq-file}

**A:** v1.3.1以降で対応しています。`type: 'file'` フィールドを追加するだけです。

アップロードされたファイルは**管理者宛メールに添付**されます。
送信後にサーバーから**自動削除**されるため、WordPressのメディアライブラリには保存されません。
Googleフォームと異なりログイン不要で使用できます。

```php
[ 'key'        => 'attachment',
  'type'       => 'file',
  'label'      => '添付ファイル',
  'accept'     => '.pdf,.doc,.docx',
  'max_size_mb' => 5,
  'mime_types' => [
      'application/pdf',
      'application/msword',
  ],
]
```

---

Q: 確認画面をスキップして直接送信したい {#faq-skip-confirm}

**A:** v1.2.0以降で対応済みです。スキーマに `confirm: false` を追加するだけです。

```php
$schemas['contact'] = [
    'id'      => 'contact',
    'to'      => 'admin@example.com',
    'confirm' => false,   // ← 確認画面をスキップして即送信
    'fields'  => [ ... ],
];
```

**フロー:**
- `confirm` 未指定（デフォルト）: 入力 → 確認 → 完了
- `confirm: false`: 入力 → 完了（確認画面なし）

ステップフォーム（`steps` 配列）でも同様に動作します。

**向いている場面:**
- ニュースレター登録（名前とメールだけのシンプルなフォーム）
- アンケート（確認不要な短いもの）
- チャットbotモード（`step_mode: 'chatbot'` との組み合わせ）

---

### Q: 送信データをWordPressの管理画面で確認したい {#faq-log}

**A:** 現在のバージョンでは送信データをDBに保存しません。以下の方法で対応できます。

- 管理者宛メールとBCCでアーカイブする
- Zapier・Make（旧Integromat）などの外部サービスと連携する

送信履歴のDB保存は今後のバージョンで対応予定です。

---

### Q: 複数のフォームを1つのサイトに設置できる？ {#faq-multiple}

**A:** できます。`$schemas` に複数のキーを追加してください。

```php
add_filter( 'hxfe_schemas', function( $schemas ) {
    $schemas['contact'] = [ ... ];  // お問い合わせフォーム
    $schemas['seminar'] = [ ... ];  // セミナー申込フォーム
    $schemas['survey']  = [ ... ];  // アンケートフォーム
    return $schemas;
});
```

各フォームはIDが異なるショートコードで呼び出します。

```
[hxfe_form id="contact"]
[hxfe_form id="seminar"]
```

---

### Q: 条件分岐がリアルタイムで動作しない {#faq-condition}

**A:** JavaScriptが無効になっていないか確認してください。また、他のプラグインがJavaScriptエラーを起こしている場合に動作しないことがあります。ブラウザの開発者ツール（F12）のコンソールタブでエラーを確認してください。

---

### Q: スキーマを functions.php 以外のファイルで管理できますか？ {#faq-other-file}

**A:** できます。`functions.php` に書くのが難しい・整理したいという場合は
別ファイルに切り出して読み込むのが一般的です。

フォームが1〜2個のうちは `functions.php` に直接書いてもまったく問題ありません。
フォームが増えてきたら「スキーマファイルの分割管理」のセクションを参考に整理してみてください。

---

### Q: 管理画面からPHPファイルを直接編集できないようにしたい {#faq-disallow-edit}

**A:** `wp-config.php` に1行追加するだけで無効化できます。

```php
// wp-config.php に追記
define( 'DISALLOW_FILE_EDIT', true );
```

これで管理画面の「外観 → テーマエディタ」「プラグイン → プラグインエディタ」が非表示になり、PHPファイルを直接書き換えられなくなります。

**さらに徹底する場合**（デプロイフローが整っている環境向け）

```php
// プラグイン・テーマのインストール・更新も管理画面から禁止
define( 'DISALLOW_FILE_MODS', true );
```

> ⚠️ `DISALLOW_FILE_MODS` を有効にするとWordPress本体・プラグインの自動更新も止まります。セキュリティアップデートを手動で管理できる環境のみ推奨。

HXFEの「フォームはコードで管理する」設計と組み合わせることで、**コードはデプロイで管理し、管理画面からは触らない**という一貫した運用が実現できます。

---

### Q: iframeで埋め込んだフォームの高さが合わない {#faq-iframe-height}

**A:** フォームを設置しているサイトのCORSの設定を確認してください。また、iframeのURLに直接アクセスして（`/hxfe-iframe/contact/`）フォームが正常に表示されるか確認してください。

[▲ 目次に戻る](#toc)

---

*このファイル（HXFE-manual.md）はプラグインのZIPに同梱されています。*

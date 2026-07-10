# HXFE — AI Agent Entry Point

Read **llms.txt** first. It contains task routing, rules, and file map.
Load **ai-reference.md** only when your task requires schema or feature details (see routing table in llms.txt).

Do not load both this file and ai-reference.md at the same time — llms.txt + one targeted file is enough for most tasks.

## アクションフック（v1.4.5〜）

### hxfe_after_submit

フォーム送信が正常に完了した直後（メール送信後）に発火する。外部プラグインが送信データを購読できる。HXMD（Markdown Log Manager）の自動取り込みがこのフックを使用している。

```php
/**
 * @param string $form_id フォームID（$schema['id']）
 * @param array  $values  送信された値（サニタイズ済み）
 * @param array  $schema  フォームスキーマ
 */
do_action( 'hxfe_after_submit', $form_id, $values, $schema );
```

発火箇所は includes/ajax-handlers.php の4箇所（すべての送信完了ポイント）:

1. validate内の即時送信（confirm: false の通常フロー）
2. handle_submit の確認画面経由送信
3. handle_step 内の即時送信（マルチステップ + confirm: false）
4. handle_step_submit の最終送信（マルチステップ）

**重要:** 送信完了ポイントを追加・変更する場合は、必ず hxfe_send_emails() の直後にこのフックを発火させること。HXMDなどの連携プラグインが送信を取りこぼす原因になる。

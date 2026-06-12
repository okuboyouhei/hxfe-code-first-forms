<?php
/**
 * ファイルアップロード処理
 *
 * フォームから送信されたファイルを WordPress の一時領域に受け取り、
 * メールへの添付とテンポラリファイルの管理を行う。
 *
 * 設計方針:
 *   - wp_handle_upload() を使ってWordPress標準のアップロード処理に乗る
 *   - メディアライブラリには保存しない（メール送信後に削除）
 *   - ファイルパスをセッションではなく nonce 付き hidden フィールドで引き回す
 *   - アップロード可能なMIMEタイプはスキーマの accept キーで制限する
 *
 * スキーマの書き方:
 *   [ 'key' => 'attachment', 'type' => 'file',
 *     'label'       => '添付ファイル',
 *     'accept'      => '.pdf,.doc,.docx,image/*',  // ブラウザのaccept属性
 *     'max_size_mb' => 5,                           // 最大ファイルサイズ（MB）
 *     'mime_types'  => [ 'application/pdf',         // サーバー側のMIMEホワイトリスト
 *                        'application/msword',
 *                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
 *                        'image/jpeg', 'image/png', 'image/gif' ],
 *   ]
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * $_FILES からファイルを処理してアップロードディレクトリに保存する。
 *
 * @param array  $field     フィールド定義。
 * @param string $form_id   フォームID（サブディレクトリ名に使用）。
 * @return array{ path: string, name: string, error: string }
 *   path:  サーバー上の絶対パス（成功時）。
 *   name:  元のファイル名（サニタイズ済み）。
 *   error: エラーメッセージ（成功時は空文字）。
 */
function hxfe_handle_file_upload( array $field, string $form_id ) {
	$key = $field['key'];

	// ファイルが送信されていない場合（nonce検証はajax-handlers.phpで実施済み）
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- nonce verified in ajax-handlers.php before this function is called
	if ( empty( $_FILES[ $key ] ) || UPLOAD_ERR_NO_FILE === $_FILES[ $key ]['error'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( ! empty( $field['required'] ) ) {
			return [
				'path'  => '',
				'name'  => '',
				'error' => __( 'This field is required.', 'hxfe-code-first-forms' ),
			];
		}
		return [ 'path' => '', 'name' => '', 'error' => '' ];
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- nonce verified in ajax-handlers.php; wp_handle_upload handles sanitization
	$file = $_FILES[ $key ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	// アップロードエラーチェック
	if ( UPLOAD_ERR_OK !== $file['error'] ) {
		return [
			'path'  => '',
			'name'  => '',
			'error' => __( 'File upload failed. Please try again.', 'hxfe-code-first-forms' ),
		];
	}

	// ファイルサイズチェック
	$max_bytes = (int) ( $field['max_size_mb'] ?? 5 ) * 1024 * 1024;
	if ( $file['size'] > $max_bytes ) {
		// translators: %s: maximum file size in MB
		$size_error = sprintf( __( 'File size exceeds the maximum of %sMB.', 'hxfe-code-first-forms' ), $field['max_size_mb'] ?? 5 );
		return [ 'path' => '', 'name' => '', 'error' => $size_error ];
	}

	// MIMEタイプのホワイトリストチェック
	$allowed_mimes = hxfe_get_allowed_mime_types( $field );
	if ( ! empty( $allowed_mimes ) ) {
		// wp_check_filetype_and_ext() でサーバー側のMIMEを確認（ファイル内容から判定）
		$filetype = wp_check_filetype_and_ext(
			$file['tmp_name'],
			sanitize_file_name( $file['name'] ),
			$allowed_mimes
		);
		if ( empty( $filetype['type'] ) ) {
			return [
				'path'  => '',
				'name'  => '',
				'error' => __( 'File type is not allowed.', 'hxfe-code-first-forms' ),
			];
		}
	}

	// wp_handle_upload() でアップロード処理（WordPressの標準フロー）
	// hxfe専用サブディレクトリに保存するためにupload_dirフィルターを使う
	$upload_dir_filter = static function( $dirs ) use ( $form_id ) {
		$dirs['subdir'] = '/hxfe-uploads/' . $form_id . '/' . gmdate( 'Y/m' );
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
		return $dirs;
	};
	add_filter( 'upload_dir', $upload_dir_filter );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_handle_upload handles validation
	$overrides = [
		'test_form' => false, // nonce検証はHXFEが別途行っているためスキップ
		'test_type' => ! empty( $allowed_mimes ), // MIMEホワイトリストがある場合のみ検証
	];
	if ( ! empty( $allowed_mimes ) ) {
		$overrides['mimes'] = $allowed_mimes;
	}

	$result = wp_handle_upload( $file, $overrides ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	remove_filter( 'upload_dir', $upload_dir_filter );

	if ( isset( $result['error'] ) ) {
		return [
			'path'  => '',
			'name'  => '',
			'error' => __( 'File upload failed: ', 'hxfe-code-first-forms' ) . $result['error'],
		];
	}

	return [
		'path'  => $result['file'],
		'name'  => sanitize_file_name( $file['name'] ),
		'error' => '',
	];
}

/**
 * フィールド定義から許可するMIMEタイプの配列を返す。
 *
 * スキーマの mime_types キーが未指定の場合は、accept から推測する。
 *
 * @param array $field フィールド定義。
 * @return array MIMEタイプの配列（空の場合はWordPressのデフォルトを使う）。
 */
function hxfe_get_allowed_mime_types( array $field ) {
	// mime_types が明示指定されている場合はそれを使う
	if ( ! empty( $field['mime_types'] ) && is_array( $field['mime_types'] ) ) {
		$result = [];
		foreach ( $field['mime_types'] as $mime ) {
			$mime = sanitize_mime_type( $mime );
			// 'ext' => 'mime/type' 形式に変換（wp_check_filetype_and_ext に必要）
			$ext = hxfe_mime_to_ext( $mime );
			if ( $ext ) {
				$result[ $ext ] = $mime;
			}
		}
		return $result;
	}

	// mime_types 未指定の場合は HXFEの安全なデフォルトを使う
	// WordPressのデフォルトは広すぎるため（SVG/HTML/JS等が通りうる）
	$hxfe_safe_defaults = [
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xls'  => 'application/vnd.ms-excel',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'txt'  => 'text/plain',
		'csv'  => 'text/csv',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
	];

	// accept 属性がある場合はその範囲でデフォルトを絞る
	$accept = $field['accept'] ?? '';
	if ( '' === $accept ) {
		// accept 未指定 → HXFEの安全なデフォルトを全部許可
		return $hxfe_safe_defaults;
	}

	// image/* の wildcard
	if ( str_contains( $accept, 'image/*' ) ) {
		return array_filter(
			$hxfe_safe_defaults,
			fn( $mime ) => str_starts_with( $mime, 'image/' )
		);
	}

	// .pdf,.doc 等の拡張子形式から安全なデフォルトを絞り込む
	$exts = array_map(
		fn( $e ) => ltrim( trim( $e ), '.' ),
		explode( ',', $accept )
	);
	$filtered = [];
	foreach ( $exts as $ext ) {
		if ( isset( $hxfe_safe_defaults[ $ext ] ) ) {
			$filtered[ $ext ] = $hxfe_safe_defaults[ $ext ];
		}
	}

	// 絞り込み結果が空の場合（未知の拡張子のみ）はデフォルト全体を使う
	return ! empty( $filtered ) ? $filtered : $hxfe_safe_defaults;
}

/**
 * MIMEタイプから拡張子を返す（内部用）。
 *
 * @param string $mime
 * @return string 拡張子（ドットなし）。不明の場合は空文字。
 */
function hxfe_mime_to_ext( string $mime ) {
	$map = [
		'application/pdf'      => 'pdf',
		'application/msword'   => 'doc',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
		'application/vnd.ms-excel' => 'xls',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
		'application/zip'      => 'zip',
		'text/plain'           => 'txt',
		'text/csv'             => 'csv',
		'image/jpeg'           => 'jpg',
		'image/png'            => 'png',
		'image/gif'            => 'gif',
		'image/webp'           => 'webp',
	];
	return $map[ $mime ] ?? '';
}

/**
 * フォームの全ファイルフィールドを処理してアップロード結果を返す。
 *
 * @param array  $schema   フォームスキーマ。
 * @param string $form_id  フォームID。
 * @return array{
 *   paths:  array<string,string>,  // field_key => 絶対パス
 *   names:  array<string,string>,  // field_key => ファイル名
 *   errors: array<string,string>,  // field_key => エラーメッセージ
 * }
 */
function hxfe_process_file_uploads( array $schema, string $form_id ) {
	$paths  = [];
	$names  = [];
	$errors = [];

	foreach ( $schema['fields'] ?? [] as $field ) {
		if ( ( $field['type'] ?? '' ) !== 'file' ) {
			continue;
		}
		$result = hxfe_handle_file_upload( $field, $form_id );
		$key    = $field['key'];

		if ( '' !== $result['error'] ) {
			$errors[ $key ] = $result['error'];
		} elseif ( '' !== $result['path'] ) {
			$paths[ $key ] = $result['path'];
			$names[ $key ] = $result['name'];
		}
	}

	return [ 'paths' => $paths, 'names' => $names, 'errors' => $errors ];
}

/**
 * アップロードされたファイルを削除する（メール送信後に呼ぶ）。
 *
 * @param array $file_paths 削除するファイルパスの配列。
 */
function hxfe_cleanup_uploaded_files( array $file_paths ) {
	foreach ( $file_paths as $path ) {
		if ( '' !== $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}
}

/**
 * HXFE のアップロードディレクトリに .htaccess を設置して PHP 実行を禁止する。
 * wp_handle_upload() が初回アップロード時にディレクトリを作成した後に呼ぶ。
 *
 * .exe / .php 等の危険なファイルを万が一保存してしまった場合でも
 * Webサーバーがそれを実行しないよう二重に防御する。
 *
 * @param string $upload_dir アップロードベースディレクトリのパス。
 */
function hxfe_protect_upload_dir( string $upload_dir ) {
	$htaccess = $upload_dir . '/hxfe-uploads/.htaccess';
	if ( file_exists( $htaccess ) ) {
		return; // 既に設置済み
	}

	$dir = dirname( $htaccess );
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	// PHP・CGI・スクリプトの実行を全て禁止
	$rules  = "# HXFEアップロードディレクトリ — スクリプト実行禁止
";
	$rules .= "<Files *>
";
	$rules .= "  SetHandler default-handler
";
	$rules .= "</Files>
";
	$rules .= "Options -ExecCGI
";
	$rules .= "php_flag engine off
";
	$rules .= "AddType text/plain .php .php3 .php4 .php5 .php7 .phtml .pl .py .cgi .sh
";

	// WP_Filesystem を使って書き込む
	global $wp_filesystem;
	if ( empty( $wp_filesystem ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}
	if ( $wp_filesystem ) {
		$wp_filesystem->put_contents( $htaccess, $rules, FS_CHMOD_FILE );
	}
}

// WordPress初期化時にアップロードディレクトリを保護する
add_action( 'init', function() {
	$upload = wp_upload_dir();
	hxfe_protect_upload_dir( $upload['basedir'] );
}, 20 );

<?php
/**
 * Plugin Name: Гомеопаты получают письма обо всех консультациях
 * Description: Подписывает на комментарии к консультации всех пользователей с ролью homeopath,
 *              чтобы заявки и ответы клиентов кто-то получал без ручных галочек.
 *              Два момента: при создании консультации (Lt\PostCreated) и при каждом новом
 *              комментарии в ней (если подписки нет: консультация создана до установки
 *              Comment Mail, подписку сняли или добавили нового гомеопата).
 *              Клиент подписан самим Lt (Lt\CommentMailPro), но если подписки у клиента тоже нет
 *              (та же причина), она создаётся здесь по автору консультации.
 *              Подписка записывается напрямую в таблицу Comment Mail, как это делает Lt.
 *              Чтобы отключить, удалить этот файл.
 * Version: 1.1
 */
defined( 'ABSPATH' ) || exit;

function hco_subscribe_user_to_post( $user, $post_id ) {
	global $wpdb;

	$table = $wpdb->prefix . 'comment_mail_subs';

	$exists = $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM {$table} WHERE post_id = %d AND user_id = %d AND comment_id = 0 LIMIT 1",
		$post_id,
		$user->ID
	) );
	if ( $exists ) {
		return;
	}

	$wpdb->query( $wpdb->prepare(
		"INSERT INTO {$table} (`key`, user_id, post_id, comment_id, deliver, fname, lname, email, status, insertion_time) VALUES (%s, %d, %d, 0, 'asap', %s, %s, %s, 'subscribed', %d)",
		'k' . strtolower( wp_generate_password( 18, false ) ),
		$user->ID,
		$post_id,
		$user->first_name,
		$user->last_name,
		$user->user_email,
		time()
	) );
}

function hco_table_exists() {
	global $wpdb;
	$table = $wpdb->prefix . 'comment_mail_subs';
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
}

add_action( 'Lt\PostCreated', function ( $user, $post_id ) {
	if ( ! hco_table_exists() ) {
		return;
	}
	foreach ( get_users( array( 'role' => 'homeopath' ) ) as $doc ) {
		if ( $user && $user->exists() && (int) $doc->ID === (int) $user->ID ) {
			continue;
		}
		hco_subscribe_user_to_post( $doc, $post_id );
	}
}, 20, 2 );

// Новый комментарий в консультации: добираем недостающие подписки до того, как Comment Mail поставит письма в очередь.
add_action( 'wp_insert_comment', function ( $comment_id, $comment ) {
	$post = get_post( $comment->comment_post_ID );
	if ( ! $post || 'discursion' !== $post->post_type || ! hco_table_exists() ) {
		return;
	}

	foreach ( get_users( array( 'role' => 'homeopath' ) ) as $doc ) {
		hco_subscribe_user_to_post( $doc, $post->ID );
	}

	$client = get_user_by( 'id', $post->post_author );
	if ( $client && ! in_array( 'administrator', (array) $client->roles, true ) && ! in_array( 'homeopath', (array) $client->roles, true ) ) {
		hco_subscribe_user_to_post( $client, $post->ID );
	}
}, 1, 2 );

<?php
/**
 * Plugin Name: Гомеопаты получают письма обо всех консультациях
 * Description: При создании консультации (Lt\PostCreated) подписывает на комментарии к ней всех
 *              пользователей с ролью homeopath, чтобы заявки и ответы клиентов кто-то получал без ручных галочек.
 *              Клиент подписан самим Lt (Lt\CommentMailPro). Подписка записывается напрямую в
 *              таблицу Comment Mail, как это делает Lt. Чтобы отключить, удалить этот файл.
 * Version: 1.0
 */
defined( 'ABSPATH' ) || exit;

add_action( 'Lt\PostCreated', function ( $user, $post_id ) {
	global $wpdb;

	$table = $wpdb->prefix . 'comment_mail_subs';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return;
	}

	foreach ( get_users( array( 'role' => 'homeopath' ) ) as $admin ) {
		if ( $user && $user->exists() && (int) $admin->ID === (int) $user->ID ) {
			continue;
		}
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$table} WHERE post_id = %d AND user_id = %d AND comment_id = 0 LIMIT 1",
			$post_id,
			$admin->ID
		) );
		if ( $exists ) {
			continue;
		}
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (`key`, user_id, post_id, comment_id, deliver, fname, lname, email, status, insertion_time) VALUES (%s, %d, %d, 0, 'asap', %s, %s, %s, 'subscribed', %d)",
			'k' . strtolower( wp_generate_password( 18, false ) ),
			$admin->ID,
			$post_id,
			$admin->first_name,
			$admin->last_name,
			$admin->user_email,
			time()
		) );
	}
}, 20, 2 );

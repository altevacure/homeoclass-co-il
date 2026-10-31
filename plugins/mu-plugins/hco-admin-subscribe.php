<?php
/**
 * Plugin Name: Подписки на консультации: гомеопаты и резервный ящик
 * Description: Подписывает на комментарии к консультации:
 *              1) гомеопатов (роль homeopath). Если у записи задан список _lt_doctors
 *                 (lt-private-consultations.php), то только тех, кто в нём; иначе всех;
 *              2) резервный ящик: пользователей с ролью hco_backup (копия всей переписки,
 *                 и клиентских, и врачебных сообщений; ответы с этого ящика блокирует lt-rve-guard);
 *              3) автора консультации (клиента), если у него подписки почему-то нет.
 *              Закрытые целиком записи (_lt_locked) не получает никто.
 *              Срабатывает при создании консультации (Lt\PostCreated) и при каждом новом комментарии
 *              в ней, поэтому консультации, созданные до установки Comment Mail, подхватываются сами.
 *              Подписка записывается напрямую в таблицу Comment Mail, как это делает Lt.
 *              Чтобы отключить, удалить этот файл.
 * Version: 1.2
 */
defined( 'ABSPATH' ) || exit;

function hco_table_exists() {
	global $wpdb;
	$table = $wpdb->prefix . 'comment_mail_subs';
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
}

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

/** Кого и на какую консультацию подписывать. $skip: пользователь, которого пропустить (автор события). */
function hco_subscribe_for_post( $post_id, $skip = null ) {
	if ( ! hco_table_exists() || get_post_meta( $post_id, '_lt_locked', true ) ) {
		return;
	}

	$doctor_ids = get_post_meta( $post_id, '_lt_doctors', true );
	$doctor_ids = is_array( $doctor_ids ) ? array_map( 'intval', $doctor_ids ) : array();

	$people = array();
	foreach ( get_users( array( 'role' => 'homeopath' ) ) as $doc ) {
		if ( ! $doctor_ids || in_array( (int) $doc->ID, $doctor_ids, true ) ) {
			$people[] = $doc;
		}
	}
	foreach ( get_users( array( 'role' => 'hco_backup' ) ) as $backup ) {
		$people[] = $backup;
	}

	$post   = get_post( $post_id );
	$client = $post ? get_user_by( 'id', $post->post_author ) : null;
	if ( $client && ! array_intersect( array( 'administrator', 'homeopath', 'hco_backup' ), (array) $client->roles ) ) {
		$people[] = $client;
	}

	foreach ( $people as $person ) {
		if ( $skip && $skip->exists() && (int) $person->ID === (int) $skip->ID ) {
			continue;
		}
		hco_subscribe_user_to_post( $person, $post_id );
	}
}

add_action( 'Lt\PostCreated', function ( $user, $post_id ) {
	hco_subscribe_for_post( (int) $post_id );
}, 20, 2 );

// Новый комментарий в консультации: добираем недостающие подписки до того, как Comment Mail поставит письма в очередь.
add_action( 'wp_insert_comment', function ( $comment_id, $comment ) {
	$post = get_post( $comment->comment_post_ID );
	if ( ! $post || 'discursion' !== $post->post_type ) {
		return;
	}
	hco_subscribe_for_post( (int) $post->ID );
}, 1, 2 );

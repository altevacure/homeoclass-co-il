<?php
/**
 * Plugin Name: Пункт меню «אזור אישי»
 * Description: Пункт меню с классом hco-personal и адресом #hco-personal. Гостя ведёт на форму входа,
 *              вошедшего клиента на его последнюю консультацию, а если консультации ещё нет, на страницу
 *              с опросниками. Врача и администратора ведёт в список консультаций (для администратора
 *              это консоль). Чтобы отключить, удалить файл и пункт меню.
 * Version: 1.0
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'wp_nav_menu_objects', function ( $items ) {
	foreach ( $items as $item ) {
		if ( ! in_array( 'hco-personal', (array) $item->classes, true ) ) {
			continue;
		}
		$item->url = hco_personal_url();
	}
	return $items;
} );

function hco_personal_url() {
	if ( ! is_user_logged_in() ) {
		return wp_login_url( home_url( '/' ) );
	}

	$user = wp_get_current_user();

	if ( in_array( 'administrator', (array) $user->roles, true ) || in_array( 'homeopath', (array) $user->roles, true ) ) {
		return admin_url( 'edit.php?post_type=discursion' );
	}

	$posts = get_posts( array(
		'post_type'      => 'discursion',
		'post_status'    => 'publish',
		'author'         => $user->ID,
		'posts_per_page' => 1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'fields'         => 'ids',
	) );
	if ( $posts ) {
		return get_permalink( $posts[0] );
	}

	$forms = get_page_by_path( 'oprosniki' );
	return $forms ? get_permalink( $forms ) : home_url( '/' );
}

<?php
/**
 * Plugin Name: LT — консоль только для администратора
 * Description: Клиентов и гомеопатов не пускает в wp-admin: в консоли виден виджет
 *              «Активность» со ссылками на записи сайта, а им там делать нечего.
 *              Вместо консоли человек уезжает туда же, куда после входа: клиент - в свой
 *              кабинет, гомеопат - на страницу для гомеопатов, с истёкшим доступом - на
 *              продление. Из верхней панели убираются ссылки в консоль; счётчик доступа
 *              и «Выйти» остаются. admin-ajax.php и admin-post.php открыты: на них
 *              работают кнопки сайта («Участвовать в разборе», круглый стол).
 *              Чтобы отключить, удалить этот файл.
 * Version: 1.0
 */

defined( 'ABSPATH' ) || exit;

class LT_Admin_Lock {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'block' ), 1 );
		// В самом конце: wp_admin_bar_my_account_item добавляет «Привет, имя» со ссылкой
		// на профиль только на приоритете 9991.
		add_action( 'admin_bar_menu', array( __CLASS__, 'strip_bar' ), PHP_INT_MAX );
	}

	private static function allowed() {
		return current_user_can( 'manage_options' );
	}

	public static function block() {
		if ( self::allowed() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		// Обработчики кнопок сайта живут на admin-post.php, а admin_init срабатывает и там.
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( (string) $_SERVER['SCRIPT_NAME'] ) : '';
		if ( 'admin-post.php' === $script || 'async-upload.php' === $script ) {
			return;
		}

		$user = wp_get_current_user();
		$url  = apply_filters( 'login_redirect', home_url( '/' ), '', $user );
		if ( ! $url || false !== strpos( $url, '/wp-admin' ) ) {
			$url = home_url( '/' );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/** Все пункты верхней панели, ведущие в консоль, кроме выхода. */
	public static function strip_bar( $bar ) {
		if ( self::allowed() ) {
			return;
		}

		$admin = admin_url();
		foreach ( $bar->get_nodes() as $node ) {
			if ( 'logout' === $node->id || empty( $node->href ) || 0 !== strpos( $node->href, $admin ) ) {
				continue;
			}
			// Узел с вложенными пунктами (меню учётной записи) оставляем, но без ссылки.
			if ( in_array( $node->id, array( 'my-account', 'user-actions' ), true ) ) {
				$node->href = false;
				$bar->add_node( (array) $node );
				continue;
			}
			$bar->remove_node( $node->id );
		}
		$bar->remove_node( 'wp-logo' );
	}
}

LT_Admin_Lock::init();

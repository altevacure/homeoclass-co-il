<?php
/**
 * Plugin Name: Срок доступа на верхней панели
 * Description: Lt выводит в панель WordPress строку «Ваш доступ активен еще» через шорткод [tminus]
 *              (плагин jquery-t-countdown-widget) и по-русски. Здесь она убирается и заменяется
 *              своей, на иврите: недели, дни и часы без секунд, считается на сервере и без скриптов.
 *              Доступ закрывает не панель, а сам Lt и lt-admin-lock, поэтому на защиту это не влияет.
 *              Администраторам панель срока не показывается. Чтобы отключить, удалить файл
 *              (тогда вернётся строка Lt с сырым шорткодом).
 * Version: 1.0
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_bar_menu', function ( $bar ) {
	$bar->remove_node( 'my_page' );

	$user = wp_get_current_user();
	if ( ! $user->ID || user_can( $user, 'manage_options' ) ) {
		return;
	}

	$from = (int) get_user_meta( $user->ID, 'paidfrom', true );
	$to   = (int) get_user_meta( $user->ID, 'paidto', true );
	$now  = time();

	if ( $from && $from > $now ) {
		$title = 'הגישה שלכם תתחיל בעוד ' . hco_left_text( $from - $now );
	} elseif ( $to && $to > $now ) {
		$title = 'האזור זמין עוד ' . hco_left_text( $to - $now );
	} elseif ( $to && $to <= $now ) {
		$title = 'הגישה שלכם הסתיימה';
	} else {
		return;
	}

	$bar->add_node( array(
		'id'    => 'hco_access',
		'title' => esc_html( $title ),
		'meta'  => array( 'class' => 'hco-access' ),
	) );
}, 1000 );

/**
 * «5 שבועות, 2 ימים ו-3 שעות»: единицы с нулём пропускаются, секунды не показываются.
 */
function hco_left_text( $seconds ) {
	$weeks   = (int) floor( $seconds / WEEK_IN_SECONDS );
	$seconds -= $weeks * WEEK_IN_SECONDS;
	$days    = (int) floor( $seconds / DAY_IN_SECONDS );
	$seconds -= $days * DAY_IN_SECONDS;
	$hours   = (int) floor( $seconds / HOUR_IN_SECONDS );

	$parts = array();
	if ( $weeks ) {
		$parts[] = 1 === $weeks ? 'שבוע' : $weeks . ' שבועות';
	}
	if ( $days ) {
		$parts[] = 1 === $days ? 'יום' : $days . ' ימים';
	}
	if ( $hours ) {
		$parts[] = 1 === $hours ? 'שעה' : $hours . ' שעות';
	}

	if ( ! $parts ) {
		return 'פחות משעה';
	}
	if ( 1 === count( $parts ) ) {
		return $parts[0];
	}

	$last = array_pop( $parts );
	return implode( ', ', $parts ) . ' ' . ( ctype_digit( $last[0] ) ? 'ו-' : 'ו' ) . $last;
}

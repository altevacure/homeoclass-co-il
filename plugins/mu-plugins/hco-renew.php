<?php
/**
 * Plugin Name: Продление доступа: бесплатно или с пожертвованием
 * Description: Шорткод [hco_renew] для страницы /renew/. Две дорожки, обе открывают клиенту ещё неделю:
 *              1) «продолжить бесплатно»;
 *              2) пожертвование через PayPal / Bit / PayBox. Доступ от платежа не зависит
 *                 (это добровольное пожертвование, не оплата услуги), поэтому подтверждения оплаты
 *                 от провайдера не нужно: кнопка продлевает доступ и переводит клиента на страницу оплаты.
 *              Ссылки задаются в HCO_RENEW_LINKS; кнопка без ссылки показывается неактивной.
 *              Даты те же, что у Lt: user meta paidfrom/paidto и действие Lt\PaidChange.
 *              Тексты на иврите требуют вычитки носителем.
 * Version: 1.0
 */
defined( 'ABSPATH' ) || exit;

/** Ссылки на оплату. Пустая строка: кнопка пока без ссылки. */
const HCO_RENEW_LINKS = array(
	'paypal' => 'https://www.paypal.me/curshen/72ILS',
	'bit'    => '',
	'paybox' => '',
);

const HCO_RENEW_DAYS = 7;

/** Продлевает доступ клиента на неделю: с текущего конца срока, а если он прошёл, то с сегодняшнего дня. */
function hco_renew_extend( $user_id ) {
	$now      = time();
	$old_from = (int) get_user_meta( $user_id, 'paidfrom', true );
	$old_to   = (int) get_user_meta( $user_id, 'paidto', true );

	$paidfrom = ( $old_from && $old_from <= $now && $old_to > $now ) ? $old_from : $now;
	$base     = ( $old_to > $now ) ? $old_to : $now;
	$paidto   = $base + HCO_RENEW_DAYS * DAY_IN_SECONDS;

	update_user_meta( $user_id, 'paidfrom', $paidfrom );
	update_user_meta( $user_id, 'paidto', $paidto );
	do_action( 'Lt\PaidChange', $user_id, $paidfrom, $paidto );
}

add_action( 'admin_post_hco_renew', function () {
	$user = wp_get_current_user();
	if ( ! $user->exists() || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'hco_renew' ) ) {
		wp_safe_redirect( home_url( '/renew/' ) );
		exit;
	}

	$method = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : 'free';
	$url    = ( 'free' !== $method && ! empty( HCO_RENEW_LINKS[ $method ] ) ) ? HCO_RENEW_LINKS[ $method ] : '';

	// Администраторам и гомеопатам даты не нужны; для них кнопки просто ведут дальше.
	if ( ! user_can( $user, 'manage_options' ) && ! in_array( 'homeopath', (array) $user->roles, true ) ) {
		hco_renew_extend( $user->ID );
	}

	if ( $url ) {
		wp_redirect( $url ); // внешняя страница оплаты
		exit;
	}

	$post = get_posts( array(
		'post_type'      => 'discursion',
		'author'         => $user->ID,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'suppress_filters' => true,
	) );
	wp_safe_redirect( $post ? get_permalink( $post[0] ) : home_url( '/' ) );
	exit;
} );

add_shortcode( 'hco_renew', function () {
	if ( ! is_user_logged_in() ) {
		return '<div dir="rtl" style="text-align:right;"><p>כדי להמשיך, יש להיכנס לאזור האישי.</p><p><a class="button" href="' . esc_url( wp_login_url( home_url( '/renew/' ) ) ) . '">כניסה לאזור האישי</a></p></div>';
	}

	$user_id = get_current_user_id();
	$past    = class_exists( 'Lt\Users' ) && \Lt\Users::is_past( $user_id );

	$form = function ( $method, $label, $enabled = true ) {
		$html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0 0 .75em;">';
		$html .= '<input type="hidden" name="action" value="hco_renew">';
		$html .= '<input type="hidden" name="method" value="' . esc_attr( $method ) . '">';
		$html .= wp_nonce_field( 'hco_renew', '_wpnonce', true, false );
		$html .= '<button type="submit" class="button"' . ( $enabled ? '' : ' disabled style="opacity:.5;cursor:not-allowed;"' ) . '>' . esc_html( $label ) . '</button>';
		$html .= '</form>';
		return $html;
	};

	$out  = '<div dir="rtl" style="text-align:right;">';
	$out .= '<h2>' . ( $past ? 'הגישה לאזור האישי הסתיימה' : 'הארכת גישה' ) . '</h2>';
	$out .= '<p>אפשר להמשיך להשתמש באזור האישי עוד שבוע, ללא תשלום.</p>';
	$out .= $form( 'free', 'להמשיך בחינם לשבוע נוסף' );

	$out .= '<h3 style="margin-top:2em;">תרומה</h3>';
	$out .= '<p>האתר פועל בזכות תרומות מרצון. אם ההתכתבות עזרה לכם, אפשר לתרום: 72 ₪ מומלצים, וכל סכום מתקבל בברכה. התרומה אינה תנאי להמשך הגישה ואינה מוכרת לצורכי מס.</p>';
	$out .= $form( 'paypal', 'תרומה ב-PayPal', ! empty( HCO_RENEW_LINKS['paypal'] ) );
	$out .= $form( 'bit', 'תרומה ב-Bit', ! empty( HCO_RENEW_LINKS['bit'] ) );
	$out .= $form( 'paybox', 'תרומה ב-PayBox', ! empty( HCO_RENEW_LINKS['paybox'] ) );
	$out .= '</div>';

	return $out;
} );

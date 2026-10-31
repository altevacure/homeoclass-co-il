<?php
/**
 * Plugin Name: LT — ответы по почте только при действующем доступе
 * Description: comment-mail-pro принимает ответ по письму, глядя лишь на ключ из него,
 *              и статус подписки не проверяет. Поэтому человек с закрытым доступом мог
 *              отвечать на любое старое письмо. Здесь такие ответы отклоняются, а отправителю
 *              уходит объяснение, где продлить доступ.
 * Version: 1.0
 */

defined( 'ABSPATH' ) || exit;

class LT_Rve_Guard {

	/** Метка, которую comment-mail-pro кладёт в запрос при ответе по почте. */
	const RVE_FIELD = 'comment_mail_rve_key';
	const SUB_FIELD = 'comment_mail_rve_sub_key';

	/** Слаг страницы продления доступа (создана в lt-config.php). */
	const RENEW_SLUG = 'renew';

	public static function init() {
		add_filter( 'preprocess_comment', array( __CLASS__, 'block_expired' ), 1 );
	}

	public static function block_expired( $commentdata ) {
		// Обычный комментарий с сайта: там доступ проверяется переадресацией, не наше дело.
		if ( empty( $_POST[ self::RVE_FIELD ] ) ) {
			return $commentdata;
		}

		$user = self::author( $commentdata );
		if ( ! $user || ! self::expired( $user ) ) {
			return $commentdata;
		}

		self::explain( $user );

		wp_die(
			esc_html( 'הגישה הסתיימה ולכן התשובה לא התקבלה. שלחנו לכם הודעה מה לעשות.' ),
			esc_html( 'הגישה סגורה' ),
			array( 'response' => 403 )
		);
	}

	/** Отправителя ищем по подписке из письма, а если её нет — по обратному адресу. */
	private static function author( $commentdata ) {
		global $wpdb;

		$sub_key = isset( $_POST[ self::SUB_FIELD ] ) ? trim( (string) wp_unslash( $_POST[ self::SUB_FIELD ] ) ) : '';

		if ( '' !== $sub_key ) {
			$user_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}comment_mail_subs WHERE `key` = %s",
				$sub_key
			) );
			if ( $user_id ) {
				$user = get_user_by( 'id', $user_id );
				if ( $user ) {
					return $user;
				}
			}
		}

		$email = isset( $commentdata['comment_author_email'] ) ? $commentdata['comment_author_email'] : '';

		return $email ? get_user_by( 'email', $email ) : null;
	}

	private static function expired( $user ) {
		if ( ! class_exists( 'Lt\Users' ) ) {
			return false;
		}
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return false;
		}

		return \Lt\Users::is_past( $user->ID );
	}

	private static function is_homeopath( $user ) {
		return in_array( 'homeopath', (array) $user->roles, true );
	}

	/**
	 * Объясняем, почему ответ не дошёл. Не чаще раза в час на человека:
	 * иначе переписка с закрытым доступом превратится в поток автоответов.
	 */
	private static function explain( $user ) {
		$guard = 'lt_rve_notice_' . $user->ID;
		if ( get_transient( $guard ) ) {
			return;
		}
		set_transient( $guard, 1, HOUR_IN_SECONDS );

		$name = $user->display_name ? $user->display_name : $user->user_login;

		$renew = get_page_by_path( self::RENEW_SLUG );
		$url   = $renew ? get_permalink( $renew ) : home_url( '/' );

		$what = "ניתן להאריך את הגישה כאן:
" . $url
			. "

לאחר התשלום הגישה נפתחת אוטומטית ותוכלו לכתוב שוב.";

		$body = "שלום, {$name}

"
			. "התשובה שלכם לא נכנסה לייעוץ: תקופת הגישה הסתיימה, והתכתבות במייל אינה זמינה כרגע.

"
			. $what . "

"
			. "------
הודעה אוטומטית מהאתר homeoclass.co.il";

		wp_mail( $user->user_email, 'התשובה לא נשלחה: הגישה סגורה', $body );
	}
}

LT_Rve_Guard::init();

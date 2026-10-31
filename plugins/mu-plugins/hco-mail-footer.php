<?php
/**
 * Plugin Name: Письма Comment Mail без служебного подвала
 * Description: Comment Mail дописывает к каждому письму блоки «Manage Subscriptions», «Contact Info»
 *              и «Our Mailing Address» (ссылки управления подпиской, адрес для жалоб, почтовый адрес).
 *              Клиенту и гомеопату они не нужны: убираем перед отправкой. Шаблон плагина не трогаем,
 *              поэтому обновления плагина и темы правку не стирают. Ответ по почте не затрагивается:
 *              метка ~rve# лежит выше подвала. Чтобы вернуть блоки, удалить этот файл.
 * Version: 1.0
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'wp_mail', function ( $args ) {
	$marker = '<hr style="margin-top:10em;" />';
	$message = isset( $args['message'] ) ? (string) $args['message'] : '';
	$pos     = strpos( $message, $marker );
	if ( false === $pos ) {
		return $args;
	}

	$head = substr( $message, 0, $pos );
	$tail = substr( $message, $pos + strlen( $marker ) );

	// Подвал Comment Mail состоит из абзацев с серым цветом; закрывающие теги страницы остаются.
	$tail = preg_replace( '~\s*<p style="color:#888888;">.*?</p>~s', '', $tail );

	$args['message'] = $head . $tail;
	return $args;
}, 20 );

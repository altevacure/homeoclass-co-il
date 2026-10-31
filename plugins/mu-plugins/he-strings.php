<?php
/**
 * Plugin Name: Переводы на иврит — комментарии кабинета
 * Description: В теме Kadence нет языкового пакета he_IL, поэтому строки блока комментариев
 *              (а в кабинете консультации это и есть переписка) остаются английскими. То же с
 *              письмами Comment Mail (домен comment-mail).
 *              Здесь подменяются только они. Когда появится полный перевод, файл можно удалить.
 * Version: 1.0
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'gettext', function ( $translated, $text, $domain ) {
	static $map = array(
		'One Comment'                          => 'תגובה אחת',
		'Comment'                              => 'תגובה',
		'Comment *'                            => 'תגובה *',
		'Post Comment'                         => 'שליחה',
		'Reply'                                => 'מענה',
		'Leave a Reply'                        => 'כתיבת תגובה',
		'Log out?'                             => 'להתנתק?',
		'Edit your profile'                    => 'עריכת הפרופיל',
		'says:'                                => 'כתב:',
		'Your comment is awaiting moderation.' => 'התגובה ממתינה לאישור.',
	);
	if ( in_array( $domain, array( 'kadence', 'default' ), true ) && isset( $map[ $text ] ) ) {
		return $map[ $text ];
	}
	return $translated;
}, 10, 3 );

add_filter( 'ngettext', function ( $translated, $single, $plural, $number, $domain ) {
	if ( 'kadence' === $domain && '%1$s Comment' === $single ) {
		return '%1$s תגובות';
	}
	return $translated;
}, 10, 5 );

add_filter( 'gettext', function ( $translated, $text, $domain ) {
	static $map = array(
		'Jump to Thread'                                   => 'מעבר לשיחה',
		'Continue Reading'                                 => 'המשך קריאה',
		'Reply to'                                         => 'מענה ל',
		'Reply'                                            => 'מענה',
		'— or simply reply to this email'                  => '— או פשוט השיבו למייל הזה',
		'Please Note:'                                     => 'שימו לב:',
		'Your reply will be posted publicly and immediately.' => 'התשובה שלכם תופיע מיד באזור האישי.',
	);
	if ( 'comment-mail' === $domain && isset( $map[ $text ] ) ) {
		return $map[ $text ];
	}
	return $translated;
}, 10, 3 );

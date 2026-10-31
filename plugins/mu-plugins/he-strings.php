<?php
/**
 * Plugin Name: Переводы на иврит — комментарии кабинета
 * Description: В теме Kadence нет языкового пакета he_IL, поэтому строки блока комментариев
 *              (а в кабинете консультации это и есть переписка) остаются английскими.
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

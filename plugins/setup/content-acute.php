<?php
/**
 * Раздел «מצבים אקוטים»: родительская страница acute (вход в раздел, content/acute/index.html)
 * и статьи из content/acute/*.html, плюс пункт меню Main.
 * Решение Иегуды 01.11.2026: статьи можно ставить на сайт опубликованными, вычитка носителем после.
 * wp eval-file content-acute.php <каталог content/acute>
 * Идемпотентно: страницы ищутся по слагу, пункт меню по странице.
 */
$dir = rtrim( $args[0], '/' );

function hco_read( $file ) {
	return preg_replace( '/^<!--.*?-->\s*/s', '', file_get_contents( $file ) );
}

function hco_upsert_page( $slug, $title, $content, $parent = 0, $order = 0 ) {
	$found = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_parent' => $parent, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'numberposts' => 1 ) );
	$arr   = array( 'post_type' => 'page', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content, 'post_parent' => $parent, 'menu_order' => $order, 'post_status' => 'publish' );
	if ( $found ) {
		$arr['ID'] = $found[0]->ID;
		return wp_update_post( wp_slash( $arr ) );
	}
	return wp_insert_post( wp_slash( $arr ) );
}

$parent = hco_upsert_page( 'acute', 'מצבים אקוטים', hco_read( "$dir/index.html" ) );

$articles = array(
	'runny-nose'      => 'נזלת: איך להבחין בין הרמדיס',
	'sore-throat'     => 'כאב גרון: איך להבחין בין הרמדיס',
	'cough'           => 'שיעול: איך להבחין בין הרמדיס',
	'fever'           => 'חום: איך להבחין בין הרמדיס',
	'flu'             => 'שפעת: איך להבחין בין הרמדיס',
	'earache'         => 'כאב אוזן: איך להבחין בין הרמדיס',
	'bruises'         => 'מכות וחבלות: איך להבחין בין הרמדיס',
	'cuts'            => 'חתכים ופצעים: איך להבחין בין הרמדיס',
	'burns'           => 'כוויות: איך להבחין בין הרמדיס',
	'bites'           => 'עקיצות ונשיכות: איך להבחין בין הרמדיס',
	'sunstroke'       => 'מכת שמש ומכת חום: איך להבחין בין הרמדיס',
	'fright'          => 'בהלה: איך להבחין בין הרמדיס',
	'teething'        => 'בקיעת שיניים: איך להבחין בין הרמדיס',
	'colic'           => 'גזים וקוליק אצל תינוקות: איך להבחין בין הרמדיס',
	'fever-children'  => 'חום אצל ילדים: איך להבחין בין הרמדיס',
	'diarrhea'        => 'שלשול: איך להבחין בין הרמדיס',
	'nausea-vomiting' => 'בחילות והקאות: איך להבחין בין הרמדיס',
	'motion-sickness' => 'מחלת תנועה: איך להבחין בין הרמדיס',
	'food-poisoning'  => 'הרעלת מזון: איך להבחין בין הרמדיס',
);
$order = 0;
foreach ( $articles as $slug => $title ) {
	$id = hco_upsert_page( $slug, $title, hco_read( "$dir/$slug.html" ), $parent, ++$order );
	echo "$slug=$id ", get_post_status( $id ), "\n";
}
echo "parent=$parent ", get_post_status( $parent ), ' ', get_permalink( $parent ), "\n";

// Пункт меню сразу после «ללמוד».
$menu  = wp_get_nav_menu_object( 'Main' );
$items = wp_get_nav_menu_items( $menu->term_id );
$have  = false;
$pos   = 0;
foreach ( $items as $it ) {
	if ( (int) $it->object_id === (int) $parent && 'page' === $it->object ) {
		$have = true;
	}
	if ( 'learn' === get_post_field( 'post_name', $it->object_id ) ) {
		$pos = $it->menu_order;
	}
}
if ( ! $have ) {
	foreach ( $items as $it ) {
		if ( $it->menu_order > $pos ) {
			wp_update_post( array( 'ID' => $it->ID, 'menu_order' => $it->menu_order + 1 ) );
		}
	}
	wp_update_nav_menu_item( $menu->term_id, 0, array(
		'menu-item-object-id' => $parent,
		'menu-item-object'    => 'page',
		'menu-item-type'      => 'post_type',
		'menu-item-status'    => 'publish',
		'menu-item-position'  => $pos + 1,
	) );
	echo "menu: added at ", $pos + 1, "\n";
}

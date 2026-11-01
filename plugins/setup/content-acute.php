<?php
/**
 * Раздел «מצבים אקוטים»: родительская страница acute (вход в раздел, content/acute/index.html)
 * и статьи из content/acute/*.html, плюс пункт меню Main.
 * Решение Иегуды 01.11.2026: статьи можно ставить на сайт опубликованными, вычитка носителем после.
 * Заголовки начинаются с ключевого запроса «הומאופתיה ל…» (подсказки Google, 01.11.2026).
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
	'runny-nose'      => 'הומאופתיה לנזלת ולצינון: איך לבחור רמדי',
	'sore-throat'     => 'הומאופתיה לכאב גרון: איך לבחור רמדי',
	'cough'           => 'הומאופתיה לשיעול: איך לבחור רמדי',
	'fever'           => 'הומאופתיה לחום: איך לבחור רמדי',
	'flu'             => 'הומאופתיה לשפעת: איך לבחור רמדי',
	'earache'         => 'הומאופתיה לכאב אוזן ולדלקת אוזניים: איך לבחור רמדי',
	'bruises'         => 'הומאופתיה למכות ולחבלות: איך לבחור רמדי',
	'cuts'            => 'הומאופתיה לחתכים ולפצעים: איך לבחור רמדי',
	'burns'           => 'הומאופתיה לכוויות: איך לבחור רמדי',
	'bites'           => 'הומאופתיה לעקיצות ולנשיכות: איך לבחור רמדי',
	'sunstroke'       => 'הומאופתיה למכת שמש ומכת חום: איך לבחור רמדי',
	'fright'          => 'הומאופתיה לבהלה ולחרדה שאחריה: איך לבחור רמדי',
	'teething'        => 'הומאופתיה לבקיעת שיניים אצל תינוקות: איך לבחור רמדי',
	'colic'           => 'הומאופתיה לגזים ולקוליק אצל תינוקות: איך לבחור רמדי',
	'fever-children'  => 'הומאופתיה לחום אצל ילדים: איך לבחור רמדי',
	'diarrhea'        => 'הומאופתיה לשלשול: איך לבחור רמדי',
	'nausea-vomiting' => 'הומאופתיה לבחילות ולהקאות: איך לבחור רמדי',
	'motion-sickness' => 'הומאופתיה למחלת ים ולבחילות בנסיעה: איך לבחור רמדי',
	'food-poisoning'  => 'הומאופתיה להרעלת מזון: איך לבחור רמדי',
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

<?php
/**
 * Раздел «מהי הומאופתיה»: родительская страница what-is-homeopathy (content/basics/index.html)
 * и статьи из content/basics/*.html, плюс пункт меню Main сразу после «ללמוד» (перед «מצבים אקוטים»).
 * Опубликованы сразу, вычитка носителем после (решение Иегуды 01.11.2026).
 * wp eval-file content-basics.php <каталог content/basics>
 * Идемпотентно: страницы ищутся по слагу, пункт меню по странице.
 */
$dir = rtrim( $args[0], '/' );

function hco_read( $file ) {
	return preg_replace( '/^<!--.*?-->\s*/s', '', file_get_contents( $file ) );
}

function hco_upsert_page( $slug, $title, $content, $parent = 0, $order = 0 ) {
	// 'any' на этом сайте не находит черновики по слагу, статусы перечислены явно.
	$found = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_parent' => $parent, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'numberposts' => 1 ) );
	$arr   = array( 'post_type' => 'page', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content, 'post_parent' => $parent, 'menu_order' => $order, 'post_status' => 'publish' );
	if ( $found ) {
		$arr['ID'] = $found[0]->ID;
		return wp_update_post( wp_slash( $arr ) );
	}
	return wp_insert_post( wp_slash( $arr ) );
}

$parent = hco_upsert_page( 'what-is-homeopathy', 'מהי הומאופתיה', hco_read( "$dir/index.html" ) );

$articles = array(
	'law-of-similars'       => 'חוק הדומה: העיקרון שעליו בנויה ההומאופתיה',
	'vital-force'           => 'כוח החיים בהומאופתיה',
	'potencies'             => 'פוטנציות בהומאופתיה: דילול וניעור',
	'individual-approach'   => 'הומאופתיה קלאסית: גישה אישית לכל אדם',
	'hering-law'            => 'חוק הרינג: איך יודעים שהגוף מתאושש',
	'conventional-medicine' => 'הומאופתיה ורפואה קונבנציונלית: מה ההבדל',
	'judaism'               => 'הומאופתיה ומקורות היהדות: דוגמאות מהתנ״ך',
);
$order = 0;
foreach ( $articles as $slug => $title ) {
	$id = hco_upsert_page( $slug, $title, hco_read( "$dir/$slug.html" ), $parent, ++$order );
	echo "$slug=$id ", get_post_status( $id ), "\n";
}
echo "parent=$parent ", get_post_status( $parent ), ' ', get_permalink( $parent ), "\n";

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
	echo 'menu: added at ', $pos + 1, "\n";
}

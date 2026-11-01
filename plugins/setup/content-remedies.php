<?php
/**
 * Раздел «רמדיס»: родительская страница remedies (content/remedies/index.html),
 * страницы средств из content/remedies/*.html (по мере появления), пункт меню Main «רמדיס» после «מצבים אקוטים».
 * Опубликовано сразу, вычитка носителем после (решение Иегуды 01.11.2026).
 * wp eval-file content-remedies.php <каталог content/remedies>
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

$parent = hco_upsert_page( 'remedies', 'רמדיס הומאופתיים: מה זה רמדי ואיך משתמשים בו', hco_read( "$dir/index.html" ) );

// Страницы средств аптечки: слаг => заголовок.
$articles = array(
	'arnica' => 'Arnica (ארניקה): רמדי הומאופתי למכות, לחבלות ולהלם',
	'aconitum' => 'Aconitum (אקוניטום): רמדי הומאופתי לבהלה ולחום פתאומי',
	'apis' => 'Apis (אפיס): רמדי הומאופתי לעקיצת דבורה ולנפיחות',
	'arsenicum-album' => 'Arsenicum album (ארסניקום אלבום): רמדי הומאופתי להרעלת מזון, לשלשול ולחולשה',
	'belladonna' => 'Belladonna (בלדונה): רמדי הומאופתי לחום גבוה, לכאב גרון ולמכת שמש',
	'bryonia' => 'Bryonia (בריוניה): רמדי הומאופתי לשיעול יבש ולשפעת עם כאבים',
	'calendula' => 'Calendula (קלנדולה): רמדי הומאופתי לפצעים ולחתכים',
	'cantharis' => 'Cantharis (קנתריס): רמדי הומאופתי לכוויות ולצריבה בשתן',
	'carbo-vegetabilis' => 'Carbo vegetabilis (קרבו וג׳): רמדי הומאופתי לנפיחות בבטן ולחולשה',
	'chamomilla' => 'Chamomilla (קמומילה): רמדי הומאופתי לבקיעת שיניים, לקוליק ולכאב אוזן',
	'china' => 'China (צ׳ינה): רמדי הומאופתי לחולשה אחרי שלשול ואיבוד נוזלים',
	'cocculus' => 'Cocculus (קוקולוס): רמדי הומאופתי למחלת ים ולעייפות מחוסר שינה',
	'drosera' => 'Drosera (דרוזרה): רמדי הומאופתי לשיעול בהתקפים',
	'ferrum-phosphoricum' => 'Ferrum phosphoricum (פרום פוספוריקום): רמדי הומאופתי לשלב הראשון של חום ודלקת',
	'gelsemium' => 'Gelsemium (ג׳לסמיום): רמדי הומאופתי לשפעת, לחולשה ולפחד במה',
	'hepar-sulphuris' => 'Hepar sulphuris (הפר סולפור): רמדי הומאופתי לדלקת אוזניים, למורסה ולשיעול נובח',
	'hypericum' => 'Hypericum (היפריקום): רמדי הומאופתי לפגיעה בעצבים, באצבעות ובעצם הזנב',
	'ignatia' => 'Ignatia (איגנציה): רמדי הומאופתי לאבל, לאכזבה ולבהלה',
	'ipecacuanha' => 'Ipecacuanha (איפקה): רמדי הומאופתי לבחילות ולהקאות',
	'ledum' => 'Ledum (לדום): רמדי הומאופתי לעקיצות יתושים ולדקירות',
	'nux-vomica' => 'Nux vomica (נוקס וומיקה): רמדי הומאופתי לבחילה, להאנג-אובר ולעצבנות',
	'pulsatilla' => 'Pulsatilla (פולסטילה): רמדי הומאופתי לנזלת, לשיעול ולילדים בכייניים',
	'rhus-toxicodendron' => 'Rhus toxicodendron (רוס טוקס): רמדי הומאופתי לנוקשות, לנקעים ולשפעת עם כאבים',
	'ruta' => 'Ruta (רוטה): רמדי הומאופתי לגידים, לנקעים ולעייפות עיניים',
);
$order    = 0;
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
	if ( 'acute' === get_post_field( 'post_name', $it->object_id ) ) {
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
		'menu-item-title'     => 'רמדיס',
		'menu-item-status'    => 'publish',
		'menu-item-position'  => $pos + 1,
	) );
	echo 'menu: added at ', $pos + 1, "\n";
}

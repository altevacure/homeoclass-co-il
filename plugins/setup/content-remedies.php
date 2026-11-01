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
	'symphytum' => 'Symphytum (סימפיטום): רמדי הומאופתי למכה בעין ולעצמות אחרי שבר',
	'urtica-urens' => 'Urtica urens (אורטיקה): רמדי הומאופתי לכוויות קלות, לגירוד ולסרפדת',
	'allium-cepa' => 'Allium cepa (בצל): רמדי הומאופתי לנזלת מימית ולאלרגיה עונתית',
	'spongia' => 'Spongia (ספונגיה): רמדי הומאופתי לשיעול נובח ולצרידות',
	'bellis-perennis' => 'Bellis perennis (בליס): רמדי הומאופתי למכה עמוקה ולחבלה אחרי ניתוח',
	'staphysagria' => 'Staphysagria (סטפיסגריה): רמדי הומאופתי לחתך נקי, אחרי ניתוח ולעלבון',
	'antimonium-tartaricum' => 'Antimonium tartaricum (אנטימוניום טרטריקום): רמדי הומאופתי לשיעול עם קרקור בחזה',
	'veratrum-album' => 'Veratrum album (ורטרום אלבום): רמדי הומאופתי להקאות ולשלשול עם חולשה וזיעה קרה',
	'aloe' => 'Aloe (אלוה): רמדי הומאופתי לשלשול דחוף ולטחורים',
	'podophyllum' => 'Podophyllum (פודופילום): רמדי הומאופתי לשלשול מימי ושוטף',
	'colocynthis' => 'Colocynthis (קולוצינתיס): רמדי הומאופתי לכאבי בטן, לקוליק ולכאב שמתקפל',
	'magnesia-phosphorica' => 'Magnesia phosphorica (מגנזיה פוספוריקה): רמדי הומאופתי לעוויתות, לכאבי מחזור ולקוליק',
	'phosphorus' => 'Phosphorus (פוספורוס): רמדי הומאופתי לשיעול, לדימומים ולבחילה עם צמא למים קרים',
	'lycopodium' => 'Lycopodium (ליקופודיום): רמדי הומאופתי לגזים, לנפיחות בבטן ולכאב גרון בצד ימין',
	'sulphur' => 'Sulphur (סולפור): רמדי הומאופתי לגירוד, לשלשול של בוקר ולתחושת חום',
	'lachesis' => 'Lachesis (לכזיס): רמדי הומאופתי לכאב גרון בצד שמאל ולגלי חום',
	'mercurius' => 'Mercurius (מרקוריוס): רמדי הומאופתי לדלקת גרון, לריח רע מהפה ולהזעת לילה',
	'causticum' => 'Causticum (קאוסטיקום): רמדי הומאופתי לכוויות ישנות, לצרידות ולחולשה בשרירים',
	'kali-bichromicum' => 'Kali bichromicum (קלי ביכרומיקום): רמדי הומאופתי לסינוסיטיס ולנזלת סמיכה ודביקה',
	'opium' => 'Opium (אופיום): רמדי הומאופתי להלם ולבהלה שאחריהם קפאון',
	'euphrasia' => 'Euphrasia (אופרזיה): רמדי הומאופתי לעיניים דומעות ושורפות ולדלקת עיניים',
	'phytolacca' => 'Phytolacca (פיטולקה): רמדי הומאופתי לדלקת גרון ולכאב שיורה לאוזן',
	'eupatorium-perfoliatum' => 'Eupatorium perfoliatum (אופטוריום): רמדי הומאופתי לשפעת עם כאבים בעצמות',
	'glonoinum' => 'Glonoinum (גלונואין): רמדי הומאופתי למכת שמש ולכאב ראש פועם',
	'natrum-carbonicum' => 'Natrum carbonicum (נתרום קרבוניקום): רמדי הומאופתי לרגישות לשמש ולקיבה חלשה',
	'natrum-sulphuricum' => 'Natrum sulphuricum (נתרום סולפוריקום): רמדי הומאופתי לתלונות שאחרי מכה בראש',
	'calcarea-phosphorica' => 'Calcarea phosphorica (קלקריה פוספוריקה): רמדי הומאופתי לבקיעת שיניים מאוחרת ולכאבי גדילה',
	'kreosotum' => 'Kreosotum (קראוזוטום): רמדי הומאופתי לשיניים שמתקלקלות ולחניכיים כואבות',
	'antimonium-crudum' => 'Antimonium crudum (אנטימוניום קרודום): רמדי הומאופתי לבחילה אחרי אכילת יתר וללשון לבנה',
	'tabacum' => 'Tabacum (טבקום): רמדי הומאופתי למחלת ים עם חיוורון וזיעה קרה',
	'petroleum' => 'Petroleum (פטרוליום): רמדי הומאופתי למחלת נסיעה שאוכל מקל עליה ולעור סדוק',
	'borax' => 'Borax (בורקס): רמדי הומאופתי לפחד מתנועה כלפי מטה ולפטרת בפה אצל תינוקות',
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

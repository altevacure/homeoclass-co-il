<?php
/**
 * Каркас сайта: роль, рубрика Workbook, страницы-заглушки, меню.
 * Запуск на сервере: wp eval-file skeleton.php. Повторный запуск ничего не дублирует.
 * Заголовки на иврите ждут вычитки носителем.
 */

if ( ! get_role( 'student' ) ) {
	add_role( 'student', 'תלמיד', array( 'read' => true ) );
}

if ( ! term_exists( 'workbook', 'category' ) ) {
	wp_insert_term( 'Workbook', 'category', array( 'slug' => 'workbook' ) );
}

$pages = array(
	'home'     => 'בית',
	'oprosniki'=> 'שאלונים',
	'children' => 'ילדים',
	'pets'     => 'חיות מחמד',
	'how'      => 'איך זה עובד',
	'learn'    => 'ללמוד',
	'about'    => 'אודות',
	'contact'  => 'צור קשר',
);

$ids = array();
foreach ( $pages as $slug => $title ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) {
		$ids[ $slug ] = $existing->ID;
		continue;
	}
	$ids[ $slug ] = wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => '<p>בבנייה</p>',
	) );
}

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $ids['home'] );

// Меню в порядке из ТЗ. Главная в меню не нужна, она открывается по названию сайта.
$menu = wp_get_nav_menu_object( 'Main' );
$menu_id = $menu ? $menu->term_id : wp_create_nav_menu( 'Main' );
$have = wp_get_nav_menu_items( $menu_id );
if ( empty( $have ) ) {
	foreach ( array( 'oprosniki', 'children', 'pets', 'how', 'learn', 'about', 'contact' ) as $slug ) {
		wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-object-id' => $ids[ $slug ],
			'menu-item-object'    => 'page',
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
		) );
	}
}

echo 'pages: ' . count( $ids ) . ', menu: ' . $menu_id . "\n";

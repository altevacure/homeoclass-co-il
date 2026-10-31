<?php
/**
 * Пункт меню Main для входа и выхода (плагин Login Logout Menu). Идемпотентно.
 * Гость видит текст до « | », вошедший после него; ссылка #loginpress-loginlogout# подменяется плагином.
 * Прежний пункт hco-personal (подмена адреса по роли) удаляется.
 * wp eval-file menu-personal.php
 */
$menu = wp_get_nav_menu_object( 'Main' );
if ( ! $menu ) {
	echo "Menu Main not found\n";
	return;
}

$title = 'כניסה לאזור האישי | יציאה מהאזור האישי';
$found = 0;

foreach ( wp_get_nav_menu_items( $menu->term_id ) as $it ) {
	if ( in_array( 'hco-personal', (array) $it->classes, true ) ) {
		wp_delete_post( $it->ID, true );
		echo "removed old item {$it->ID}\n";
	} elseif ( '#loginpress-loginlogout#' === $it->url ) {
		$found = $it->ID;
	}
}

$id = wp_update_nav_menu_item( $menu->term_id, $found, array(
	'menu-item-title'  => $title,
	'menu-item-url'    => '#loginpress-loginlogout#',
	'menu-item-status' => 'publish',
	'menu-item-type'   => 'custom',
) );
echo is_wp_error( $id ) ? 'ERR ' . $id->get_error_message() . "\n" : "item id=$id\n";

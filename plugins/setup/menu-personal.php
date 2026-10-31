<?php
/**
 * Добавляет в меню Main пункт «אזור אישי» (последним). Идемпотентно.
 * Адрес подменяется на лету плагином hco-personal-menu.php.
 * wp eval-file menu-personal.php
 */
$menu = wp_get_nav_menu_object( 'Main' );
if ( ! $menu ) {
	echo "Menu Main not found\n";
	return;
}

foreach ( wp_get_nav_menu_items( $menu->term_id ) as $it ) {
	if ( in_array( 'hco-personal', (array) $it->classes, true ) ) {
		echo "already exists id={$it->ID}\n";
		return;
	}
}

$id = wp_update_nav_menu_item( $menu->term_id, 0, array(
	'menu-item-title'   => 'אזור אישי',
	'menu-item-url'     => '#hco-personal',
	'menu-item-classes' => 'hco-personal',
	'menu-item-status'  => 'publish',
	'menu-item-type'    => 'custom',
) );
echo is_wp_error( $id ) ? 'ERR ' . $id->get_error_message() . "\n" : "added id=$id\n";

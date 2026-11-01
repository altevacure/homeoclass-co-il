<?php
/**
 * Страница /renew/ («הארכת גישה»): содержимое — шорткод [hco_renew] из mu-плагина hco-renew.php.
 * wp eval-file renew-page.php
 */
$page = get_page_by_path( 'renew' );
if ( ! $page ) {
	echo "page renew not found\n";
	return;
}
wp_update_post( array( 'ID' => $page->ID, 'post_content' => '[hco_renew]' ) );
echo "renew page {$page->ID} updated\n";

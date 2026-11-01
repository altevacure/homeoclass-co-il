<?php
/**
 * Страница-вход «שאלונים» (слаг oprosniki, id 6): список опросников.
 * wp eval-file content-questionnaires.php <каталог content/questionnaires>
 */
$dir  = rtrim( $args[0], '/' );
$page = get_page_by_path( 'oprosniki' );
$id   = wp_update_post( array(
	'ID'           => $page->ID,
	'post_content' => file_get_contents( "$dir/index.html" ),
	'post_status'  => 'publish',
) );
echo "page=$id\n";

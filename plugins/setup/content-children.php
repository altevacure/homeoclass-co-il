<?php
/**
 * Страница-вход «ילדים» (слаг children, id 7): опросники для детей и младенцев.
 * wp eval-file content-children.php <каталог content/children>
 */
$dir  = rtrim( $args[0], '/' );
$page = get_page_by_path( 'children' );
echo 'page=' . wp_update_post( array( 'ID' => $page->ID, 'post_content' => file_get_contents( "$dir/index.html" ), 'post_status' => 'publish' ) ) . "\n";

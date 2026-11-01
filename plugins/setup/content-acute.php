<?php
/**
 * Раздел «מצבים חריפים»: родительская страница acute и статьи из content/acute/*.html.
 * Пока всё черновиком (draft): публикует Иегуда после вычитки.
 * wp eval-file content-acute.php <каталог content/acute>
 * Идемпотентно: страницы ищутся по слагу.
 */
$dir = rtrim( $args[0], '/' );

function hco_upsert_page( $slug, $title, $content, $parent = 0 ) {
	$found = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_parent' => $parent, 'post_status' => 'any', 'numberposts' => 1 ) );
	$arr   = array( 'post_type' => 'page', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content, 'post_parent' => $parent );
	if ( $found ) {
		$arr['ID'] = $found[0]->ID;
		return wp_update_post( wp_slash( $arr ) );
	}
	$arr['post_status'] = 'draft';
	return wp_insert_post( wp_slash( $arr ) );
}

$parent = hco_upsert_page( 'acute', 'מצבים חריפים', '<p>בבנייה</p>' );

$articles = array(
	'cough' => 'שיעול חריף: איך להבחין בין הרמדיס',
);
foreach ( $articles as $slug => $title ) {
	$html = preg_replace( '/^<!--.*?-->\s*/s', '', file_get_contents( "$dir/$slug.html" ) );
	$id   = hco_upsert_page( $slug, $title, $html, $parent );
	echo "$slug=$id ", get_post_status( $id ), "\n";
}
echo "parent=$parent ", get_post_status( $parent ), "\n";

<?php
/**
 * Создаёт формы CF7 и страницы для набора опросников из forms.json. Идемпотентно.
 * wp eval-file forms-batch.php <каталог с forms.json, <slug>.form.html, <slug>.mail.txt>
 */
$dir   = rtrim( $args[0], '/' );
$forms = json_decode( file_get_contents( "$dir/forms.json" ), true );

foreach ( $forms as $f ) {
	$slug  = $f['slug'];
	$title = $f['title'];
	$form  = file_get_contents( "$dir/$slug.form.html" );
	$mail  = file_get_contents( "$dir/$slug.mail.txt" );
	if ( ! $form || ! $mail ) {
		echo "skip $slug\n";
		continue;
	}

	$found = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'title' => $title, 'numberposts' => 1, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ) ) );
	$id    = $found ? $found[0]->ID : wp_insert_post( array( 'post_type' => 'wpcf7_contact_form', 'post_status' => 'publish', 'post_title' => $title ) );

	update_post_meta( $id, '_form', wp_slash( $form ) );
	update_post_meta( $id, '_locale', 'he_IL' );
	update_post_meta( $id, '_mail', wp_slash( array(
		'active'             => true,
		'subject'            => '[your-name] - ' . $title,
		'sender'             => 'הומאופתיה לעם <ask@homeoclass.co.il>',
		'recipient'          => '[_site_admin_email]',
		'body'               => $mail,
		'additional_headers' => "Reply-To: [your-email]\nBcc: homeoclass3@gmail.com",
		'attachments'        => '',
		'use_html'           => true,
		'exclude_blank'      => false,
	) ) );
	update_post_meta( $id, '_mail_2', wp_slash( array( 'active' => false ) ) );

	$page = get_page_by_path( $slug );
	$arr  = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => '[contact-form-7 id="' . $id . '" title="' . $title . '"]',
	);
	if ( $page ) {
		$arr['ID'] = $page->ID;
		$pid       = wp_update_post( $arr );
	} else {
		$pid = wp_insert_post( $arr );
	}
	echo "$slug form=$id page=$pid\n";
}

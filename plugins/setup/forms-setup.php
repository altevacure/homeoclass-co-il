<?php
/**
 * Создаёт форму CF7 «שאלון ראשוני כללי» и страницу с ней. Идемпотентно.
 * wp eval-file forms-setup.php <каталог с general-primary.form.html и .mail.txt>
 */
$dir   = rtrim( $args[0], '/' );
$form  = file_get_contents( "$dir/general-primary.form.html" );
$mail  = file_get_contents( "$dir/general-primary.mail.txt" );
$title = 'שאלון ראשוני כללי';

$existing = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'title' => $title, 'numberposts' => 1, 'post_status' => 'any' ) );
$id       = $existing ? $existing[0]->ID : wp_insert_post( array( 'post_type' => 'wpcf7_contact_form', 'post_status' => 'publish', 'post_title' => $title ) );

update_post_meta( $id, '_form', wp_slash( $form ) );
update_post_meta( $id, '_locale', 'he_IL' );
update_post_meta( $id, '_mail', wp_slash( array(
	'active'             => true,
	'subject'            => '[your-name] - שאלון ראשוני כללי',
	'sender'             => 'הומאופתיה לעם <ask@homeoclass.co.il>',
	'recipient'          => '[_site_admin_email]',
	'body'               => $mail,
	'additional_headers' => "Reply-To: [your-email]\nBcc: homeoclass3@gmail.com",
	'attachments'        => '',
	'use_html'           => true,
	'exclude_blank'      => false,
) ) );
update_post_meta( $id, '_mail_2', wp_slash( array( 'active' => false ) ) );

$page    = get_page_by_path( 'general-primary' );
$content = '[contact-form-7 id="' . $id . '" title="' . $title . '"]';
$arr     = array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'general-primary', 'post_title' => $title, 'post_content' => $content );
if ( $page ) {
	$arr['ID'] = $page->ID;
	$pid       = wp_update_post( $arr );
} else {
	$pid = wp_insert_post( $arr );
}

echo "form=$id page=$pid\n";

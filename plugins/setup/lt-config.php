<?php
/**
 * Настройки плагина Lt для homeoclass.co.il. Идемпотентно: можно запускать повторно.
 * wp eval-file lt-config.php
 */
$page = function ( $slug, $title ) {
	$p = get_page_by_path( $slug );
	if ( $p ) {
		return (int) $p->ID;
	}
	return (int) wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => '<p>בבנייה</p>',
	) );
};

$renew = $page( 'renew', 'הארכת גישה' );
$forms = (int) ( get_page_by_path( 'questionnaires' ) ? get_page_by_path( 'questionnaires' )->ID : 0 );
if ( ! $forms ) {
	$q = get_posts( array( 'post_type' => 'page', 'title' => 'שאלונים', 'numberposts' => 1 ) );
	$forms = $q ? (int) $q[0]->ID : 0;
}
$home_url = home_url( '/' );
$u = function ( $id ) { return $id ? '/?page_id=' . $id : '/'; };

update_option( 'lt_plugin_options_timing', array(
	'registration_timevalue' => '7',
	'registration_timespan'  => 'PD',
	'approve_timevalue'      => '7',
	'approve_timespan'       => 'PD',
) );

update_option( 'lt_plugin_options_redirects', array(
	'login_active'     => '',
	'login_future'     => $u( $forms ),
	'postview_future'  => $u( $forms ),
	'login_past'       => $u( $renew ),
	'postview_past'    => $u( $renew ),
	'login_noinfo'     => $u( $forms ),
	'postview_noinfo'  => $u( $forms ),
) );

update_option( 'lt_plugin_options_template', array(
	'postcontent'    => '[userrole role="homeopath" status="active"]*** פתוח להומאופת[/userrole]' . "\r\n" .
		'[userrole role="administrator"]*** פתוח למנהל[/userrole]' . "\r\n" .
		'<p><a href="' . $home_url . '?page_id=' . $forms . '">שאלונים</a></p>',
	'subject_start'  => 'הגישה נפתחה',
	'template_start' => '',
	'subject_soon'   => 'הגישה שלכם מסתיימת בקרוב',
	'template_soon'  => "שלום, {user_nicename}\r\n\r\nהגישה לאזור האישי תסתיים בעוד כיממה. להארכה:\r\n" . $home_url . "renew/\r\n\r\n------\r\nהודעה אוטומטית מהאתר homeoclass.co.il",
	'endsoon_timevalue' => '1',
	'endsoon_timespan'  => 'PD',
	'subject_end'    => 'הגישה הסתיימה',
	'template_end'   => "שלום, {user_nicename}\r\n\r\nהגישה לאזור האישי הסתיימה. להארכה:\r\n" . $home_url . "renew/\r\n\r\n------\r\nהודעה אוטומטית מהאתר homeoclass.co.il",
) );

echo "renew=$renew forms=$forms\n";

<?php
/**
 * Ответы клиентов с почты (RVE) через Make.com для homeoclass.co.il.
 *
 * Токен, команда и зона берутся из переменных окружения HCO_TOK, HCO_TEAM, HCO_ZONE
 * (их передаёт оболочка с настроек tutor, в файл и в вывод они не попадают).
 *
 * Шаги:
 * 1. Ищет в Make почтовый хук с точным именем «Comment Mail Pro - homeoclass.co.il - Mailhook»
 *    и создаёт его, если нет. Нужно заранее: иначе плагин возьмёт «первый попавшийся» хук
 *    (хук tutor) и привяжет сайт к чужому сценарию.
 * 2. Записывает rve_makecom_* и включает ответы по почте в comment_mail_options.
 * 3. Вызывает RveMakecom::setupWebhook(): плагин создаёт сценарий «Comment Mail Pro - <хост>»
 *    (Custom mailhook -> HTTP POST на сайт) и включает его.
 *
 * wp eval-file rve-makecom-setup.php
 */
$tok  = getenv( 'HCO_TOK' );
$team = getenv( 'HCO_TEAM' );
$zone = getenv( 'HCO_ZONE' ) ?: 'eu2';
if ( ! $tok || ! $team ) {
	echo "Нет HCO_TOK/HCO_TEAM\n";
	return;
}

$host      = parse_url( home_url(), PHP_URL_HOST );
$hook_name = 'Comment Mail Pro - ' . $host . ' - Mailhook';
$base      = 'https://' . $zone . '.make.com/api/v2';
$hdr       = array( 'Content-Type' => 'application/json', 'Authorization' => 'Token ' . $tok );

$r    = wp_remote_get( $base . '/hooks?teamId=' . urlencode( $team ), array( 'timeout' => 20, 'headers' => $hdr ) );
$code = (int) wp_remote_retrieve_response_code( $r );
$body = json_decode( wp_remote_retrieve_body( $r ) );
if ( $code >= 400 || ! $code ) {
	echo "Make API list hooks: HTTP $code\n";
	return;
}

$hook = null;
foreach ( (array) ( $body->hooks ?? array() ) as $h ) {
	if ( ( $h->typeName ?? '' ) === 'gateway-mailhook' && ( $h->name ?? '' ) === $hook_name ) {
		$hook = $h;
		break;
	}
}

if ( ! $hook ) {
	$r    = wp_remote_post( $base . '/hooks', array(
		'timeout' => 20,
		'headers' => $hdr,
		'body'    => json_encode( array( 'teamId' => (int) $team, 'typeName' => 'gateway-mailhook', 'name' => $hook_name ) ),
	) );
	$code = (int) wp_remote_retrieve_response_code( $r );
	$body = json_decode( wp_remote_retrieve_body( $r ) );
	if ( $code >= 400 || ! $code ) {
		echo "Make API create hook: HTTP $code\n";
		return;
	}
	$hook = $body->hook ?? $body;
	echo "hook created\n";
} else {
	echo "hook exists\n";
}

$plugin = \WebSharks\CommentMail\Pro\plugin();

$plugin->options['replies_via_email_enable']  = '1';
$plugin->options['replies_via_email_handler'] = 'makecom';
$plugin->options['rve_makecom_api_token']     = $tok;
$plugin->options['rve_makecom_team_id']       = (string) $team;
$plugin->options['rve_makecom_zone']          = $zone;
$plugin->options['rve_makecom_hook_id']       = (string) $hook->id;
$plugin->options['rve_makecom_mailhook_email'] = (string) $hook->url;
$plugin->options['rve_makecom_scenario_id']   = '';

\WebSharks\CommentMail\Pro\RveMakecom::setupWebhook();

update_option( 'comment_mail_options', $plugin->options );

echo 'hook_id=' . $plugin->options['rve_makecom_hook_id'] . ' scenario_id=' . $plugin->options['rve_makecom_scenario_id'] . "\n";
echo 'mailhook_domain=' . substr( strrchr( $plugin->options['rve_makecom_mailhook_email'], '@' ), 1 ) . "\n";

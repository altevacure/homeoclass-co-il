<?php
/**
 * Проверка сценария Make для RVE без вывода секретов: активен ли, куда шлёт, сколько в очереди хука.
 * wp eval-file rve-makecom-check.php
 */
$o    = get_option( 'comment_mail_options' );
$tok  = $o['rve_makecom_api_token'];
$base = 'https://' . ( $o['rve_makecom_zone'] ?: 'eu2' ) . '.make.com/api/v2';
$hdr  = array( 'Authorization' => 'Token ' . $tok );

$r = json_decode( wp_remote_retrieve_body( wp_remote_get( $base . '/scenarios/' . $o['rve_makecom_scenario_id'], array( 'headers' => $hdr, 'timeout' => 20 ) ) ) );
$s = $r->scenario ?? null;
if ( $s ) {
	echo 'scenario name=' . $s->name . ' active=' . var_export( $s->isActive, true ) . ' paused=' . var_export( $s->isPaused ?? null, true ) . "\n";
}

$bp = json_decode( wp_remote_retrieve_body( wp_remote_get( $base . '/scenarios/' . $o['rve_makecom_scenario_id'] . '/blueprint', array( 'headers' => $hdr, 'timeout' => 20 ) ) ) );
$flow = $bp->response->blueprint->flow ?? array();
foreach ( $flow as $m ) {
	if ( ( $m->module ?? '' ) === 'http:MakeRequest' ) {
		$u = $m->mapper->url ?? '';
		$p = parse_url( $u );
		echo 'http target host=' . ( $p['host'] ?? '?' ) . ' path=' . ( $p['path'] ?? '' ) . ' has_key=' . ( strpos( $u, 'rve_makecom' ) !== false ? 'yes' : 'no' ) . "\n";
	}
}

$h = json_decode( wp_remote_retrieve_body( wp_remote_get( $base . '/hooks/' . $o['rve_makecom_hook_id'], array( 'headers' => $hdr, 'timeout' => 20 ) ) ) );
$hk = $h->hook ?? null;
if ( $hk ) {
	echo 'hook name=' . $hk->name . ' enabled=' . var_export( $hk->enabled ?? null, true ) . ' scenarioId=' . ( $hk->scenarioId ?? '-' ) . "\n";
}
echo 'reply_to_enabled=' . $o['replies_via_email_enable'] . ' handler=' . $o['replies_via_email_handler'] . "\n";

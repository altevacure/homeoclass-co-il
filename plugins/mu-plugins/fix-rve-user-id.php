<?php
/**
 * Fix user_ID for comments created by Comment Mail Pro via RVE (Reply Via Email).
 * Comment Mail posts comments via wp_remote_post without session,
 * resulting in user_id=0. This filter resolves the WP user.
 *
 * homeoclass.co.il: first by the subscription key from the email (comment_mail_rve_sub_key),
 * i.e. the person the notification was sent to. Only then by the sender's address.
 * So a reply to a notification meant for curshen@homeoclass.co.il is posted as
 * that user, even when it is sent from another address (e.g. a Gmail forward).
 */
add_filter('preprocess_comment', function($commentdata) {
    if (empty($_REQUEST['comment_mail_rve_key'])) {
        return $commentdata;
    }
    if (!empty($commentdata['user_ID'])) {
        return $commentdata;
    }

    $user = null;

    $sub_key = isset($_REQUEST['comment_mail_rve_sub_key']) ? trim((string) wp_unslash($_REQUEST['comment_mail_rve_sub_key'])) : '';
    if ('' !== $sub_key) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, post_id FROM {$wpdb->prefix}comment_mail_subs WHERE `key` = %s",
            $sub_key
        ));
        if ($row && (int) $row->user_id && (int) $row->post_id === (int) $commentdata['comment_post_ID']) {
            $user = get_user_by('id', (int) $row->user_id);
        }
    }

    if (!$user) {
        $user = get_user_by('email', $commentdata['comment_author_email']);
    }

    if ($user) {
        $commentdata['user_ID'] = $user->ID;
        $commentdata['comment_author'] = $user->display_name;
        $commentdata['comment_author_email'] = $user->user_email;
    }
    return $commentdata;
});

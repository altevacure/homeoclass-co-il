<?php
/**
 * Disable comment flood check for Comment Mail Pro RVE (Reply Via Email) requests.
 *
 * wp_remote_post to wp-comments-post.php carries comment_mail_rve_key in the body.
 * WordPress flood protection (15 sec throttle) rejects rapid successive replies,
 * causing silent 429 losses. This filter bypasses the check for RVE only.
 */
add_filter('wp_is_comment_flood', function($is_flood, $ip, $email, $date, $wp_error) {
    if (!empty($_REQUEST['comment_mail_rve_key'])) {
        return false;
    }
    return $is_flood;
}, 20, 5);

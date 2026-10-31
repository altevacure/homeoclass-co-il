<?php
/**
 * Fix Comment Mail Pro RVE (Reply Via Email) rejections on wp-comments-post.php.
 *
 * Every RVE loopback POST died with 403 "must be logged in", and behind that
 * with WP Armour's honeypot ("Spamming or your Javascript is disabled").
 *
 * 1) comment_registration=1: Comment Mail's own bypass is broken twice —
 *    it guards on $_REQUEST['comment_mail_rve'], a field maybePostComment()
 *    never sends, and its pre_option_comment_registration callback returns
 *    the false sentinel before ever checking the RVE key.
 * 2) WP Armour rejects any comment POST lacking its JS-filled honeypot fields;
 *    a server-to-server RVE post can never carry them.
 *
 * Both gates are cleared ONLY for requests carrying a valid RVE key
 * (HMAC, hash_equals) — presence of the field alone must not open
 * anonymous commenting on a private site.
 *
 * For the honeypot we satisfy its own check rather than unhooking it:
 * we inject the two fields it expects (dynamic field + empty `alt_s`) so
 * wpa_check_is_spam() passes naturally. This is independent of WP Armour's
 * callback name and hook priority, so it survives plugin updates that would
 * silently break a remove_filter().
 */
if (!defined('ABSPATH')) {
    exit;
}

function lt_is_valid_rve_request()
{
    static $valid = null;
    if ($valid !== null) {
        return $valid;
    }
    if (empty($_POST['comment_mail_rve_key'])) {
        return $valid = false;
    }
    if (!class_exists('\WebSharks\CommentMail\Pro\UtilsRve')) {
        return $valid = false;
    }
    $key = (string) wp_unslash($_POST['comment_mail_rve_key']);
    return $valid = hash_equals(\WebSharks\CommentMail\Pro\UtilsRve::key(), $key);
}

// Gate 1: lift the registration requirement for valid RVE posts.
add_filter('pre_option_comment_registration', function ($value) {
    return lt_is_valid_rve_request() ? 0 : $value;
}, 1);

// Gate 2: satisfy WP Armour's honeypot for valid RVE posts.
add_action('init', function () {
    if (!lt_is_valid_rve_request()) {
        return;
    }
    if (($field = get_option('wpa_field_name'))) {
        $_POST[$field] = '1'; // Dynamic field — must be set.
    }
    $_POST['alt_s'] = '';     // Honeypot trap — must be set and empty.
}, 99);

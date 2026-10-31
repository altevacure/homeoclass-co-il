<?php
/**
 * Настройка Comment Mail Pro для консультаций (аналог tutor). Идемпотентно.
 * Ответы по почте (Make) не включаются: replies_via_email_enable = 0.
 * wp eval-file comment-mail-config.php
 */
$o = get_option( 'comment_mail_options' );
if ( ! is_array( $o ) ) {
	$o = array();
}

$o['enable']                          = 1;
$o['new_subs_enable']                 = 1;
$o['queue_processing_enable']         = 1;
$o['enabled_post_types']              = 'discursion';
$o['comment_form_sub_template_enable'] = 1;
$o['comment_form_sub_scripts_enable'] = 1;
$o['sso_enable']                      = 0;
$o['auto_subscribe_enable']           = 0;
$o['auto_confirm_force_enable']       = 1;
$o['all_wp_users_confirm_email']      = 1;
$o['from_name']                       = 'הומאופתיה לעם';
$o['from_email']                      = 'altevacure@gmail.com';
$o['reply_to_email']                  = '';
$o['smtp_enable']                     = 0;
$o['replies_via_email_enable']        = 0;
$o['list_server_enable']              = 0;
$o['can_spam_postmaster']             = 'ask@homeoclass.co.il';
$o['can_spam_mailing_address']        = 'הומאופתיה לעם<br />homeoclass.co.il';
$o['email_footer_powered_by_enable']  = 0;
$o['site_footer_powered_by_enable']   = 0;
$o['template_type']                   = 's';

$p = 'template__type_s__email__';
$o[ $p . 'snippet__header_tag___php' ] = '<header dir="rtl"><h3 style="margin:0;">[blog_name_clip] <span style="color:#888888;"><a href="[home_url]" style="color:#888888;">[current_host_path]</a></span></h3></header><hr style="margin-bottom:2em;" />';
$o[ $p . 'snippet__footer_tag___php' ] = '<footer dir="rtl"></footer>';
$o[ $p . 'sub_confirmation__snippet__subject___php' ] = '';
$o[ $p . 'sub_confirmation__snippet__message___php' ] = '<div dir="rtl"><h5 style="margin-top:0;font-weight:normal;">[sub_fname], <a href="[sub_confirm_url]"><strong>לחצו כאן לאישור</strong></a> הרשמה להודעות.</h5><p><a href="[sub_post_comments_url]">[sub_post_title_clip]</a></p></div>';
$o[ $p . 'comment_notification__snippet__subject___php' ] = 'הודעה חדשה: [sub_post_title_clip]';
$o[ $p . 'comment_notification__snippet__message_heading___php' ] = '<div dir="rtl"><h2 style="margin-top:0;">הודעה חדשה ב<em><a href="[sub_post_comments_url]">[sub_post_title_clip]</a></em></h2><p style="font-weight:bold;">כדי להשיב, היכנסו לאתר ופתחו את הקישור.</p></div>';
$o[ $p . 'comment_notification__snippet__message_in_response_to___php' ] = '<div dir="rtl"><p style="font-weight:bold;">[if comment_reply_to_own_comment]מענה ל<a href="[comment_parent_url]">הודעה שלכם</a>:[else]מענה ל<a href="[comment_parent_url]">הודעה</a>[if comment_parent_author] של [comment_parent_author][endif]:[endif]</p><div style="margin:1em 0;font-style:italic;">[comment_parent_content]</div></div>';
$o[ $p . 'comment_notification__snippet__message_reply_from___php' ] = '<div dir="rtl" style="margin:1em 0;font-size:130%;">[comment_content]</div>';
$o[ $p . 'comment_notification__snippet__message_comment_from___php' ] = '<div dir="rtl" style="margin:1em 0;font-size:130%;">[comment_content]</div>';

update_option( 'comment_mail_options', $o );
echo "comment_mail_options updated, keys=" . count( $o ) . "\n";

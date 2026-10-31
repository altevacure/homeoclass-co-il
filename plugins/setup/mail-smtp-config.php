<?php
/**
 * Предварительная настройка WP Mail SMTP: режим Gmail, отправитель и имя.
 * Ключи Google (client_id, client_secret) вводятся вручную в админке, здесь их нет.
 * wp eval-file mail-smtp-config.php
 */
$options = \WPMailSMTP\Options::init();
$options->set(
	array(
		'mail' => array(
			'from_email'       => 'altevacure@gmail.com',
			'from_name'        => 'הומאופתיה לעם',
			'mailer'           => 'gmail',
			'return_path'      => false,
			'from_email_force' => true,
			'from_name_force'  => true,
		),
	),
	false,
	true
);
echo "mailer=" . $options->get( 'mail', 'mailer' ) . " from=" . $options->get( 'mail', 'from_email' ) . "\n";

<?php
/**
 * Регистрация пользователя прямо из опросника Contact Form 7.
 *
 * Раньше путь до анкеты был в три шага: форма регистрации (?p=97, Advanced Forms,
 * форма «Регистрация клиента» form_6074ef8e3c7ad) -> «Инструкции для пациентов»
 * (?p=73) -> собственно опросник, который до этого был закрыт шорткодом
 * [loggedin]. Теперь опросник открыт всем, а поля регистрации живут внутри него.
 *
 * Что делает файл:
 * 1. Добавляет в CF7 тег [password] / [password*] — в ядре его нет.
 * 2. Проверяет поля регистрации (свободный e-mail, непустой пароль) — только
 *    для незалогиненных, потому что для остальных этих полей на странице нет.
 * 3. На wpcf7_before_send_mail с приоритетом 5 (то есть ДО Lt\ContactForm,
 *    который висит на 10) создаёт пользователя и логинит его. Дальше Lt видит
 *    уже залогиненного клиента и заводит консультацию на него, а не на админа,
 *    и сам же редиректит в личный кабинет — как при обычной регистрации.
 * 4. Отдаёт значения флажков строкой, иначе Lt не подставит их в кабинет.
 * 5. Ставит в заголовок консультации имя клиента вместо его логина-почты.
 * 6. Не даёт contact-form-cfdb7 сохранить пароль в открытом виде.
 *
 * Форма считается «формой с регистрацией», если в ней есть поле reg-password,
 * так что тот же блок можно просто скопировать в остальные опросники.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Имена полей CF7, из которых собирается учётная запись.
 *
 * @return array
 */
function lt_cf7_reg_fields() {
	return array(
		'name'     => 'your-name',
		// Фамилия отдельным полем с 22.10.2026 (раньше «Имя и фамилия» было одним).
		'surname'  => 'your-surname',
		'email'    => 'your-email',
		'password' => 'reg-password',
		'source'   => 'reg-source',
	);
}

/**
 * Есть ли в форме блок регистрации.
 *
 * @param WPCF7_ContactForm $contact_form
 *
 * @return bool
 */
function lt_cf7_form_has_registration( $contact_form ) {
	if ( ! $contact_form instanceof WPCF7_ContactForm ) {
		return false;
	}

	$fields = lt_cf7_reg_fields();

	return (bool) $contact_form->scan_form_tags( array( 'name' => $fields['password'] ) );
}


/* -------------------------------------------------------------------------
 * 1. Тег [password]
 * ---------------------------------------------------------------------- */

add_action( 'wpcf7_init', 'lt_cf7_add_form_tag_password', 10, 0 );

function lt_cf7_add_form_tag_password() {
	wpcf7_add_form_tag(
		array( 'password', 'password*' ),
		'lt_cf7_password_form_tag_handler',
		array( 'name-attr' => true )
	);
}

/**
 * Разметка повторяет wpcf7_text_form_tag_handler(), меняется только type.
 *
 * @param WPCF7_FormTag $tag
 *
 * @return string
 */
function lt_cf7_password_form_tag_handler( $tag ) {
	if ( empty( $tag->name ) ) {
		return '';
	}

	$validation_error = wpcf7_get_validation_error( $tag->name );

	$class = wpcf7_form_controls_class( $tag->type, 'wpcf7-text' );

	if ( $validation_error ) {
		$class .= ' wpcf7-not-valid';
	}

	$atts = array();

	$atts['size']      = $tag->get_size_option( '40' );
	$atts['maxlength'] = $tag->get_maxlength_option( '400' );
	$atts['minlength'] = $tag->get_minlength_option();

	if (
		$atts['maxlength'] and $atts['minlength'] and
		$atts['maxlength'] < $atts['minlength']
	) {
		unset( $atts['maxlength'], $atts['minlength'] );
	}

	$atts['class']        = $tag->get_class_option( $class );
	$atts['id']           = $tag->get_id_option();
	$atts['tabindex']     = $tag->get_option( 'tabindex', 'signed_int', true );
	$atts['autocomplete'] = $tag->get_autocomplete_option();

	if ( $tag->is_required() ) {
		$atts['aria-required'] = 'true';
	}

	if ( $validation_error ) {
		$atts['aria-invalid']     = 'true';
		$atts['aria-describedby'] = wpcf7_get_validation_error_reference( $tag->name );
	} else {
		$atts['aria-invalid'] = 'false';
	}

	if ( $tag->has_option( 'placeholder' ) or $tag->has_option( 'watermark' ) ) {
		$atts['placeholder'] = (string) reset( $tag->values );
	}

	// Пароль никогда не возвращаем в поле обратно, даже при ошибке валидации.
	$atts['value'] = '';
	$atts['type']  = 'password';
	$atts['name']  = $tag->name;

	return sprintf(
		'<span class="wpcf7-form-control-wrap" data-name="%1$s"><input %2$s />%3$s</span>',
		esc_attr( $tag->name ),
		wpcf7_format_atts( $atts ),
		$validation_error
	);
}


/* -------------------------------------------------------------------------
 * 2. Валидация полей регистрации
 * ---------------------------------------------------------------------- */

add_filter( 'wpcf7_validate', 'lt_cf7_validate_registration', 20, 2 );

/**
 * @param WPCF7_Validation $result
 * @param WPCF7_FormTag[]  $tags
 *
 * @return WPCF7_Validation
 */
function lt_cf7_validate_registration( $result, $tags ) {
	if ( is_user_logged_in() ) {
		return $result;
	}

	$submission = WPCF7_Submission::get_instance();

	if ( ! $submission or ! lt_cf7_form_has_registration( $submission->get_contact_form() ) ) {
		return $result;
	}

	$fields = lt_cf7_reg_fields();
	$data   = $submission->get_posted_data();

	$by_name = array();

	foreach ( $tags as $tag ) {
		if ( ! empty( $tag->name ) ) {
			$by_name[ $tag->name ] = $tag;
		}
	}

	$email = isset( $data[ $fields['email'] ] ) ? trim( (string) $data[ $fields['email'] ] ) : '';

	if ( $email and isset( $by_name[ $fields['email'] ] ) ) {
		if ( email_exists( $email ) or username_exists( $email ) ) {
			$result->invalidate(
				$by_name[ $fields['email'] ],
				'כתובת הדוא"ל הזו כבר רשומה. היכנסו לאתר והשאלון יתמלא בשמכם.'
			);
		}
	}

	$password = isset( $data[ $fields['password'] ] ) ? (string) $data[ $fields['password'] ] : '';

	if ( isset( $by_name[ $fields['password'] ] ) ) {
		if ( '' === trim( $password ) ) {
			$result->invalidate(
				$by_name[ $fields['password'] ],
				'בחרו סיסמה, איתה תיכנסו לאזור האישי.'
			);
		} elseif ( mb_strlen( $password ) < 6 ) {
			$result->invalidate(
				$by_name[ $fields['password'] ],
				'הסיסמה צריכה להכיל לפחות שישה תווים.'
			);
		}
	}

	return $result;
}


/* -------------------------------------------------------------------------
 * 3. Создание пользователя до того, как Lt заведёт консультацию
 * ---------------------------------------------------------------------- */

add_action( 'wpcf7_before_send_mail', 'lt_cf7_register_from_submission', 5, 3 );

/**
 * @param WPCF7_ContactForm $contact_form
 * @param bool              $abort
 * @param WPCF7_Submission  $submission
 */
function lt_cf7_register_from_submission( $contact_form, &$abort, $submission ) {
	if ( is_user_logged_in() or ! lt_cf7_form_has_registration( $contact_form ) ) {
		return;
	}

	$fields = lt_cf7_reg_fields();
	$data   = $submission->get_posted_data();

	$email    = isset( $data[ $fields['email'] ] ) ? sanitize_email( trim( (string) $data[ $fields['email'] ] ) ) : '';
	$password = isset( $data[ $fields['password'] ] ) ? (string) $data[ $fields['password'] ] : '';
	$name     = isset( $data[ $fields['name'] ] ) ? sanitize_text_field( trim( (string) $data[ $fields['name'] ] ) ) : '';

	if ( ! is_email( $email ) or '' === trim( $password ) ) {
		$abort = true;
		$submission->set_response( 'האזור האישי לא נפתח: בדקו שם, דוא"ל וסיסמה.' );

		return;
	}

	if ( email_exists( $email ) or username_exists( $email ) ) {
		$abort = true;
		$submission->set_response( 'כתובת הדוא"ל הזו כבר רשומה. היכנסו לאתר ושלחו את השאלון שוב.' );

		return;
	}

	$surname = isset( $data[ $fields['surname'] ] ) ? sanitize_text_field( trim( (string) $data[ $fields['surname'] ] ) ) : '';

	if ( '' !== $surname ) {
		// Имя и фамилия приходят отдельными полями.
		$first = $name;
		$last  = $surname;
		$name  = trim( $first . ' ' . $last );
	} else {
		// Формы без поля фамилии: «Иван Петров» в одном поле -> first_name + last_name.
		$parts = preg_split( '/\s+/u', $name, 2 );
		$first = isset( $parts[0] ) ? $parts[0] : '';
		$last  = isset( $parts[1] ) ? $parts[1] : '';
	}

	$user_id = wp_insert_user( array(
		'user_login'   => $email,
		'user_email'   => $email,
		'user_pass'    => $password,
		'first_name'   => $first,
		'last_name'    => $last,
		'display_name' => '' !== $name ? $name : $email,
		'role'         => 'subscriber',
	) );

	if ( is_wp_error( $user_id ) ) {
		$abort = true;
		$submission->set_response( 'האזור האישי לא נפתח: ' . $user_id->get_error_message() );

		return;
	}

	$user = get_user_by( 'id', $user_id );

	// Те же ACF-метаполя, что заполняет форма регистрации «Регистрация клиента».
	if ( function_exists( 'update_field' ) ) {
		$source = isset( $data[ $fields['source'] ] ) ? $data[ $fields['source'] ] : '';
		$source = is_array( $source ) ? (string) reset( $source ) : (string) $source;

		update_field( 'field_6074f101b3388', $email, 'user_' . $user_id ); // email
		update_field( 'field_6074ef63e3056', $first, 'user_' . $user_id ); // first_name
		update_field( 'field_6074ef6ae3057', $last, 'user_' . $user_id );  // second_name

		if ( '' !== $source ) {
			update_field( 'field_6074ef6fe3058', $source, 'user_' . $user_id ); // source
		}
	}

	// Логинимся ровно тем же способом, что и после формы регистрации:
	// Lt\Users::form_sign_in_user() висит на af/form/editing/user_created.
	if ( class_exists( '\Lt\Users' ) ) {
		\Lt\Users::form_sign_in_user( $user );
	} else {
		clean_user_cache( $user_id );
		wp_clear_auth_cookie();
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );
	}
}


/* -------------------------------------------------------------------------
 * 4. Значения флажков и переключателей - строкой, а не массивом
 * ---------------------------------------------------------------------- */

add_filter( 'wpcf7_posted_data_checkbox', 'lt_cf7_flatten_selectable_value', 20, 3 );
add_filter( 'wpcf7_posted_data_checkbox*', 'lt_cf7_flatten_selectable_value', 20, 3 );
add_filter( 'wpcf7_posted_data_radio', 'lt_cf7_flatten_selectable_value', 20, 3 );

/**
 * CF7 хранит значения checkbox и radio массивом. Lt\ContactForm при сборке
 * текста для личного кабинета идёт по posted_data и подставляет только то,
 * что прошло is_string(), поэтому такие теги оставались в комментарии
 * неразвёрнутыми - «Тел: +7… ([messenger])». В письме CF7 подставлял значения
 * сам, так что расхождение было видно только в кабинете.
 *
 * Склеиваем так же, как это делает почтовый шаблон CF7, - через запятую.
 * На валидацию это не влияет: правила SWV читают $_POST напрямую
 * (Contactable\SWV\Rule::get_default_input), а не отфильтрованные данные.
 *
 * @param array|string  $value
 * @param array|string  $value_orig
 * @param WPCF7_FormTag $form_tag
 *
 * @return string
 */
function lt_cf7_flatten_selectable_value( $value, $value_orig, $form_tag ) {
	if ( is_array( $value ) ) {
		return implode( ', ', $value );
	}

	return $value;
}


/* -------------------------------------------------------------------------
 * 5. Заголовок консультации: фамилия и имя, без даты
 * ---------------------------------------------------------------------- */

add_action( 'Lt\PostCreated', 'lt_cf7_rename_consultation', 20, 2 );

/**
 * Lt\ContactForm склеивает заголовок как «<название формы> <user_login>», а
 * user_login у клиентов — это e-mail, потому что так его заводит форма
 * регистрации. В списке консультаций это выглядело как «Опросник - кашель
 * asd@asd.com».
 *
 * Для homeoclass.co.il (решение Егуды 01.11.2026) заголовок - «Иванов Роман»: фамилия
 * и имя, без даты. Если фамилии нет, остаётся одно имя. Тёзки различаются слагом
 * (WP добавит номер).
 *
 * @param WP_User $user
 * @param int     $post_id
 */
function lt_cf7_rename_consultation( $user, $post_id ) {
	if ( ! $user instanceof WP_User or ! $post_id ) {
		return;
	}

	$name = trim( (string) $user->first_name );

	if ( '' === $name ) {
		// Старые учётные записи без first_name: первое слово отображаемого имени.
		$display = trim( (string) $user->display_name );
		$name    = $display !== $user->user_login ? (string) strtok( $display, ' ' ) : '';
	}

	if ( '' === $name or $name === $user->user_login ) {
		return;
	}

	$name = trim( (string) $user->last_name . ' ' . $name );

	$post = get_post( $post_id );

	if ( ! $post ) {
		return;
	}

	$suffix = ' ' . $user->user_login;

	// Правим только тот заголовок, который действительно собрал Lt.
	if ( $suffix !== substr( $post->post_title, - strlen( $suffix ) ) ) {
		return;
	}

	wp_update_post( array(
		'ID'         => $post_id,
		// В заголовке кабинета остаются фамилия и имя клиента. Название опросника
		// никуда не девается: его собирает CF7 в теме письма администратору.
		'post_title' => $name,
		// Пустой post_name заставляет WP пересобрать слаг из нового заголовка,
		// иначе в нём остаётся почта клиента.
		'post_name'  => '',
	) );
}


/* -------------------------------------------------------------------------
 * 6. Пароль не должен осесть в базе форм открытым текстом
 * ---------------------------------------------------------------------- */

add_filter( 'cfdb7_not_allowed_tags', 'lt_cf7_hide_password_from_cfdb7' );

function lt_cf7_hide_password_from_cfdb7( $not_allowed ) {
	$fields        = lt_cf7_reg_fields();
	$not_allowed[] = $fields['password'];

	return $not_allowed;
}

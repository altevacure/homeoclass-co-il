<?php
/**
 * Plugin Name: Приватные консультации
 * Description: Закрывает консультацию (discursion) для всех гомеопатов, кроме
 *              ведущего и тех, кто перечислен в мета-поле _lt_doctors.
 *              Доступ ведущего гомеопата - умолчание: запись без списка видна
 *              только ему, а не всем коллегам, как было до 19.09.2026.
 *              Клиент и администратор видят свою запись всегда.
 *              Отмечать гомеопатов галочками может только администратор, на экране
 *              редактирования консультации. При сохранении приватной записи лишние
 *              подписки на письма удаляются, их копия остаётся в _lt_doctors_unsubbed.
 *              Галочка «Закрыть для всех, кроме администратора» (мета _lt_locked)
 *              закрывает запись и от клиента, и от ведущего гомеопата, и от круглого
 *              стола; подписки на письма при этом удаляются у всех.
 *              Чтобы отключить, удалить этот файл.
 */

namespace LtPrivate;

const META       = '_lt_doctors';
const META_UNSUB = '_lt_doctors_unsubbed';
const META_LOCK  = '_lt_locked';
const PT         = 'discursion';

/** Запись закрыта целиком: видит только администратор. */
function is_locked( $post_id ) {
	return (bool) get_post_meta( $post_id, META_LOCK, true );
}

/**
 * Ведущий гомеопат - user 125, Иегуда Куршен (curshen@yandex.ru).
 * Он видит любую консультацию, даже если в записи не отмечен никто.
 * Номер переопределяется опцией lt_private_main_doctor, без правки кода.
 */
function main_doctor() {
	return (int) get_option( 'lt_private_main_doctor', 125 );
}

/**
 * Кому открыта запись. Пустая мета больше не значит «видят все»:
 * запись без списка открыта только ведущему гомеопату.
 * Его номер добавляется всегда, даже если в мете его нет.
 */
function doctors_of( $post_id ) {
	$doctors   = get_post_meta( $post_id, META, true );
	$doctors   = is_array( $doctors ) ? array_map( 'intval', $doctors ) : array();
	$doctors[] = main_doctor();

	return array_values( array_unique( array_filter( $doctors ) ) );
}

/** Пускаем администратора, самого клиента и гомеопатов из списка. */
function allowed( $post_id ) {
	if ( current_user_can( 'activate_plugins' ) ) {
		return true;
	}
	if ( is_locked( $post_id ) ) {
		return false;
	}
	$user = wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	$post = get_post( $post_id );
	if ( $post && (int) $post->post_author === (int) $user->ID ) {
		return true;
	}

	return in_array( (int) $user->ID, doctors_of( $post_id ), true );
}

/**
 * Список закрытых для текущего пользователя записей.
 * Кешируется на пользователя: wp_set_current_user() в скриптах меняет ответ.
 */
function forbidden_ids() {
	static $cache = array();
	$uid = (int) get_current_user_id();
	if ( isset( $cache[ $uid ] ) ) {
		return $cache[ $uid ];
	}

	global $wpdb;
	// Перебираем все консультации, а не только те, где мета проставлена:
	// запись без меты тоже закрыта, она открыта одному ведущему гомеопату.
	$posts = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
		PT
	) ) );

	// Один запрос на все записи и один на все меты: иначе allowed() сходит
	// в базу за каждой из полусотни консультаций, и так на каждой странице.
	if ( $posts ) {
		_prime_post_caches( $posts, false, true );
	}

	$ids = array();
	foreach ( $posts as $post_id ) {
		if ( ! allowed( $post_id ) ) {
			$ids[] = $post_id;
		}
	}
	$cache[ $uid ] = $ids;

	return $ids;
}

/** Администратор и гомеопат. Остальные видят только свои консультации. */
function is_staff() {
	if ( current_user_can( 'activate_plugins' ) ) {
		return true;
	}
	$user = wp_get_current_user();

	return $user && $user->exists() && in_array( 'homeopath', (array) $user->roles, true );
}

/**
 * Чужие консультации для клиента и для незалогиненного.
 * Родной фильтр lt делает то же самое, но только в режиме объектов,
 * поэтому при fields => ids номера чужих кабинетов утекали.
 */
function foreign_ids() {
	static $cache = array();
	$uid = (int) get_current_user_id();
	if ( isset( $cache[ $uid ] ) ) {
		return $cache[ $uid ];
	}
	if ( is_staff() ) {
		return $cache[ $uid ] = array();
	}

	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_author <> %d",
		PT,
		$uid
	) );

	return $cache[ $uid ] = array_map( 'intval', (array) $ids );
}

/** Запрос вообще про консультации? Чтобы не таскать длинный список по всему сайту. */
function query_touches_pt( $query ) {
	$pt = $query->get( 'post_type' );
	if ( empty( $pt ) ) {
		return (bool) $query->get( 's' );
	}
	$pt = (array) $pt;

	return in_array( PT, $pt, true ) || in_array( 'any', $pt, true );
}

/**
 * Главный заслон: срезаем закрытые записи до запроса.
 * Нужен потому, что при fields => ids ядро возвращает выборку раньше,
 * чем применяет posts_results, и оба фильтра оказываются слепыми.
 */
add_action( 'pre_get_posts', function ( $query ) {
	$ids = forbidden_ids();
	if ( query_touches_pt( $query ) ) {
		$ids = array_merge( $ids, foreign_ids() );
	}
	if ( ! $ids ) {
		return;
	}
	$excluded = (array) $query->get( 'post__not_in' );
	$query->set( 'post__not_in', array_values( array_unique( array_merge( $excluded, $ids ) ) ) );
}, 20 );

/** Приоритет 20, чтобы отработать после фильтров плагина Lt. */
add_filter( 'posts_results', function ( $posts ) {
	foreach ( $posts as $idx => $row ) {
		// При fields => ids выборка приходит массивом чисел, при fields => id=>parent
		// объектами без post_type. Поэтому тип записи выясняем отдельно.
		if ( is_object( $row ) && isset( $row->post_type ) ) {
			$post_id   = (int) $row->ID;
			$post_type = $row->post_type;
		} else {
			$post_id   = is_object( $row ) ? (int) ( isset( $row->ID ) ? $row->ID : 0 ) : (int) $row;
			$post_type = $post_id ? get_post_type( $post_id ) : '';
		}
		if ( PT === $post_type && ! allowed( $post_id ) ) {
			unset( $posts[ $idx ] );
		}
	}

	return array_values( $posts );
}, 20 );

add_filter( 'the_comments', function ( $comments ) {
	foreach ( $comments as $idx => $comment ) {
		if ( PT === get_post_type( $comment->comment_post_ID ) && ! allowed( $comment->comment_post_ID ) ) {
			unset( $comments[ $idx ] );
		}
	}

	return array_values( $comments );
}, 20 );

/** Страховка на случай прямой ссылки: отдаём 404 до редиректов плагина Lt. */
add_action( 'template_redirect', function () {
	if ( ! is_singular( PT ) ) {
		return;
	}
	$id = get_queried_object_id();
	if ( ! $id || allowed( $id ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}, 5 );

/* ------------------------- админка ------------------------- */

function homeopaths() {
	return get_users( array(
		'role'    => 'homeopath',
		'orderby' => 'display_name',
		'fields'  => array( 'ID', 'display_name', 'user_email' ),
	) );
}

add_action( 'add_meta_boxes', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_meta_box( 'lt_private_doctors', 'Доступ гомеопатов', __NAMESPACE__ . '\metabox', PT, 'side', 'high' );
} );

function metabox( $post ) {
	wp_nonce_field( 'lt_private_doctors', 'lt_private_doctors_nonce' );
	$current = get_post_meta( $post->ID, META, true );
	$current = is_array( $current ) ? array_map( 'intval', $current ) : array();

	printf(
		'<label style="display:block;margin:0 0 .8em;font-weight:600"><input type="checkbox" name="lt_locked" value="1"%s> Закрыть для всех, кроме администратора</label>',
		checked( is_locked( $post->ID ), true, false )
	);

	$main = main_doctor();
	echo '<p style="margin:0 0 .6em">Ничего не отмечено - запись видит только ведущий гомеопат. Отметьте тех, кому открыть её ещё.</p>';
	foreach ( homeopaths() as $u ) {
		$id = (int) $u->ID;
		if ( $id === $main ) {
			// Ведущему доступ не отключается, галочка показана для полноты картины.
			printf(
				'<label style="display:block;margin:.3em 0;color:#666"><input type="checkbox" checked disabled> %s <em>- всегда</em></label>',
				esc_html( $u->display_name )
			);
			continue;
		}
		printf(
			'<label style="display:block;margin:.3em 0"><input type="checkbox" name="lt_doctors[]" value="%d"%s> %s</label>',
			$id,
			checked( in_array( $id, $current, true ), true, false ),
			esc_html( $u->display_name . ' - ' . $u->user_email )
		);
	}
	echo '<p style="margin:.8em 0 0;color:#666">При сохранении приватной записи подписки остальных на письма удаляются. Обратно они не восстанавливаются.</p>';
}

add_action( 'save_post_' . PT, function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( empty( $_POST['lt_private_doctors_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( $_POST['lt_private_doctors_nonce'], 'lt_private_doctors' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$doctors   = isset( $_POST['lt_doctors'] ) ? array_map( 'intval', (array) $_POST['lt_doctors'] ) : array();
	$doctors[] = main_doctor();
	$doctors   = array_values( array_unique( array_filter( $doctors ) ) );

	// Список пишем всегда, даже когда сняты все галочки: пустой меты,
	// от которой запись открывалась бы коллегам, теперь не бывает.
	update_post_meta( $post_id, META, $doctors );
	if ( ! empty( $_POST['lt_locked'] ) ) {
		update_post_meta( $post_id, META_LOCK, 1 );
	} else {
		delete_post_meta( $post_id, META_LOCK );
	}
	prune_subs( $post_id, $doctors );
} );

/**
 * Новая консультация заводится закрытой на ведущего гомеопата.
 * Мета нужна не для доступа - он и так по умолчанию его, - а чтобы в админке
 * и в колонке «Доступ» было видно, кто запись читает.
 */
add_action( 'wp_insert_post', function ( $post_id, $post ) {
	if ( ! $post instanceof \WP_Post || PT !== $post->post_type ) {
		return;
	}
	if ( in_array( $post->post_status, array( 'auto-draft', 'inherit' ), true ) ) {
		return;
	}
	$current = get_post_meta( $post_id, META, true );
	if ( is_array( $current ) && $current ) {
		return;
	}
	update_post_meta( $post_id, META, array( main_doctor() ) );
}, 10, 2 );

/**
 * Удаляем подписки на письма у тех, кому запись закрыта.
 * Именно удаляем, а не приостанавливаем: Lt\Users::change_subscribe_status()
 * обновляет все строки пользователя разом и вернул бы статус subscribed
 * при следующем продлении его собственной подписки.
 */
function prune_subs( $post_id, array $doctors ) {
	global $wpdb;

	$table = $wpdb->prefix . 'comment_mail_subs';
	$post  = get_post( $post_id );
	$keep  = $doctors;
	if ( $post ) {
		$keep[] = (int) $post->post_author;
	}
	// Закрытую целиком запись не читает никто, и письма о ней не уходят никому.
	if ( is_locked( $post_id ) ) {
		$keep = array();
	}

	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d", $post_id ) );
	if ( ! $rows ) {
		return;
	}

	$removed = get_post_meta( $post_id, META_UNSUB, true );
	$removed = is_array( $removed ) ? $removed : array();

	foreach ( $rows as $row ) {
		if ( in_array( (int) $row->user_id, $keep, true ) ) {
			continue;
		}
		// Колонка первичного ключа называется ID, свойства объекта регистрозависимы.
		$row_id = isset( $row->ID ) ? (int) $row->ID : 0;
		if ( ! $row_id ) {
			continue;
		}
		$removed[] = (array) $row;
		$wpdb->delete( $table, array( 'ID' => $row_id ) );
	}

	if ( $removed ) {
		update_post_meta( $post_id, META_UNSUB, $removed );
	}
}

/** Колонка в списке консультаций, чтобы видно было, какие закрыты. */
add_filter( 'manage_' . PT . '_posts_columns', function ( $columns ) {
	$columns['lt_private'] = 'Доступ';

	return $columns;
} );

add_action( 'manage_' . PT . '_posts_custom_column', function ( $column, $post_id ) {
	if ( 'lt_private' !== $column ) {
		return;
	}
	if ( is_locked( $post_id ) ) {
		echo '<strong>закрыта: только администратор</strong>';
		return;
	}
	$names = array();
	foreach ( doctors_of( $post_id ) as $id ) {
		$u       = get_user_by( 'id', $id );
		$names[] = $u ? $u->display_name : (int) $id;
	}
	echo 'только ' . esc_html( implode( ', ', $names ) );
}, 10, 2 );

<?php
/**
 * Plugin Name: Рубрика Workbook только для подписчиков
 * Description: Закрывает записи рубрики workbook для всех, кроме администраторов
 *              и подписчиков (роль student). Все остальные записи сайта открыты.
 *              Когда подключится плагин Lt, срок доступа подписчика проверяется по paidfrom/paidto.
 *              Перекрыты все каналы: страницы, архивы, поиск, ленты, карта сайта и REST API.
 *              Остальным отдаётся 404. Чтобы отключить, удалить этот файл.
 */

const LT_WB_SLUG = 'workbook';

/**
 * Идентификатор рубрики. Ищем по слагу, чтобы правка пережила пересоздание рубрики.
 */
function lt_wb_term_id() {
	static $id = null;
	if ( null === $id ) {
		$term = get_category_by_slug( LT_WB_SLUG );
		$id   = $term ? (int) $term->term_id : 0;
	}
	return $id;
}

/**
 * Пускаем администратора и подписчика (роль student). Если плагин Lt уже подключён,
 * срок доступа подписчика должен быть действующим.
 */
function lt_wb_allowed() {
	if ( current_user_can( 'activate_plugins' ) ) {
		return true;
	}

	$user = wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	if ( ! in_array( 'student', (array) $user->roles, true ) ) {
		return false;
	}
	if ( class_exists( '\Lt\Users' ) && method_exists( '\Lt\Users', 'is_active' ) ) {
		return (bool) \Lt\Users::is_active( $user->ID );
	}

	return true;
}

/** Списки, архивы, поиск, ленты. */
add_action( 'pre_get_posts', function ( $query ) {
	if ( is_admin() || ! lt_wb_term_id() || lt_wb_allowed() ) {
		return;
	}
	$excluded   = (array) $query->get( 'category__not_in' );
	$excluded[] = lt_wb_term_id();
	$query->set( 'category__not_in', array_values( array_unique( array_filter( $excluded ) ) ) );
} );

/** Отдельная запись и архив самой рубрики. */
add_action( 'template_redirect', function () {
	if ( ! lt_wb_term_id() || lt_wb_allowed() ) {
		return;
	}

	$blocked = false;
	if ( is_singular( 'post' ) ) {
		$blocked = has_category( lt_wb_term_id(), get_queried_object_id() );
	} elseif ( is_category( lt_wb_term_id() ) ) {
		$blocked = true;
	}

	if ( ! $blocked ) {
		return;
	}

	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}, 5 );

/** REST: выборка записей. */
add_filter( 'rest_post_query', function ( $args ) {
	if ( ! lt_wb_term_id() || lt_wb_allowed() ) {
		return $args;
	}
	$excluded   = isset( $args['category__not_in'] ) ? (array) $args['category__not_in'] : array();
	$excluded[] = lt_wb_term_id();
	$args['category__not_in'] = array_values( array_unique( array_filter( $excluded ) ) );
	return $args;
} );

/** REST: запрос конкретной записи по идентификатору. */
add_filter( 'rest_prepare_post', function ( $response, $post ) {
	if ( ! lt_wb_term_id() || lt_wb_allowed() ) {
		return $response;
	}
	if ( ! has_category( lt_wb_term_id(), $post ) ) {
		return $response;
	}
	// Возвращать WP_Error отсюда нельзя: ядро ждёт объект ответа и падает с 500.
	// Поэтому отдаём пустой ответ с кодом 404.
	$response->set_data( array() );
	$response->set_status( 404 );

	return $response;
}, 10, 2 );

/** Карта сайта. */
add_filter( 'wp_sitemaps_posts_query_args', function ( $args ) {
	if ( ! lt_wb_term_id() || lt_wb_allowed() ) {
		return $args;
	}
	$excluded   = isset( $args['category__not_in'] ) ? (array) $args['category__not_in'] : array();
	$excluded[] = lt_wb_term_id();
	$args['category__not_in'] = array_values( array_unique( array_filter( $excluded ) ) );
	return $args;
} );

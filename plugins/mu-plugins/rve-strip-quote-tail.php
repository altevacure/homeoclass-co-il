<?php
/**
 * Plugin Name: RVE - срезать хвост цитаты
 * Description: Ответы, присланные по электронной почте, приходят в кабинет
 *              с хвостом: подписью почтового клиента («-- / Отправлено из
 *              мобильной Яндекс Почты») и цитатой прошлого письма (шапка
 *              «Кому: / Тема:», строка с датой и адресом, затем blockquote).
 *              Comment Mail Pro режет письмо по своему невидимому маркеру
 *              и снимает английскую строку «... wrote:»
 *              (UtilsRve::stripWroteByLine), русских вариантов у него нет.
 *
 *              Версия 2 шла по строкам снизу вверх и останавливалась на
 *              первой строке с живым текстом. Яндекс кладёт внутрь цитаты
 *              её собственную подпись, поэтому самой нижней строкой
 *              оказывалась «Отправлено из мобильной Яндекс Почты» - разбор
 *              обрывался на ней, и весь хвост оставался в кабинете
 *              (комментарий 1282 на tutor, 20.09.2026).
 *
 *              Версия 3 ищет начало цитаты сверху вниз и отрезает всё от
 *              него до конца, а подпись снимает отдельным проходом. Резать
 *              разрешено только если выше осталась живая строка и в
 *              отрезаемом куске есть явная примета письма: blockquote,
 *              адрес почты или две строки заголовков подряд.
 *
 *              Правка в mu-плагине, а не в Comment Mail, чтобы пережила
 *              обновление плагина - как disable-rve-flood-check.php
 *              и fix-rve-user-id.php рядом.
 *              Чтобы отключить, удалить этот файл.
 * Version: 3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Текст строки без разметки и без невидимых пробелов. Теги снимаем ДО
 * раскодирования: иначе адрес в &lt;скобках&gt; сам выглядит тегом.
 * Яндекс отбивает абзацы не обычным пробелом, а U+200A и подобными -
 * без их замены строка из одного такого знака считалась текстом
 * и обрывала разбор хвоста.
 */
function curshen_rve_plain( $html ) {

	$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
	$text = str_replace( "\xC2\xA0", ' ', $text );

	$normalized = preg_replace( '~[\x{00A0}\x{1680}\x{2000}-\x{200D}\x{202F}\x{205F}\x{2060}\x{3000}\x{FEFF}]~u', ' ', $text );

	if ( null !== $normalized ) {
		$text = $normalized;   // null значит битый UTF-8, тогда оставляем как есть
	}

	return trim( $text );
}

/** Строка без текста: только разметка вроде <blockquote>, <br>, &nbsp;. */
function curshen_rve_is_scaffold( $line ) {
	return '' === curshen_rve_plain( $line );
}

/** Разделитель, которым почтовики отбивают цитату: «----------------». */
function curshen_rve_is_separator( $line ) {
	return (bool) preg_match( '#^[-_=~*]{3,}$#', curshen_rve_plain( $line ) );
}

/** Строка «--», которой по почтовому обычаю отбивается подпись. */
function curshen_rve_is_signature_delim( $line ) {
	return (bool) preg_match( '~^(?:--|—|–)$~u', curshen_rve_plain( $line ) );
}

/** Сама подпись почтового клиента: «Отправлено из мобильной Яндекс Почты», «Sent from my iPhone». */
function curshen_rve_is_signature_line( $line ) {

	$text = curshen_rve_plain( $line );

	if ( '' === $text || mb_strlen( $text, 'UTF-8' ) > 120 ) {
		return false;
	}

	return (bool) preg_match(
		'~^(?:отправлено\s+(?:из|с|через|при\s+помощи)\b|получено\s+из\b|'
		. 'sent\s+(?:from|via|using)\b|get\s+outlook\s+for\b)~ui',
		$text
	);
}

/** Шапка цитаты: «пт, 18 сент. 2026 г., 14:45 Имя <адрес>:», «On ... wrote:» и подобные. */
function curshen_rve_is_attribution( $line ) {

	$text = curshen_rve_plain( $line );

	if ( '' === $text || mb_strlen( $text, 'UTF-8' ) > 300 ) {
		return false;   // шапка всегда короткая
	}
	if ( ':' !== mb_substr( $text, -1, 1, 'UTF-8' ) ) {
		return false;   // и всегда кончается двоеточием
	}

	// Кончается словами, которыми почтовики подписывают цитату.
	// «написал(а):» - скобка между словом и двоеточием, её надо пропустить.
	$ends_wrote = (bool) preg_match( '~\b(?:wrote|writes|said|says)\s*:$~i', $text )
		|| (bool) preg_match( '~(?:написал|написала|написало|пишет|сообщил|сообщила)\w*\s*(?:\([^)]{0,8}\))?\s*:$~ui', $text );

	// Начинается как дата или день недели («На 08.09.2026 ... написал(а):» - тоже).
	$starts_date = (bool) preg_match(
		'~^(?:on\b|в\s|во\s|на\s+\d|\d{1,2}[.\s/,-]|пн|вт|ср|чт|пт|сб|вс|'
		. 'понедельник|вторник|сред|четверг|пятниц|субот|суббот|воскрес|'
		. 'mon|tue|wed|thu|fri|sat|sun)~ui',
		$text
	);

	$has_email = false !== stripos( (string) $line, 'mailto:' )
		|| (bool) preg_match( '~[^\s@<>]+@[^\s@<>]+~', $text );

	$has_time = (bool) preg_match( '~\d{1,2}:\d{2}~', $text );

	if ( $ends_wrote && ( $has_email || $has_time || $starts_date ) ) {
		return true;
	}

	return $starts_date && $has_email && $has_time;
}

/**
 * Строка из шапки пересланного письма: «Кому: ...», «Тема: ...», «Дата: ...».
 * Так цитируют Яндекс, Mail и Outlook.
 */
function curshen_rve_is_header_line( $line ) {

	$text = curshen_rve_plain( $line );

	if ( '' === $text || mb_strlen( $text, 'UTF-8' ) > 300 ) {
		return false;
	}

	return (bool) preg_match(
		'~^(?:от\s+кого|от|кому|копия|скрытая\s+копия|дата|тема|отправлено|получено|'
		. 'from|to|cc|bcc|sent|date|subject|reply-to)\s*:~ui',
		$text
	);
}

/** Убирает с конца строки пустую разметку: <blockquote>, <br>, &nbsp;, пустые пары тегов. */
function curshen_rve_trim_markup( $html ) {

	$patterns = array(
		'~(?:\s|&nbsp;|&#160;|\xC2\xA0|\xE2\x80[\x80-\x8D]|\xE2\x80\xAF|\xEF\xBB\xBF|<br\s*/?\s*>)+$~i',
		'~<(blockquote|p|div|span)\b[^>]*>(?:\s|&nbsp;|&#160;|\xC2\xA0|<br\s*/?\s*>)*</\1\s*>$~i',
		'~<blockquote\b[^>]*>$~i',
	);

	$prev = null;
	$out  = (string) $html;

	for ( $guard = 0; $guard < 500 && $out !== $prev; $guard++ ) {
		$prev = $out;
		foreach ( $patterns as $re ) {
			$out = preg_replace( $re, '', $out );
		}
		// Закрывающий </blockquote> убираем только если закрытий больше, чем открытий.
		if ( substr_count( strtolower( $out ), '</blockquote' ) > substr_count( strtolower( $out ), '<blockquote' ) ) {
			$out = preg_replace( '~</blockquote\s*>$~i', '', $out );
		}
	}

	return $out;
}

/** Живой текст письма, лежащий ВНЕ блоков <blockquote> (с учётом вложенности). */
function curshen_rve_text_outside_quotes( $html ) {

	$parts = preg_split( '~(<\s*/?\s*blockquote\b[^>]*>)~i', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	$depth = 0;
	$out   = '';

	foreach ( $parts as $part ) {
		if ( preg_match( '~^<\s*/\s*blockquote~i', $part ) ) {
			$depth = max( 0, $depth - 1 );
		} elseif ( preg_match( '~^<\s*blockquote~i', $part ) ) {
			$depth++;
		} elseif ( 0 === $depth ) {
			$out .= $part;
		}
	}

	return curshen_rve_plain( $out );
}

/**
 * Режет по открывающему <blockquote>: почтовики заворачивают в него всё
 * прошлое письмо, и шапка цитаты часто стоит на той же строке, что живой
 * текст, - построчный разбор её не берёт. Режем только если выше осталась
 * живая строка, а ниже вне цитаты текста уже нет: ответ, дописанный
 * под цитатой, так не потеряется.
 */
function curshen_rve_cut_at_blockquote( $html ) {

	$html = (string) $html;
	$pos  = stripos( $html, '<blockquote' );

	if ( false === $pos ) {
		return $html;
	}

	$head = substr( $html, 0, $pos );
	$tail = substr( $html, $pos );

	if ( '' === curshen_rve_plain( $head ) ) {
		return $html;   // письмо состоит из одной цитаты
	}
	if ( '' !== curshen_rve_text_outside_quotes( $tail ) ) {
		return $html;   // под цитатой человек ещё писал
	}

	return $head;
}

/**
 * Где начинается цитата. Идём сверху вниз до первой строки, которая явно
 * открывает цитату: шапка «...написал:» или пара строк заголовков подряд.
 * На одиночной строке «Тема: ...» не режем - её мог написать и человек.
 * Возвращает номер строки или -1.
 */
function curshen_rve_quote_start( array $lines ) {

	$count = count( $lines );

	for ( $i = 0; $i < $count; $i++ ) {

		$line = trim( $lines[ $i ] );

		if ( '' === $line ) {
			continue;
		}
		if ( curshen_rve_is_attribution( $line ) ) {
			return $i;
		}
		if ( curshen_rve_is_header_line( $line ) ) {
			// Смотрим следующую содержательную строку: если это снова заголовок
			// или шапка, значит началась шапка пересланного письма.
			for ( $j = $i + 1; $j < $count; $j++ ) {
				$next = trim( $lines[ $j ] );
				if ( '' === $next || curshen_rve_is_scaffold( $next ) ) {
					continue;
				}
				if ( curshen_rve_is_header_line( $next ) || curshen_rve_is_attribution( $next ) ) {
					return $i;
				}
				break;
			}
		}
	}

	return -1;
}

/** В отрезаемом куске должна быть примета письма, иначе это просто текст. */
function curshen_rve_looks_like_quote( $chunk ) {

	if ( false !== stripos( $chunk, '<blockquote' ) ) {
		return true;
	}
	if ( preg_match( '~[^\s@<>]+@[^\s@<>]+~', curshen_rve_plain( $chunk ) ) ) {
		return true;
	}

	$headers = 0;
	foreach ( preg_split( "/\r\n|\n|\r/", $chunk ) as $line ) {
		if ( curshen_rve_is_header_line( $line ) ) {
			$headers++;
		}
	}

	return $headers >= 2;
}

/**
 * Снимает подпись почтового клиента с конца: строки «Отправлено из ...»
 * и стоящую перед ними отбивку «--».
 */
function curshen_rve_strip_signature( array $lines ) {

	$i        = count( $lines ) - 1;
	$seen_sig = false;

	while ( $i >= 0 ) {

		$line = trim( $lines[ $i ] );

		if ( '' === $line || curshen_rve_is_scaffold( $line ) ) {
			$i--;
			continue;
		}
		if ( curshen_rve_is_signature_line( $line ) ) {
			$seen_sig = true;
			$i--;
			continue;
		}
		if ( $seen_sig && ( curshen_rve_is_signature_delim( $line ) || curshen_rve_is_separator( $line ) ) ) {
			$i--;   // отбивку «--» забираем вместе с подписью
		}
		break;
	}

	return $seen_sig ? array_slice( $lines, 0, $i + 1 ) : $lines;
}

/** Снимает с конца пустую разметку, разделители и одиночные строки заголовков. */
function curshen_rve_strip_trailing_scraps( array $lines ) {

	$i = count( $lines ) - 1;

	while ( $i >= 0 ) {

		$line = trim( $lines[ $i ] );

		if ( '' === $line || curshen_rve_is_scaffold( $line ) || curshen_rve_is_separator( $line )
			|| curshen_rve_is_attribution( $line ) || curshen_rve_is_header_line( $line ) ) {
			$i--;
			continue;
		}
		break;
	}

	return array_slice( $lines, 0, $i + 1 );
}

/**
 * Отрезает от комментария хвост: цитату прошлого письма и подпись клиента.
 * Если своего текста в письме не было вовсе, возвращаем как пришло: пусть
 * лучше врач увидит хвост, чем пустой пузырь без следов.
 */
function curshen_rve_strip_quote_tail( $html ) {

	$html = (string) $html;
	if ( '' === trim( $html ) ) {
		return $html;
	}

	// 1. Блок цитаты целиком, вместе с шапкой на той же строке.
	$html = curshen_rve_cut_at_blockquote( $html );

	$lines = preg_split( "/\r\n|\n|\r/", rtrim( $html ) );

	// 2. Цитата, начатая шапкой или заголовками письма.
	$start = curshen_rve_quote_start( $lines );

	if ( $start > 0 ) {
		$head  = array_slice( $lines, 0, $start );
		$chunk = implode( "\n", array_slice( $lines, $start ) );

		if ( '' !== curshen_rve_plain( implode( "\n", $head ) ) && curshen_rve_looks_like_quote( $chunk ) ) {
			$lines = $head;
		}
	}

	// 3. Подпись почтового клиента и мелкий мусор с конца.
	$lines = curshen_rve_strip_signature( $lines );
	$lines = curshen_rve_strip_trailing_scraps( $lines );
	$lines = curshen_rve_strip_signature( $lines );   // подпись бывает и под цитатой

	$out = curshen_rve_trim_markup( rtrim( implode( "\n", $lines ) ) );

	// Если человек писал внутри цитаты, открывающий <blockquote> мог остаться
	// без пары - закрываем теги, чтобы вывод не поехал.
	if ( substr_count( strtolower( $out ), '<blockquote' ) !== substr_count( strtolower( $out ), '</blockquote' ) ) {
		$out = force_balance_tags( $out );
	}

	return $out;
}

/**
 * Чистим только ответы из почты: у них в запросе лежит ключ Comment Mail.
 * Комментарии, написанные на сайте, не трогаем.
 */
add_filter( 'preprocess_comment', function ( $commentdata ) {

	if ( empty( $_REQUEST['comment_mail_rve_key'] ) ) {
		return $commentdata;
	}
	if ( empty( $commentdata['comment_content'] ) ) {
		return $commentdata;
	}

	$clean = curshen_rve_strip_quote_tail( $commentdata['comment_content'] );

	// Пустой результат значит, что в письме не было ничего, кроме хвоста.
	if ( '' !== curshen_rve_plain( $clean ) ) {
		$commentdata['comment_content'] = $clean;
	}

	return $commentdata;
}, 20 );

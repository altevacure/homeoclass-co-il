<?php
/**
 * Подвал всех страниц: оговорка об учебном характере материалов и копирайт без ссылки на Kadence.
 * Текст на иврите требует вычитки носителем и юристом.
 * wp eval-file footer-disclaimer.php
 */
$html = '<p style="font-size:0.9em;">כל החומרים באתר נועדו ללימוד בלבד ואינם ייעוץ רפואי, אבחון או טיפול. בכל בעיה רפואית יש לפנות לרופא; במצב חירום – למד״א 101.</p>'
	. '<p>{copyright} {year} {site-title}</p>';

set_theme_mod( 'footer_html_content', $html );
echo "footer_html_content set\n";

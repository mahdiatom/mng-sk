<?php
/**
 * جستجوی هدر: کلمات کلیدی تنظیماتی، دوره/رویداد از جداول باشگاه، برگه/نوشته، محصول ووکامرس.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<int, array{title: string, url: string}>
 */
function sc_get_header_search_suggestions_parsed() {
	$raw = sc_get_setting( 'sc_header_search_suggestions_json', '' );
	$items = json_decode( (string) $raw, true );
	if ( ! is_array( $items ) ) {
		return array();
	}
	$out = array();
	foreach ( $items as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
		$url   = isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '';
		if ( $title === '' || $url === '' ) {
			continue;
		}
		$out[] = array(
			'title' => $title,
			'url'   => $url,
			'type'  => 'suggestion',
		);
	}
	return $out;
}

/**
 * قیمت محصول بدون HTML و موجودیت (&nbsp; و …) — فقط عدد و «تومان»
 *
 * @param object $product محصول ووکامرس.
 * @return string
 */
function sc_header_search_format_product_price_plain( $product ) {
	if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
		return '';
	}
	if ( $product->get_price() === '' ) {
		return '';
	}
	$amount = (float) wc_get_price_to_display( $product );
	$formatted = number_format( $amount, 0, '.', ',' );
	return $formatted . ' تومان';
}

/**
 * آیا رشته needle در haystack هست (پشتیبانی فارسی با mbstring)
 *
 * @param string $haystack متن جستجو شده.
 * @param string $needle سوزن.
 * @return bool
 */
function sc_header_search_utf8_contains( $haystack, $needle ) {
	$haystack = (string) $haystack;
	$needle   = (string) $needle;
	if ( $needle === '' ) {
		return false;
	}
	if ( function_exists( 'mb_stripos' ) ) {
		return mb_stripos( $haystack, $needle, 0, 'UTF-8' ) !== false;
	}
	return stripos( $haystack, $needle ) !== false;
}

/**
 * همهٔ مقصدهای قابل تنظیم برای کلمات کلیدی (فروشگاه، حساب، اندپوینت‌های باشگاه).
 *
 * @return array<string, array{title: string, url: string, type: string, group: string}>
 */
function sc_header_search_link_targets_map() {
	static $cache = null;
	if ( is_array( $cache ) ) {
		return $cache;
	}
	$cache = array();

	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$shop = wc_get_page_permalink( 'shop' );
		if ( $shop ) {
			$cache['wc_shop'] = array(
				'title' => 'فروشگاه (صفحه محصولات)',
				'url'   => $shop,
				'type'  => 'service',
				'group' => 'site',
			);
		}
		$myacc = wc_get_page_permalink( 'myaccount' );
		if ( $myacc ) {
			$cache['wc_myaccount'] = array(
				'title' => 'حساب کاربری / ورود',
				'url'   => $myacc,
				'type'  => 'service',
				'group' => 'site',
			);
		}
	}
	if ( function_exists( 'wc_get_cart_url' ) ) {
		$cart = wc_get_cart_url();
		if ( $cart ) {
			$cache['wc_cart'] = array(
				'title' => 'سبد خرید',
				'url'   => $cart,
				'type'  => 'service',
				'group' => 'site',
			);
		}
	}

	if ( ! function_exists( 'wc_get_account_endpoint_url' ) ) {
		return $cache;
	}

	$endpoints = array(
		'sc-submit-documents' => 'اطلاعات بازیکن',
		'sc-enroll-course'    => 'ثبت نام در دوره',
		'sc-my-courses'       => 'دوره‌های من',
		'sc-my-attendances'   => 'حضور و غیاب‌های من',
		'sc-events'           => 'رویدادها / مسابقات',
		'sc-my-events'        => 'رویدادهای من',
		'sc-event-detail'     => 'جزئیات رویداد / ثبت‌نام',
		'sc-event-success'    => 'تأیید ثبت‌نام رویداد',
		'sc-invoices'         => 'صورت‌حساب‌ها',
		'sc-faq'              => 'سوالات متداول',
		'sc-my-honors'        => 'افتخارات من',
		'sc-my-certificates'  => 'گواهینامه‌ها',
		'sc-notifications'    => 'اطلاعیه‌ها',
		'sc-wallet'           => 'کیف پول',
		'sc-support-tickets'  => 'تیکت پشتیبانی',
		'sc-private-notes'    => 'یادداشت‌های من',
		'sc-my-programs'      => 'برنامه‌های تخصصی من',
		'sc-private-classes'  => 'کلاس خصوصی',
		'shop'                => 'فروشگاه (تب حساب کاربری)',
		'my-orders'           => 'سفارش‌های فروشگاه',
		'edit-account'        => 'ویرایش حساب و رمز',
		'dashboard'           => 'پیشخوان حساب کاربری',
	);

	foreach ( $endpoints as $endpoint => $title ) {
		$key            = 'svc_' . str_replace( '-', '_', $endpoint );
		$cache[ $key ] = array(
			'title' => $title,
			'url'   => wc_get_account_endpoint_url( $endpoint ),
			'type'  => 'service',
			'group' => 'account',
		);
	}

	$cache['svc_bot_connect'] = array(
		'title' => 'اتصال به ربات',
		'url'   => trailingslashit( home_url( 'my-account/bot-connect' ) ),
		'type'  => 'service',
		'group' => 'account',
	);

	return $cache;
}

/**
 * میانبرهای ثابت برای هنگام «بدون نتیجه» و فوکوس روی جستجو (صفحات پرکاربرد).
 *
 * @return array<int, array{title: string, url: string, type: string}>
 */
function sc_header_search_quick_links_for_overlay() {
	$map     = sc_header_search_link_targets_map();
	$shop_on = (int) sc_get_setting( 'pro_feature_shop', 0 ) === 1;

	$keys = array(
		'svc_sc_enroll_course',
		'svc_sc_my_courses',
		'svc_sc_events',
		'wc_shop',
		'wc_cart',
		'svc_sc_support_tickets',
		'svc_sc_notifications',
		'svc_sc_wallet',
		'svc_dashboard',
	);

	$out  = array();
	$seen = array();

	foreach ( $keys as $key ) {
		if ( ( $key === 'wc_shop' || $key === 'wc_cart' ) && ! $shop_on ) {
			continue;
		}
		if ( ! isset( $map[ $key ] ) || empty( $map[ $key ]['url'] ) ) {
			continue;
		}
		$url = (string) $map[ $key ]['url'];
		if ( isset( $seen[ $url ] ) ) {
			continue;
		}
		$seen[ $url ] = true;
		$out[]        = array(
			'title' => $map[ $key ]['title'],
			'url'   => $url,
			'type'  => 'shortcut',
		);
	}

	return $out;
}

/**
 * نقشهٔ ذخیره‌شده کلمات کلیدی (کلید جدید + سازگاری با ذخیره قدیمی فقط صفحات).
 *
 * @return array<string, string>
 */
function sc_header_search_get_saved_keywords_map() {
	$raw = sc_get_setting( 'sc_header_search_keywords_json', '' );
	if ( $raw === '' ) {
		$raw = sc_get_setting( 'sc_header_search_page_keywords_json', '' );
	}
	$map = json_decode( (string) $raw, true );
	return is_array( $map ) ? $map : array();
}

/**
 * نتایجی که مستقیماً از تنظیمات کلمات کلیدی می‌آیند (صفحات وردپرس + خدمات باشگاه).
 *
 * @param string $q عبارت جستجو.
 * @return array<int, array{title: string, url: string, type: string}>
 */
function sc_header_search_keyword_map_matches( $q ) {
	$q = trim( (string) $q );
	if ( $q === '' ) {
		return array();
	}

	$map       = sc_header_search_get_saved_keywords_map();
	$targets   = sc_header_search_link_targets_map();
	$out       = array();
	$seen_urls = array();

	foreach ( $map as $storage_key => $blob ) {
		$blob = (string) $blob;
		if ( $blob === '' ) {
			continue;
		}

		$parts = preg_split( '/[\r\n,،؛;]+/u', $blob );
		$hit   = false;
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( $part === '' ) {
				continue;
			}
			if ( sc_header_search_utf8_contains( $q, $part ) || sc_header_search_utf8_contains( $part, $q ) ) {
				$hit = true;
				break;
			}
		}
		if ( ! $hit ) {
			continue;
		}

		$sk = (string) $storage_key;

		$page_id = 0;
		if ( preg_match( '/^page_(\d+)$/', $sk, $m ) ) {
			$page_id = absint( $m[1] );
		} elseif ( ctype_digit( (string) $sk ) ) {
			$page_id = absint( $sk );
		}

		$title = '';
		$url   = '';
		$type  = 'page';

		if ( $page_id > 0 ) {
			$post = get_post( $page_id );
			if ( ! $post || $post->post_type !== 'page' || $post->post_status !== 'publish' ) {
				continue;
			}
			$title = get_the_title( $post );
			$url   = get_permalink( $post );
			$type  = 'page';
		} elseif ( isset( $targets[ $sk ] ) && ! empty( $targets[ $sk ]['url'] ) ) {
			$title = $targets[ $sk ]['title'];
			$url   = $targets[ $sk ]['url'];
			$type  = isset( $targets[ $sk ]['type'] ) ? $targets[ $sk ]['type'] : 'service';
		} else {
			continue;
		}

		if ( ! $url || isset( $seen_urls[ $url ] ) ) {
			continue;
		}
		$seen_urls[ $url ] = true;
		$out[]             = array(
			'title' => $title,
			'url'   => $url,
			'type'  => $type,
		);
		if ( count( $out ) >= 25 ) {
			break;
		}
	}

	return $out;
}

/**
 * حداقل طول عبارت برای جستجوی «هوشمند» در جداول و SQL (کاهش بار سرور).
 *
 * @param string $q
 * @return bool
 */
function sc_header_search_smart_query_long_enough( $q ) {
	$q = trim( (string) $q );
	if ( $q === '' ) {
		return false;
	}
	if ( function_exists( 'mb_strlen' ) ) {
		return mb_strlen( $q, 'UTF-8' ) >= 2;
	}
	return strlen( $q ) >= 2;
}

/**
 * آیا جدول با نام داده‌شده وجود دارد؟
 *
 * @param string $table نام کامل جدول شامل پیشوند.
 * @return bool
 */
function sc_header_search_db_table_exists( $table ) {
	global $wpdb;
	$t = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	return $t === $table;
}

/**
 * نام ستون‌های یک جدول (برای سازگاری با مهاجرت‌های قدیمی).
 *
 * @param string $table نام کامل جدول.
 * @return array<int, string>
 */
function sc_header_search_db_table_columns( $table ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is prefix + known suffix.
	$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" );
	if ( ! is_array( $rows ) ) {
		return array();
	}
	$out = array();
	foreach ( $rows as $row ) {
		if ( isset( $row->Field ) ) {
			$out[] = (string) $row->Field;
		}
	}
	return $out;
}

/**
 * دوره‌های گروهی فعال که عنوان یا توضیح با عبارت هم‌پوشانی دارد.
 *
 * @param string $q
 * @param int    $limit
 * @return array<int, array{title: string, url: string, type: string}>
 */
function sc_header_search_smart_courses( $q, $limit ) {
	global $wpdb;
	$out   = array();
	$table = $wpdb->prefix . 'sc_courses';
	if ( ! sc_header_search_db_table_exists( $table ) ) {
		return $out;
	}
	if ( ! function_exists( 'wc_get_account_endpoint_url' ) ) {
		return $out;
	}
	$base = wc_get_account_endpoint_url( 'sc-enroll-course' );
	if ( ! $base ) {
		return $out;
	}

	$limit = min( 12, max( 1, (int) $limit ) );
	$like  = '%' . $wpdb->esc_like( $q ) . '%';

	$cols = sc_header_search_db_table_columns( $table );
	$ct_filter = '';
	if ( in_array( 'course_type', $cols, true ) ) {
		$ct_filter = " AND (course_type IS NULL OR course_type = '' OR course_type = 'group') ";
	}

	$sql = $wpdb->prepare(
		"SELECT id, title FROM `{$table}`
		 WHERE deleted_at IS NULL AND is_active = 1
		 {$ct_filter}
		 AND (title LIKE %s OR description LIKE %s OR chapter LIKE %s)
		 ORDER BY (CASE WHEN title LIKE %s THEN 0 ELSE 1 END) ASC, updated_at DESC
		 LIMIT %d",
		$like,
		$like,
		$like,
		$like,
		$limit
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is wp prefix + sc_courses.
	$rows = $wpdb->get_results( $sql );
	if ( ! is_array( $rows ) ) {
		return $out;
	}

	foreach ( $rows as $row ) {
		$cid = isset( $row->id ) ? (int) $row->id : 0;
		if ( $cid <= 0 || empty( $row->title ) ) {
			continue;
		}
		$url = $base . '#course_item_' . $cid;
		$out[] = array(
			'title' => $row->title,
			'url'   => $url,
			'type'  => 'course',
		);
	}

	return $out;
}

/**
 * رویدادهای فعال که نام یا توضیح با عبارت هم‌پوشانی دارد.
 *
 * @param string $q
 * @param int    $limit
 * @return array<int, array{title: string, url: string, type: string}>
 */
function sc_header_search_smart_events( $q, $limit ) {
	global $wpdb;
	$out   = array();
	$table = $wpdb->prefix . 'sc_events';
	if ( ! sc_header_search_db_table_exists( $table ) ) {
		return $out;
	}
	if ( ! function_exists( 'wc_get_page_permalink' ) || ! function_exists( 'wc_get_endpoint_url' ) ) {
		return $out;
	}
	$myacc = wc_get_page_permalink( 'myaccount' );
	if ( ! $myacc ) {
		return $out;
	}

	$limit = min( 12, max( 1, (int) $limit ) );
	$like  = '%' . $wpdb->esc_like( $q ) . '%';

	$sql = $wpdb->prepare(
		"SELECT id, name FROM `{$table}`
		 WHERE deleted_at IS NULL AND is_active = 1
		 AND (name LIKE %s OR description LIKE %s OR event_location LIKE %s OR chapter LIKE %s)
		 ORDER BY (CASE WHEN name LIKE %s THEN 0 ELSE 1 END) ASC, updated_at DESC
		 LIMIT %d",
		$like,
		$like,
		$like,
		$like,
		$like,
		$limit
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $sql );
	if ( ! is_array( $rows ) ) {
		return $out;
	}

	foreach ( $rows as $row ) {
		$eid = isset( $row->id ) ? (int) $row->id : 0;
		if ( $eid <= 0 || empty( $row->name ) ) {
			continue;
		}
		$url = wc_get_endpoint_url( 'sc-event-detail', (string) $eid, $myacc );
		if ( ! $url ) {
			continue;
		}
		$out[] = array(
			'title' => $row->name,
			'url'   => $url,
			'type'  => 'event',
		);
	}

	return $out;
}

/**
 * برگه و نوشتهٔ منتشرشده؛ اولویت با تطابق عنوان، سپس تازه‌ترین ویرایش.
 *
 * @param string $q
 * @param int    $limit
 * @return array<int, array{title: string, url: string, type: string}>
 */
function sc_header_search_smart_wp_posts( $q, $limit ) {
	global $wpdb;
	$out = array();

	$limit = min( 15, max( 1, (int) $limit ) );
	$like  = '%' . $wpdb->esc_like( $q ) . '%';

	$sql = $wpdb->prepare(
		"SELECT ID, post_type FROM {$wpdb->posts}
		 WHERE post_status = 'publish'
		 AND post_type IN ('page','post')
		 AND (post_title LIKE %s OR post_excerpt LIKE %s OR post_content LIKE %s)
		 ORDER BY (CASE WHEN post_title LIKE %s THEN 0 ELSE 1 END) ASC, post_modified DESC
		 LIMIT %d",
		$like,
		$like,
		$like,
		$like,
		$limit
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $sql );
	if ( ! is_array( $rows ) ) {
		return $out;
	}

	foreach ( $rows as $row ) {
		$id = isset( $row->ID ) ? (int) $row->ID : 0;
		if ( $id <= 0 ) {
			continue;
		}
		$post = get_post( $id );
		if ( ! $post ) {
			continue;
		}
		$url = get_permalink( $post );
		if ( ! $url ) {
			continue;
		}
		$out[] = array(
			'title' => get_the_title( $post ),
			'url'   => $url,
			'type'  => $post->post_type === 'page' ? 'page' : 'post',
		);
	}

	return $out;
}

/**
 * @param string $q
 * @return array<int, array{title: string, url: string, type: string, price?: string}>
 */
function sc_header_search_collect_results( $q ) {
	$results = array();
	$seen    = array();
	$max_ce  = 8;
	$max_wp  = 10;
	$max_p   = 6;

	foreach ( sc_header_search_keyword_map_matches( $q ) as $row ) {
		if ( isset( $seen[ $row['url'] ] ) ) {
			continue;
		}
		$seen[ $row['url'] ] = true;
		$results[]           = $row;
	}

	if ( sc_header_search_smart_query_long_enough( $q ) ) {
		foreach ( sc_header_search_smart_courses( $q, $max_ce ) as $row ) {
			if ( isset( $seen[ $row['url'] ] ) ) {
				continue;
			}
			$seen[ $row['url'] ] = true;
			$results[]           = $row;
		}
		foreach ( sc_header_search_smart_events( $q, $max_ce ) as $row ) {
			if ( isset( $seen[ $row['url'] ] ) ) {
				continue;
			}
			$seen[ $row['url'] ] = true;
			$results[]           = $row;
		}
		foreach ( sc_header_search_smart_wp_posts( $q, $max_wp ) as $row ) {
			if ( isset( $seen[ $row['url'] ] ) ) {
				continue;
			}
			$seen[ $row['url'] ] = true;
			$results[]           = $row;
		}
	} else {
		$query = new WP_Query(
			array(
				's'              => $q,
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => min( $max_wp, 6 ),
			)
		);
		foreach ( $query->posts as $p ) {
			$url = get_permalink( $p );
			if ( isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
			$results[]    = array(
				'title' => get_the_title( $p ),
				'url'   => $url,
				'type'  => $p->post_type === 'page' ? 'page' : 'post',
			);
		}
		wp_reset_postdata();
	}

	$shop_on = (int) sc_get_setting( 'pro_feature_shop', 0 ) === 1;
	if ( $shop_on && class_exists( 'WooCommerce' ) ) {
		$pq = new WP_Query(
			array(
				's'              => $q,
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => $max_p,
			)
		);
		foreach ( $pq->posts as $p ) {
			$product = wc_get_product( $p );
			if ( ! $product ) {
				continue;
			}
			$url = $product->get_permalink();
			if ( isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
			$row          = array(
				'title' => $product->get_name(),
				'url'   => $url,
				'type'  => 'product',
			);
			$plain_price = sc_header_search_format_product_price_plain( $product );
			if ( $plain_price !== '' ) {
				$row['price'] = $plain_price;
			}
			$results[] = $row;
		}
		wp_reset_postdata();
	}

	return $results;
}

function sc_ajax_header_search() {
	check_ajax_referer( 'sc_header_search', 'nonce' );

	$q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
	$q = trim( $q );

	$suggestions = sc_get_header_search_suggestions_parsed();

	if ( $q === '' ) {
		wp_send_json_success(
			array(
				'suggestions' => $suggestions,
				'results'     => array(),
			)
		);
	}

	wp_send_json_success(
		array(
			'suggestions' => $suggestions,
			'results'     => sc_header_search_collect_results( $q ),
		)
	);
}

add_action( 'wp_ajax_sc_header_search', 'sc_ajax_header_search' );
add_action( 'wp_ajax_nopriv_sc_header_search', 'sc_ajax_header_search' );

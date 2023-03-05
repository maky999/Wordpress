//Code Buộc đăng nhập để truy cập

function v_forcelogin() {

	// Ngoại lệ cho các yêu cầu AJAX, Cron hoặc WP-CLI
	if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	// Bảo lãnh nếu khách truy cập hiện tại là người dùng đã đăng nhập, trừ khi Multisite được bật
	if ( is_user_logged_in() && ! is_multisite() ) {
		return;
	}

	// Nhận URL đã truy cập
	$schema = isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https://' : 'http://';
	$url = $schema . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

	// Bail if visiting the login URL. Fix for custom login URLs
	if ( preg_replace( '/\?.*/', '', wp_login_url() ) === preg_replace( '/\?.*/', '', $url ) ) {
		return;
	}

	/**
	 * Whitelist filter.
	 *
	 * @since 3.0.0
	 * @deprecated 5.5.0 Use {@see 'v_forcelogin_bypass'} instead.
	 *
	 * @param array An array of absolute URLs.
	 */
	$allowed = apply_filters_deprecated( 'v_forcelogin_whitelist', array( array() ), '5.5.0', 'v_forcelogin_bypass' );

	/**
	 * Bypass filter.
	 *
	 * @since 5.0.0
	 * @since 5.2.0 Added the `$url` parameter.
	 *
	 * @param bool Whether to disable Force Login. Default false.
	 * @param string $url The visited URL.
	 */
	$bypass = apply_filters( 'v_forcelogin_bypass', in_array( $url, $allowed ), $url );

	// Bail if bypass is enabled
	if ( $bypass ) {
		return;
	}

	// Chỉ cho phép người dùng nhiều trang truy cập vào các trang được chỉ định của họ
	if ( is_multisite() && is_user_logged_in() ) {
		if ( ! is_user_member_of_blog() && ! current_user_can( 'setup_network' ) ) {
			$message = apply_filters( 'v_forcelogin_multisite_message', __( "You're not authorized to access this site.", 'wp-force-login' ), $url );
			wp_die( $message, get_option( 'blogname' ) . ' &rsaquo; ' . __( 'Error', 'wp-force-login' ) );
		}
		return;
	}

	// Xác định URL chuyển hướng
	$redirect_url = apply_filters( 'v_forcelogin_redirect', $url );

	// Đặt tiêu đề để ngăn bộ nhớ đệm
	nocache_headers();

	// Chuyển hướng khách truy cập trái phép
	wp_safe_redirect( wp_login_url( $redirect_url ), 302 );
	exit;
}
add_action( 'template_redirect', 'v_forcelogin' );

/**
 * Restrict REST API for authorized users only
 *
 * @since 5.1.0
 * @param WP_Error|null|bool $result WP_Error if authentication error, null if authentication
 *                              method wasn't used, true if authentication succeeded.
 *
 * @return WP_Error|null|bool
 */
function v_forcelogin_rest_access( $result ) {
	if ( null === $result && ! is_user_logged_in() ) {
		return new WP_Error( 'rest_unauthorized', __( 'Only authenticated users can access the REST API.', 'wp-force-login' ), array( 'status' => rest_authorization_required_code() ) );
	}
	return $result;
}
add_filter( 'rest_authentication_errors', 'v_forcelogin_rest_access', 99 );

/*
 * Localization
 */
function v_forcelogin_load_textdomain() {
	load_plugin_textdomain( 'wp-force-login', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'v_forcelogin_load_textdomain' );

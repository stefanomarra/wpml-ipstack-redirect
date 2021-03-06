<?php
/*
Plugin Name: WPML IPStack Redirect
Plugin URI: https://www.stefanomarra.com
Description: Redirects users to their appropriate languages by utilizing third-party service ipstack.com
Version: 1.0.1
Author: Stefano Marra
Author URI: https://www.stefanomarra.com
License: GPL2
*/

class WPML_IPStack_Redirect
{

	const GEO_PROVIDER_WC = 'WC_Geolocation';
	const GEO_PROVIDER_IPSTACK = 'IPStack';

	private $client_geolocation_details = false;
	private $geolocation_provider = false;

	/**
	 * Initialize
	 */
	function __construct() {


		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
			add_action( 'admin_menu', array( $this, 'digest_post_data' ) );
		}
		else {
			add_action('template_redirect', array( &$this, 'redirect_ip_country') );
		}
	}

	public static function setCookie($lang = null) {
		global $sitepress_settings;

		$expiry = time() + ($sitepress_settings['remember_language']*HOUR_IN_SECONDS);
		$value = 'checked|' . $expiry;

		setcookie('icl_ip_to_country_check', $value, $expiry, COOKIEPATH, COOKIE_DOMAIN);
		self::setLanguageCookie($lang, $expiry);
	}

	public static function setLanguageCookie($lang = null, $expiry = null) {
		global $sitepress_settings;

		if ( !$expiry ) {
			$expiry = time() + ($sitepress_settings['remember_language']*HOUR_IN_SECONDS);
		}

		setcookie('icl_ip_to_country_lang', $lang, $expiry, COOKIEPATH, COOKIE_DOMAIN);
	}

	function digest_post_data() {

		if ( isset( $_POST['wpml_ipstack_redirect_api_key'] ) ) {

			$location = 'options-general.php?page=wpml_ipstack_redirect_settings';

			if( !wp_verify_nonce( $_POST['_wpnonce'], 'wpml_ipstack_redirect_update_action' )){
				$this->redirect_user( $location . '&feedback=form_submission_error' );
			}

			// Save in DB and redirect
			update_option( 'wpml_ipstack_redirect_api_key' , sanitize_text_field( trim( $_POST['wpml_ipstack_redirect_api_key'] ) ) );

			$geo_provider = isset($_POST['wpml_ipstack_redirect_geolocation_provider'])?$_POST['wpml_ipstack_redirect_geolocation_provider']:'IPStack';
			update_option( 'wpml_ipstack_redirect_geolocation_provider' , sanitize_text_field( trim($geo_provider) ) );

			$avoid_post_missing_redirect = isset($_POST['wpml_ipstack_redirect_avoid_post_missing_redirect'])?$_POST['wpml_ipstack_redirect_avoid_post_missing_redirect']:'off';
			update_option( 'wpml_ipstack_redirect_avoid_post_missing_redirect' , sanitize_text_field( trim( $avoid_post_missing_redirect ) ) );

			$this->redirect_user( $location . '&feedback=success' );
		}

	}

	function add_admin_page(){

		add_options_page(
			'WPML IPStack Redirect',
			'WPML IPStack Redirect',
			'manage_options',
			'wpml_ipstack_redirect_settings',
			array( $this, 'wpml_ipstack_redirect_admin_page' ) );
	}

	function wpml_ipstack_redirect_admin_page(){

		$this->set_wp_options_with_default_values_if_necessary();

		$api_key = get_option( 'wpml_ipstack_redirect_api_key' );
		$geo_provider = get_option( 'wpml_ipstack_redirect_geolocation_provider' );
		$fallback_redirect = get_option( 'wpml_ipstack_redirect_avoid_post_missing_redirect' );

		include 'wpml-ipstack-redirect-admin-page.class.php';

		$admin_page = new WPML_IPStack_Redirect_Admin_Page( $api_key, $geo_provider, $fallback_redirect );
		$admin_page->display_wpml_ipstack_redirect_admin_page();

	}

	private function set_wp_options_with_default_values_if_necessary(){

        if ( null === get_option('wpml_ipstack_redirect_api_key', null) ) {
			add_option('wpml_ipstack_redirect_api_key', '');
        }
    }

    /**
     * https://wordpress.stackexchange.com/questions/12863/check-if-wp-login-is-current-page
     */
    function is_wp_login() {
		$ABSPATH_MY = str_replace(array('\\','/'), DIRECTORY_SEPARATOR, ABSPATH);
		return ((in_array($ABSPATH_MY.'wp-login.php', get_included_files()) || in_array($ABSPATH_MY.'wp-register.php', get_included_files()) ) || (isset($_GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') || $_SERVER['PHP_SELF']== '/wp-login.php');
    }

    function redirect_user( $location ){
		header("Location: $location");
		exit();
	}

	function get_api_key() {
		return apply_filters( 'wpml_ipstack_redirect_get_api_key', get_option('wpml_ipstack_redirect_api_key') );
	}

	function get_avoid_post_missing_redirect() {
		return apply_filters( 'wpml_ipstack_redirect_get_avoid_post_missing_redirect', get_option('wpml_ipstack_redirect_avoid_post_missing_redirect') );
	}

	function force_redirect() {
		if ( $this->is_wp_login() ) {
			return apply_filters( 'wpml_ipstack_redirect_force_redirect', false );
		}

		if ( is_admin() ) {
			return apply_filters( 'wpml_ipstack_redirect_force_redirect', false );
		}

		if ( current_user_can('editor') || current_user_can('administrator') ) {

		}

		return apply_filters( 'wpml_ipstack_redirect_force_redirect', true );
	}

	function get_client_ip() {
		return $_SERVER['REMOTE_ADDR'];
	}

	function get_language_code_by_country_code($code = 'US') {

		# http://wiki.openstreetmap.org/wiki/Nominatim/Country_Codes
		$arr = array(
			'ad' => 'ca',
			'ae' => 'ar',
			'af' => 'fa',
			'ag' => 'en',
			'ai' => 'en',
			'al' => 'sq',
			'am' => 'hy',
			'an' => 'nl',
			'ao' => 'pt',
			'aq' => 'en',
			'ar' => 'es',
			'as' => 'en',
			'at' => 'de',
			'au' => 'en',
			'aw' => 'nl',
			'ax' => 'sv',
			'az' => 'az',
			'ba' => 'bs',
			'bb' => 'en',
			'bd' => 'bn',
			'be' => 'nl',
			'bf' => 'fr',
			'bg' => 'bg',
			'bh' => 'ar',
			'bi' => 'fr',
			'bj' => 'fr',
			'bl' => 'fr',
			'bm' => 'en',
			'bn' => 'ms',
			'bo' => 'es',
			'br' => 'pt',
			'bq' => 'nl',
			'bs' => 'en',
			'bt' => 'dz',
			'bv' => 'no',
			'bw' => 'en',
			'by' => 'be',
			'bz' => 'en',
			'ca' => 'en',
			'cc' => 'en',
			'cd' => 'fr',
			'cf' => 'fr',
			'cg' => 'fr',
			'ch' => 'de',
			'ci' => 'fr',
			'ck' => 'en',
			'cl' => 'es',
			'cm' => 'fr',
			'cn' => 'zh',
			'co' => 'es',
			'cr' => 'es',
			'cu' => 'es',
			'cv' => 'pt',
			'cw' => 'nl',
			'cx' => 'en',
			'cy' => 'el',
			'cz' => 'cs',
			'de' => 'de',
			'dj' => 'fr',
			'dk' => 'da',
			'dm' => 'en',
			'do' => 'es',
			'dz' => 'ar',
			'ec' => 'es',
			'ee' => 'et',
			'eg' => 'ar',
			'eh' => 'ar',
			'er' => 'ti',
			'es' => 'es',
			'et' => 'am',
			'fi' => 'fi',
			'fj' => 'en',
			'fk' => 'en',
			'fm' => 'en',
			'fo' => 'fo',
			'fr' => 'fr',
			'ga' => 'fr',
			'gb' => 'en',
			'gd' => 'en',
			'ge' => 'ka',
			'gf' => 'fr',
			'gg' => 'en',
			'gh' => 'en',
			'gi' => 'en',
			'gl' => 'kl',
			'gm' => 'en',
			'gn' => 'fr',
			'gp' => 'fr',
			'gq' => 'es',
			'gr' => 'el',
			'gs' => 'en',
			'gt' => 'es',
			'gu' => 'en',
			'gw' => 'pt',
			'gy' => 'en',
			'hk' => 'zh',
			'hm' => 'en',
			'hn' => 'es',
			'hr' => 'hr',
			'ht' => 'fr',
			'hu' => 'hu',
			'id' => 'id',
			'ie' => 'en',
			'il' => 'he',
			'im' => 'en',
			'in' => 'hi',
			'io' => 'en',
			'iq' => 'ar',
			'ir' => 'fa',
			'is' => 'is',
			'it' => 'it',
			'je' => 'en',
			'jm' => 'en',
			'jo' => 'ar',
			'jp' => 'ja',
			'ke' => 'sw',
			'kg' => 'ky',
			'kh' => 'km',
			'ki' => 'en',
			'km' => 'ar',
			'kn' => 'en',
			'kp' => 'ko',
			'kr' => 'ko',
			'kw' => 'ar',
			'ky' => 'en',
			'kz' => 'kk',
			'la' => 'lo',
			'lb' => 'ar',
			'lc' => 'en',
			'li' => 'de',
			'lk' => 'si',
			'lr' => 'en',
			'ls' => 'en',
			'lt' => 'lt',
			'lu' => 'lb',
			'lv' => 'lv',
			'ly' => 'ar',
			'ma' => 'ar',
			'mc' => 'fr',
			'md' => 'ru',
			'me' => 'srp',
			'mf' => 'fr',
			'mg' => 'mg',
			'mh' => 'en',
			'mk' => 'mk',
			'ml' => 'fr',
			'mm' => 'my',
			'mn' => 'mn',
			'mo' => 'zh',
			'mp' => 'ch',
			'mq' => 'fr',
			'mr' => 'ar',
			'ms' => 'en',
			'mt' => 'mt',
			'mu' => 'mfe',
			'mv' => 'dv',
			'mw' => 'en',
			'mx' => 'es',
			'my' => 'ms',
			'mz' => 'pt',
			'na' => 'en',
			'nc' => 'fr',
			'ne' => 'fr',
			'nf' => 'en',
			'ng' => 'en',
			'ni' => 'es',
			'nl' => 'nl',
			'no' => 'nb',
			'np' => 'ne',
			'nr' => 'na',
			'nu' => 'niu',
			'nz' => 'en',
			'om' => 'ar',
			'pa' => 'es',
			'pe' => 'es',
			'pf' => 'fr',
			'pg' => 'en',
			'ph' => 'en',
			'pk' => 'en',
			'pl' => 'pl',
			'pm' => 'fr',
			'pn' => 'en',
			'pr' => 'es',
			'ps' => 'ar',
			'pt' => 'pt',
			'pw' => 'en',
			'py' => 'es',
			'qa' => 'ar',
			're' => 'fr',
			'ro' => 'ro',
			'rs' => 'sr',
			'ru' => 'ru',
			'rw' => 'rw',
			'sa' => 'ar',
			'sb' => 'en',
			'sc' => 'fr',
			'sd' => 'ar',
			'se' => 'sv',
			'sg' => 'en',
			'sh' => 'en',
			'si' => 'sl',
			'sj' => 'no',
			'sk' => 'sk',
			'sl' => 'en',
			'sm' => 'it',
			'sn' => 'fr',
			'so' => 'so',
			'sr' => 'nl',
			'st' => 'pt',
			'ss' => 'en',
			'sv' => 'es',
			'sx' => 'nl',
			'sy' => 'ar',
			'sz' => 'en',
			'tc' => 'en',
			'td' => 'fr',
			'tf' => 'fr',
			'tg' => 'fr',
			'th' => 'th',
			'tj' => 'tg',
			'tk' => 'tkl',
			'tl' => 'pt',
			'tm' => 'tk',
			'tn' => 'ar',
			'to' => 'en',
			'tr' => 'tr',
			'tt' => 'en',
			'tv' => 'en',
			'tw' => 'zh',
			'tz' => 'sw',
			'ua' => 'uk',
			'ug' => 'en',
			'um' => 'en',
			'us' => 'en',
			'uy' => 'es',
			'uz' => 'uz',
			'va' => 'it',
			'vc' => 'en',
			've' => 'es',
			'vg' => 'en',
			'vi' => 'en',
			'vn' => 'vi',
			'vu' => 'bi',
			'wf' => 'fr',
			'ws' => 'sm',
			'ye' => 'ar',
			'yt' => 'fr',
			'za' => 'zu',
			'zm' => 'en',
			'zw' => 'end'
		);

		if ( isset($arr[strtolower($code)]) ) {
			$language = $arr[strtolower($code)];
		}
		else {
			$language = 'en';
		}

		return apply_filters( 'WPML_IPStack_Redirect_get_language_code_by_country_code', $language, $code, $arr );
	}

	function get_geolocation_provider() {
		if ( class_exists('WC_Geolocation') ) {
			$geo_provider = get_option( 'wpml_ipstack_redirect_geolocation_provider' );

			switch ($geo_provider) {
				case self::GEO_PROVIDER_WC:
				case self::GEO_PROVIDER_IPSTACK:
					return $geo_provider;
					break;

				default:
					return self::GEO_PROVIDER_IPSTACK;
			}
		}

		return self::GEO_PROVIDER_IPSTACK;
	}

	function get_client_geolocation_details() {
		$ip = $this->get_client_ip();
		$api_key = $this->get_api_key();

		if ( $this->client_geolocation_details ) {
			return $this->client_geolocation_details;
		}

		switch ($this->get_geolocation_provider()) {
			case self::GEO_PROVIDER_WC:
				$this->geolocation_provider = self::GEO_PROVIDER_WC;
				$this->client_geolocation_details = WC_Geolocation::geolocate_ip();
				break;

			case self::GEO_PROVIDER_IPSTACK:
			default:
				$this->geolocation_provider = self::GEO_PROVIDER_IPSTACK;
				$this->client_geolocation_details = json_decode(file_get_contents("http://api.ipstack.com/{$ip}?access_key={$api_key}&format=1"));
				break;
		}

		return $this->client_geolocation_details;
	}

	function get_country_code_by_ip() {
		$details = $this->get_client_geolocation_details();

		switch ($this->geolocation_provider) {
			case self::GEO_PROVIDER_WC:
				$country_code = strtolower($details['country']);
				break;

			case self::GEO_PROVIDER_IPSTACK:
			default:
				$country_code = strtolower($details->country_code);
				break;
		}

		return apply_filters( 'WPML_IPStack_Redirect_get_country_code_by_ip', $country_code );
	}

	function get_language_code_by_ip() {
		$details = $this->get_client_geolocation_details();

		switch ($this->geolocation_provider) {
			case self::GEO_PROVIDER_WC:
				$language_code = $this->get_language_code_by_country_code($details['country']);
				break;

			case self::GEO_PROVIDER_IPSTACK:
			default:
				if ( !is_array($details->location->languages) ) {
					$language_code = false;
				}

				foreach ($details->location->languages as $lang) {
					$language_code = strtolower($lang->code);
				}
				break;
		}


		return apply_filters( 'WPML_IPStack_Redirect_get_language_code_by_ip', $language_code );
	}

	function is_check_cookie_valid() {
		if ( !isset($_COOKIE['icl_ip_to_country_check']) ) {
			return false;
		}

		$values = explode("|", $_COOKIE["icl_ip_to_country_check"]);

		if ( !isset($values[1]) ) {
			return false;
		}

		return $values[0];
	}

	function redirect($url = null, $client_lang = null) {

		if ( apply_filters( 'WPML_IPStack_Redirect_should_redirect', true, $url, $client_lang ) ) {
			wp_safe_redirect( apply_filters( 'WPML_IPStack_Redirect_redirect', $url, $client_lang ) );
		}
	}

	function redirect_ip_country() {
		global $wpdb, $post, $sitepress, $sitepress_settings;

		switch ($this->get_geolocation_provider()) {
			case self::GEO_PROVIDER_IPSTACK:
				if ( !$this->get_api_key() ) {
					return;
				}
				break;

			case self::GEO_PROVIDER_WC:
				if ( !class_exists('WC_Geolocation') ) {
					return;
				}
				break;

			default:
		}

		$args['skip_missing'] = intval($sitepress_settings['automatic_redirect'] == 1);
		$languages = apply_filters( 'wpml_active_languages', null, $args );
		$language_urls = array();
		foreach($languages as $language) {
			$language_urls[$language['language_code']] = array(
				'url' => $language['url'],
				'missing' => isset($language['missing'])?$language['missing']:true
			);
		}

		// echo '<pre>';
		// var_dump(get_site_url());
		// var_dump($language_urls);
		// var_dump($this->get_country_code_by_ip());
		// var_dump($sitepress->get_default_language());
		// die();

		/**
		 * No Cookies are set yet
		 */
		if ( !$this->is_check_cookie_valid() || !isset($_COOKIE['icl_ip_to_country_lang']) ) {

			// Grab the language code from ip e.g. es
			$lang_code_from_ip = $this->get_language_code_by_ip();
			if ( !$lang_code_from_ip ) {
				$lang_code_from_ip = $this->get_country_code_by_ip();
			}
			if ( !$lang_code_from_ip ) {
				$lang_code_from_ip = $sitepress->get_default_language();
			}

			// Get the current post, page language code
			// $_post = wpml_get_language_information( $post->ID );
			// $post_language_code = substr( $_post['locale'], 0, 2 );

			// If they are different redirect by language code from ip
			// if ($sitepress->get_current_language() != $lang_code_from_ip){
			// 	$url = $sitepress->convert_url(get_permalink($post->ID), $lang_code_from_ip);
			// }

			// Now lets set a browser cookie and do the redirect
			self::setCookie($lang_code_from_ip);

			// If language is set, redirect to that url
			if ( isset($language_urls[$lang_code_from_ip]) ) {
				if (!$language_urls[$lang_code_from_ip]['missing'] || ($language_urls[$lang_code_from_ip]['missing'] && $this->get_avoid_post_missing_redirect()!=='on')) {
					$this->redirect( $language_urls[$lang_code_from_ip]['url'], $lang_code_from_ip );
				}
				// post missing in user lang
			}

			// Language not found, redirect to default language
			else {
				$default_post_lang = $sitepress->get_default_language();
				if (isset($language_urls[$default_post_lang])) {
					if (!$language_urls[$default_post_lang]['missing'] || ($language_urls[$default_post_lang]['missing'] && $this->get_avoid_post_missing_redirect()!=='on')) {
						$this->redirect( $language_urls[$default_post_lang]['url'], $lang_code_from_ip );
					}
					// post missing in user lang
				}
			}
		}

		/**
		 * Force redirect the user to the right post.
		 * Make sure to redirect only if user is currently viewing the post in a different language then the one store in the cookie
		 */
		else if ( $this->force_redirect() && isset($_COOKIE['icl_ip_to_country_lang']) && !empty($_COOKIE['icl_ip_to_country_lang']) ) {
			$lang_code_from_ip = $_COOKIE['icl_ip_to_country_lang'];
			$post_language_code = ICL_LANGUAGE_CODE;

			$current_post_url = isset($language_urls[$post_language_code])?$language_urls[$post_language_code]['url']:false;
			$user_lang_post_url = isset($language_urls[$lang_code_from_ip])?$language_urls[$lang_code_from_ip]['url']:false;
			$user_lang_post_missing = isset($language_urls[$lang_code_from_ip])?$language_urls[$lang_code_from_ip]['missing']:true;

			// echo '<pre>';
			// var_dump($lang_code_from_ip);
			// var_dump($post_language_code);
			// var_dump($user_lang_post_url);
			// var_dump($language_urls);
			// die();

			/**
			 * If the current post language is different then the user language then redirect
			 */
			if ( $lang_code_from_ip != $post_language_code && $current_post_url != $user_lang_post_url ) {

				if (!$user_lang_post_missing || ($user_lang_post_missing && $this->get_avoid_post_missing_redirect()!=='on')) {
					$this->redirect( $user_lang_post_url, $lang_code_from_ip );
				}

				// post missing in user lang

			}
		}
	}
}

if ( function_exists('wpml_get_language_information') ) {
	$wpml_gr = new WPML_IPStack_Redirect();
}

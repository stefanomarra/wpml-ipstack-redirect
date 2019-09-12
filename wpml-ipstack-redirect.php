<?php
/*
Plugin Name: WPML IPStack Redirect
Plugin URI: https://www.stefanomarra.com
Description: Redirects users to their appropriate languages by utilizing third-party service ipstack.com
Version: 1.0
Author: Stefano Marra
Author URI: https://www.stefanomarra.com
License: GPL2
*/

class WPML_IPStack_Redirect
{

	private $client_geolocation_details = false;

	/**
	 * Initialize
	 */
	function __construct() {

		add_action('template_redirect', array( &$this, 'redirect_ip_country') );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
			add_action( 'admin_menu', array( $this, 'digest_post_data' ) );
		}
	}

	function digest_post_data(){

		if ( isset( $_POST['wpml_ipstack_redirect_api_key'] )  ) {

			$location = 'options-general.php?page=wpml_ipstack_redirect_settings';

			if( !wp_verify_nonce( $_POST['_wpnonce'], 'wpml_ipstack_redirect_update_action' )){
				$this->redirect_user( $location . '&feedback=form_submission_error' );
			}

			// Save in DB and redirect
			update_option( 'wpml_ipstack_redirect_api_key' , trim( $_POST['wpml_ipstack_redirect_api_key'] ) );

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

		include 'wpml-ipstack-redirect-admin-page.class.php';

		$admin_page = new WPML_IPStack_Redirect_Admin_Page( $api_key );
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

	function get_client_geolocation_details() {
		$ip = $this->get_client_ip();
		$api_key = $this->get_api_key();

		if ( $this->client_geolocation_details ) {
			return $this->client_geolocation_details;
		}

		$this->client_geolocation_details = json_decode(file_get_contents("http://api.ipstack.com/{$ip}?access_key={$api_key}&format=1"));

		return $this->client_geolocation_details;
	}

	function get_country_code_by_ip() {
		$details = $this->get_client_geolocation_details();

		return strtolower($details->country_code);
	}

	function get_language_code_by_ip() {
		$details = $this->get_client_geolocation_details();

		if ( !is_array($details->location->languages) ) {
			return false;
		}

		foreach ($details->location->languages as $lang) {
			return strtolower($lang->code);
		}

		return false;
	}

	function redirect_ip_country() {
		global $wpdb, $post, $sitepress, $sitepress_settings;

		if ( !$this->get_api_key() ) {
			return;
		}

		$args['skip_missing'] = intval($sitepress_settings['automatic_redirect'] == 1);
		$languages = apply_filters( 'wpml_active_languages', null, $args );
		$language_urls = array();
		foreach($languages as $language) {
			$language_urls[$language['language_code']] = $language['url'];
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
		if ( !isset($_COOKIE['icl_ip_to_country_check']) || !isset($_COOKIE['icl_ip_to_country_lang']) ) {

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
			setcookie('icl_ip_to_country_check','checked', time()+($sitepress_settings['remember_language']*HOUR_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
			setcookie('icl_ip_to_country_lang', $lang_code_from_ip, time()+($sitepress_settings['remember_language']*HOUR_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);

			// If language is set, redirect to that url
			if ( isset($language_urls[$lang_code_from_ip]) ) {
				wp_safe_redirect( $language_urls[$lang_code_from_ip] );
			}

			// Language not found, redirect to default language
			else {
				wp_safe_redirect( $language_urls[$sitepress->get_default_language()] );
			}
		}

		/**
		 * Force redirect the user to the right post.
		 * Make sure to redirect only if user is currently viewing the post in a different language then the one store in the cookie
		 */
		else if ( $this->force_redirect() && isset($_COOKIE['icl_ip_to_country_lang']) && !empty($_COOKIE['icl_ip_to_country_lang']) ) {
			$lang_code_from_ip = $_COOKIE['icl_ip_to_country_lang'];
			$post_language_code = ICL_LANGUAGE_CODE;

			$current_post_url = isset($language_urls[$post_language_code])?$language_urls[$post_language_code]:false;
			$user_lang_post_url = isset($language_urls[$lang_code_from_ip])?$language_urls[$lang_code_from_ip]:false;

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
				wp_safe_redirect( $user_lang_post_url );
			}
		}
	}
}

if ( function_exists('wpml_get_language_information') ) {
	$wpml_gr = new WPML_IPStack_Redirect();
}

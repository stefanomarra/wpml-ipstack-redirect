<?php
if( ! defined( 'ABSPATH' ) ) exit;

class WPML_IPStack_Redirect_Admin_Page
{
	var $api_key;
	var $default_language;
	var $geolocation_providers;
	var $avoid_post_missing_redirect;


	function __construct( $api_key, $geolocation_provider = null, $avoid_post_missing_redirect = 'no' ){
		$this->api_key = $api_key;
		$this->geolocation_provider = $geolocation_provider;
		$this->avoid_post_missing_redirect = $avoid_post_missing_redirect;

		$this->geolocation_providers = [
			'IPStack' => 'IPStack'
		];

		if ( class_exists('WC_Geolocation') ) {
			$this->geolocation_providers['WC_Geolocation'] = 'WooCommerce Geolocation';
		}
	}


	function display_wpml_ipstack_redirect_admin_page(){
		?>

		<div class="wrap">
			<h2>WPML IPStack Redirect</h2>

			<?php $this->display_feedback_admin_notices() ?>

			<form method="post" action="options-general.php?page=wpml_ipstack_redirect_settings" id="options_form" >
				<?php
				echo '<table class="form-table" >';

				if ( count(array_keys($this->geolocation_providers)) > 1 ) {

					echo "<tr>
							<th scope='row'>Geolocation Provider</th>
							<td>
								<select name='wpml_ipstack_redirect_geolocation_provider'  class='regular-text' style='width: 300px;'>";
								foreach (array_keys($this->geolocation_providers) as $key) {
									echo "<option value='{$key}' " . ($key==$this->geolocation_provider?'selected="selected"':'') . ">" . $this->geolocation_providers[$key] . "</option>";
								}
					echo "		</select>
							</td>
						</tr>";

				}

				echo "<tr>
						<th scope='row'>IPStack Api Key</th>
						<td>
							<input type='text' name='wpml_ipstack_redirect_api_key' value='{$this->api_key}' class='regular-text' style='width: 300px;' />
							<p class='description'>Signup on <a href='https://ipstack.com/' target='blank'>ipstack.com</a> to get you Api Key</p>
						</td>
					</tr>";

				echo "<tr>
						<th scope='row'>Avoid Post Missing Redirection</th>
						<td>
							<input type='checkbox' name='wpml_ipstack_redirect_avoid_post_missing_redirect' class='regular-text' " . ($this->avoid_post_missing_redirect==='on'?'checked="checked"':'') . " />
							<p class='description'>Avoid to redirect the user if the post is not available in the user country language. This will change the user default user language</p>
						</td>
					</tr>";
				echo "</table>";

				wp_nonce_field( 'wpml_ipstack_redirect_update_action' );
				submit_button();
				?>
			</form>
		</div>

		<?php
	}

	private function display_feedback_admin_notices(){

		if( isset($_GET['feedback'] ) ){

			$feedback = trim( $_GET['feedback'] );
			switch( $feedback ){

				case 'success':
					?>
					<div class="notice notice-success is-dismissible">
						<p>Updated.</p>
					</div>
					<?php
					break;

				case 'form_submission_error':
					?>
					<div class="notice notice-error is-dismissible">
						<p>There has been a form submission error.</p>
					</div>
					<?php
					break;
			}
		}
	}
}

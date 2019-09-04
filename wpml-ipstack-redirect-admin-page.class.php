<?php
if( ! defined( 'ABSPATH' ) ) exit;

class WPML_IPStack_Redirect_Admin_Page
{
	var $api_key;
	var $default_language;


	function __construct( $api_key ){
		$this->api_key = $api_key;
	}


	function display_wpml_ipstack_redirect_admin_page(){
		?>

		<div class="wrap">
			<h2>WPML IPStack Redirect</h2>

			<?php $this->display_feedback_admin_notices() ?>

			<form method="post" action="options-general.php?page=wpml_ipstack_redirect_settings" id="options_form" >
				<?php
				echo '<table class="form-table" >';
				echo "<tr>
						<th scope='row'>Api Key</th>
						<td>
							<input type='text' name='wpml_ipstack_redirect_api_key' value='{$this->api_key}' class='regular-text' style='width: 300px;' />
							<p class='description'>Signup on <a href='https://ipstack.com/' target='blank'>ipstack.com</a> to get you Api Key</p>
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

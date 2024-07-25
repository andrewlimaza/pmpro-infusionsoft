<?php 

	//Require API wrapper class in classes folder
	include_once( PMPROKEAP_DIR . '/classes/class-pmprokeap-api-wrapper.php' );

	/**
	 * Add the options page
	 *
	 * @return void
	 * @since TBD
	 *
	 */
	function pmprokeap_admin_add_page() {
		$keap_integration_menu_text = __( 'Keap', 'pmpro-keap' );
		add_submenu_page( 'pmpro-dashboard', $keap_integration_menu_text, $keap_integration_menu_text, 'manage_options',
			'pmpro-keap', 'pmprokeap_options_page' );
	}

	function pmprokeap_admin_bar_menu_add_page() {
		//Bail if can't manage options
		if( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wp_admin_bar;
		$keap_integration_menu_text = __( 'Keap', 'pmpro-keap' );
		$wp_admin_bar->add_menu( array(
			'id' => 'pmpro-keap',
			'title' => $keap_integration_menu_text,
			'href' => admin_url( 'admin.php?page=pmpro-keap' ),
			'parent' => 'paid-memberships-pro',
			'meta' => array( 'class' => 'pmpro-keap' )
		) );
	}

	add_action( 'admin_menu', 'pmprokeap_admin_add_page' );
	add_action( 'admin_bar_menu', 'pmprokeap_admin_bar_menu_add_page', 1500 );

	/**
	 * Get settings options for PMPro Keap and and render the markup to save the options
	 *
	 * @return array $options
	 */
	function pmprokeap_options_page() {
		require_once( PMPROKEAP_DIR . '/adminpages/settings.php' );
	
	}

	/**
	 * Register setting page for PMPro Keap
	 *
	 * @return void
	 * @since TBD
	 */
	function pmprokeap_admin_init() {
		//setup settings
		register_setting( 'pmprokeap_options', 'pmprokeap_options', 'pmprokeap_options_validate' );
		add_settings_section( 'pmprokeap_section_general', 'General Settings', 'pmprokeap_section_general', 'pmprokeap_options' );
		add_settings_field( 'pmprokeap_keap_authorized', 'Keap Authorized', 'pmprokeap_keap_authorized', 'pmprokeap_options', 'pmprokeap_section_general' );
		add_settings_field( 'pmprokeap_api_key', 'Keap API Key', 'pmprokeap_api_key', 'pmprokeap_options', 'pmprokeap_section_general' );
		add_settings_field( 'pmprokeap_api_secret', 'Keap Secret Key', 'pmprokeap_secret_key', 'pmprokeap_options', 'pmprokeap_section_general' );
		add_settings_field( 'pmprokeap_users_tags', 'All Users Tags', 'pmprokeap_users_tags', 'pmprokeap_options', 'pmprois_section_general' );
		if (  get_option( 'keap_access_token' ) ) {
			add_settings_section( 'pmprokeap_section_levels', 'Levels Tags', 'pmprokeap_section_levels', 'pmprokeap_options' );
		}
	
		if ( isset($_GET['action']) && $_GET['action'] == 'authorize_keap' ) {
			$keap = PMProKeap_Api_Wrapper::get_instance();
			$authUrl = $keap->pmprokeap_get_authorization_url();
			header( "Location: $authUrl" );
			exit;
		}

		// Handle the OAuth callback
		if ( isset( $_GET['page'] ) && $_GET['page'] == 'pmpro-keap' && isset( $_GET['code'] ) ) {
			$keap = PMProKeap_Api_Wrapper::get_instance();
			$authorization_code = $_GET['code'];
			$token_response = $keap->pmprokeap_request_token( $authorization_code );

			if (isset($token_response['access_token'])) {
				// Store the access token securely
				update_option('pmprokeap_access_token', $token_response['access_token']);
				update_option('pmprokeap_refresh_token', $tokenResponse['refresh_token']);

			} else {
				// Handle token request error
				echo '<div class="error"><p>Error requesting access token: ' . esc_html($token_response['error_description']) . '</p></div>';
			}

			// Redirect to the settings page after processing
			wp_redirect( admin_url( 'admin.php?page=pmpro-keap' ) );
			exit;
		}
	}

	add_action("admin_init", "pmprokeap_admin_init");



	/**
	 * Add the settings title section for the PMPro Keap options page
	 *
	 * @since TBD
	 */
	function pmprokeap_section_general() {
		?>
		<p><?php esc_html_e('Settings for the Keap Integration.', 'pmpro-keap');?></p>
		<?php
	}

	/**
	 * Add the API Key settings section for the PMPro Keap options page
	 * 
	 * @return void
	 * @since TBD
	 */
	function pmprokeap_api_key() {
		$options = get_option('pmprokeap_options');
		if( !empty($options['api_key'] ) ) {
			$api_key = $options['api_key'];
		} else {
			$api_key = "";
		}
		?>
		<input id='pmprokeap_api_key' name='pmprokeap_options[api_key]' size='80' type='text' value='<?php echo esc_attr( $api_key ) ?>' />
	<?php
	}

	/**
	 * Add the Secret Key settings section for the PMPro Keap options page
	 * 
	 * @return void
	 * @since TBD
	 */
	function pmprokeap_secret_key() {
		$options = get_option('pmprokeap_options');
		if(!empty($options['api_secret'])) {
			$api_secret = $options['api_secret'];
		} else {
			$api_secret = "";
		}
		?>
		<input id='pmprokeap_api_secret' name='pmprokeap_options[api_secret]' size='80' type='text' value='<?php echo esc_attr( $api_secret ) ?>' />
	<?php
	}

	/**
	 * Add the Users Tags settings section for the PMPro Keap options page
	 * 
	 * @return void
	 * @since TBD
	 */
	function pmprokeap_section_levels() {
		?>
		<p>
			<?php esc_html_e('For each level below, choose the tags which should be added 
			to the contact when a new user registers or switches levels.', 'pmpro-keap'); ?>
		</p>
		<table class="<?php echo esc_attr( 'form-table' ) ?>">
			<?php
				$levels = pmpro_getAllLevels( true, true );
				$all_tags = pmprokeap_get_tags();
				foreach( $levels as $level ) {
					$tags = pmprokeap_get_tags_for_level( $level->id );
			?>
					<tr>
						<th>
							<?php echo esc_html( $level->name );?>
						</th>
						<td>
							<?php
								if( empty( $all_tags ) ) {
									?>
									<p><?php esc_html_e( 'No tags found.', 'pmpro-keap' );?></p>
									<?php
								} else {
									?>
							<select name="pmprokeap_options[levels][<?php echo esc_attr( $level->id );?>][]" multiple="yes">
								<?php
									foreach( $all_tags as $tag ) {
								?>
										<option value="<?php echo esc_attr( $tag[ 'id' ] );?>" 
											<?php if( in_array( $tag[ 'id' ], $tags ) ) { ?>
													selected="selected"
												<?php } ?>>
											<?php echo esc_html( $tag [ 'name' ] );?>
										</option>
									<?php
									}
								?>
							</select>
							<?php
								}
							?>
						</td>
					</tr>

		<?php
		}
		?>
		</tbody>
		</table>
		<?php
	}
	/**
	 * Get the tags for a specific level
	 *
	 * @param int $level_id The level ID
	 * @return array The tags for the level
	 * @since TBD
	 */
	function pmprokeap_get_tags_for_level( $level_id ) {
		$options = get_option( 'pmprokeap_options' );
		if( !empty( $options[ 'levels' ][ $level_id ] ) ) {
			return $options[ 'levels' ][ $level_id ];
		} else {
			return array();
		}
	}

	/**
	 * Get all Keap tags
	 *
	 * @return array The tags.
	 * @since TBD 
	 */
	function pmprokeap_get_tags() {
		$keap = PMProKeap_Api_Wrapper::get_instance();
		$tags = $keap->pmprokeap_get_tags();
		//bail if no tags
		if( empty( $tags[ 'tags' ] ) ) {
			return array();
		}
		return $tags['tags'];
	}

	/**
	 * Show either or not the user is authorized with Keap
	 *
	 * @since TBD
	 */
	function pmprokeap_keap_authorized() {
		$accessToken = get_option( 'keap_access_token' );
		if ( $accessToken ) {
			?>
			<span class="<?php echo esc_attr( 'pmpro_tag pmpro_tag-has_icon pmpro_tag-active pmpro-keap-tag' ) ?>">
				<?php esc_html_e( 'Authorized', 'pmpro-keap' ); ?>
			</span>
			<?php
		return;
		} 
		?>
		<span class="<?php echo esc_attr( 'pmpro_tag pmpro_tag-has_icon pmpro_tag-inactive pmpro-keap-tag' ) ?>">
			<?php esc_html_e( 'Not Authorized', 'pmpro-keap' ); ?>
		</span>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=pmpro-keap&action=authorize_keap' ) ); ?>" class="button button-secondary">
			<?php esc_html_e( 'Authorize with Keap', 'pmpro-keap' ) ?>

		<?php
	}
?>

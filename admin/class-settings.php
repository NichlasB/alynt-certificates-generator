<?php
/**
 * Admin settings handler placeholder.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

use Alynt\CertificateGenerator\Admin\Render\Alynt_Certificate_Generator_Field_Renderer;
use Alynt\CertificateGenerator\Admin\Settings\Alynt_Certificate_Generator_Admin_Settings_Schema;
use Alynt\CertificateGenerator\Admin\Settings\Sanitize\Alynt_Certificate_Generator_Settings_Sanitizer;
use Alynt\CertificateGenerator\AdminUi\Tabs\Alynt_Certificate_Generator_Tab_Email;
use Alynt\CertificateGenerator\AdminUi\Tabs\Alynt_Certificate_Generator_Tab_Fonts;
use Alynt\CertificateGenerator\AdminUi\Tabs\Alynt_Certificate_Generator_Tab_General;
use Alynt\CertificateGenerator\AdminUi\Tabs\Alynt_Certificate_Generator_Tab_Logs;
use Alynt\CertificateGenerator\AdminUi\Tabs\Alynt_Certificate_Generator_Tab_Webhooks;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Font_Service;

class Alynt_Certificate_Generator_Settings {
	/**
	 * Settings schema.
	 *
	 * @var Alynt_Certificate_Generator_Admin_Settings_Schema
	 */
	private $schema;

	/**
	 * Settings sanitizer.
	 *
	 * @var Alynt_Certificate_Generator_Settings_Sanitizer
	 */
	private $sanitizer;

	public function __construct() {
		$this->schema    = new Alynt_Certificate_Generator_Admin_Settings_Schema();
		$this->sanitizer = new Alynt_Certificate_Generator_Settings_Sanitizer( $this->schema );
	}

	/**
	 * Register settings hooks.
	 */
	public function register(): void {
		\register_setting(
			'alynt_certificate_generator_settings_group',
			ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->schema->get_defaults(),
			)
		);

		foreach ( $this->get_tabs() as $tab ) {
			$section_id = $this->get_section_id( $tab->get_id() );
			$page_slug  = $this->get_page_slug( $tab->get_id() );

			\add_settings_section(
				$section_id,
				$tab->get_title(),
				array( $this, 'render_section' ),
				$page_slug,
				array(
					'tab_id'      => $tab->get_id(),
					'description' => $tab->get_description(),
				)
			);

			foreach ( $this->schema->get_fields_for_tab( $tab->get_id() ) as $key => $field ) {
				\add_settings_field(
					$key,
					$field['label'] ?? '',
					array( $this, 'render_field' ),
					$page_slug,
					$section_id,
					array(
						'key'   => $key,
						'field' => $field,
					)
				);
			}
		}
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			\wp_die( esc_html__( 'You do not have permission to access this page.', 'alynt-certificate-generator' ) );
		}

		$tabs       = $this->get_tabs();
		$active_tab = $this->get_active_tab();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Alynt Certificate Generator', 'alynt-certificate-generator' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $tab ) {
			$is_active = $tab->get_id() === $active_tab;
			$url       = add_query_arg(
				array(
					'page' => 'alynt-certificate-generator',
					'tab'  => $tab->get_id(),
				),
				admin_url( 'admin.php' )
			);
			printf(
				'<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
				esc_url( $url ),
				$is_active ? 'nav-tab-active' : '',
				esc_html( $tab->get_title() )
			);
		}
		echo '</nav>';

		$description = $this->get_tab_description( $active_tab );
		if ( '' !== $description ) {
			echo '<p>' . esc_html( $description ) . '</p>';
		}

		if ( 'email' === $active_tab && ! $this->has_smtp_plugin() ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__(
				'No SMTP plugin detected. For reliable delivery, install and configure an SMTP plugin.',
				'alynt-certificate-generator'
			);
			echo '</p></div>';
		}

		// Fonts tab has custom UI, not standard settings fields.
		if ( 'fonts' === $active_tab ) {
			$this->render_fonts_page();
		} else {
			echo '<form method="post" action="options.php">';
			settings_fields( 'alynt_certificate_generator_settings_group' );
			echo '<input type="hidden" name="alynt_active_tab" value="' . esc_attr( $active_tab ) . '" />';
			\do_settings_sections( $this->get_page_slug( $active_tab ) );
			submit_button();
			echo '</form>';
		}
		echo '</div>';
	}

	/**
	 * Render section description.
	 *
	 * @param array $args Section args.
	 */
	public function render_section( array $args ): void {
		if ( empty( $args['description'] ) ) {
			return;
		}

		echo '<p>' . esc_html( (string) $args['description'] ) . '</p>';
	}

	/**
	 * Render a field.
	 *
	 * @param array $args Field args.
	 */
	public function render_field( array $args ): void {
		$key   = $args['key'] ?? '';
		$field = $args['field'] ?? array();

		if ( '' === $key ) {
			return;
		}

		$options = $this->get_settings();
		$value   = $options[ $key ] ?? ( $field['default'] ?? '' );

		Alynt_Certificate_Generator_Field_Renderer::render_field(
			ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION,
			$key,
			$field,
			$value
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( array $input ): array {
		$current    = $this->get_settings();
		$active_tab = $this->get_active_tab( true );

		if ( ! $this->schema->is_valid_tab( $active_tab ) ) {
			$active_tab = 'general';
		}

		return $this->sanitizer->sanitize_for_tab( $input, $current, $active_tab );
	}

	/**
	 * Get current settings.
	 *
	 * @return array
	 */
	private function get_settings(): array {
		$settings = \get_option( ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION, array() );
		if ( ! is_array( $settings ) ) {
			return array();
		}

		return $settings;
	}

	/**
	 * Get active tab.
	 *
	 * @param bool $from_post Check POST first.
	 * @return string
	 */
	private function get_active_tab( bool $from_post = false ): string {
		if ( $from_post && isset( $_POST['alynt_active_tab'] ) ) {
			return sanitize_key( wp_unslash( $_POST['alynt_active_tab'] ) );
		}

		if ( isset( $_GET['tab'] ) ) {
			return sanitize_key( wp_unslash( $_GET['tab'] ) );
		}

		return 'general';
	}

	/**
	 * Get tab description.
	 *
	 * @param string $tab_id Tab ID.
	 * @return string
	 */
	private function get_tab_description( string $tab_id ): string {
		foreach ( $this->get_tabs() as $tab ) {
			if ( $tab->get_id() === $tab_id ) {
				return $tab->get_description();
			}
		}

		return '';
	}

	/**
	 * Get tab list.
	 *
	 * @return array
	 */
	private function get_tabs(): array {
		return array(
			new Alynt_Certificate_Generator_Tab_General(),
			new Alynt_Certificate_Generator_Tab_Fonts(),
			new Alynt_Certificate_Generator_Tab_Webhooks(),
			new Alynt_Certificate_Generator_Tab_Email(),
			new Alynt_Certificate_Generator_Tab_Logs(),
		);
	}

	/**
	 * Build section ID for a tab.
	 *
	 * @param string $tab Tab ID.
	 * @return string
	 */
	private function get_section_id( string $tab ): string {
		return 'alynt_certificate_generator_section_' . $tab;
	}

	/**
	 * Build page slug for a tab.
	 *
	 * @param string $tab Tab ID.
	 * @return string
	 */
	private function get_page_slug( string $tab ): string {
		return 'alynt_certificate_generator_settings_' . $tab;
	}

	/**
	 * Render the fonts management page.
	 */
	private function render_fonts_page(): void {
		$font_service = new Alynt_Certificate_Generator_Font_Service();
		$fonts = $font_service->get_global_fonts();
		$allowed_weights = Alynt_Certificate_Generator_Font_Service::ALLOWED_WEIGHTS;

		// Handle form submissions.
		if ( isset( $_POST['acg_font_action'] ) && check_admin_referer( 'acg_font_management', 'acg_font_nonce' ) ) {
			$action = sanitize_key( wp_unslash( $_POST['acg_font_action'] ) );

			if ( 'create_family' === $action && isset( $_POST['acg_font_family_name'] ) ) {
				$family_name = sanitize_text_field( wp_unslash( $_POST['acg_font_family_name'] ) );
				$result = $font_service->create_font_family( $family_name );
				if ( is_wp_error( $result ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
				} else {
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Font family created successfully.', 'alynt-certificate-generator' ) . '</p></div>';
					$fonts = $font_service->get_global_fonts();
				}
			}

			if ( 'upload_weight' === $action && isset( $_POST['acg_font_family_slug'], $_POST['acg_font_weight'], $_FILES['acg_font_file'] ) ) {
				$family_slug = sanitize_key( wp_unslash( $_POST['acg_font_family_slug'] ) );
				$weight = sanitize_key( wp_unslash( $_POST['acg_font_weight'] ) );

				if ( isset( $fonts[ $family_slug ] ) && ! empty( $_FILES['acg_font_file']['tmp_name'] ) ) {
					$result = $font_service->upload_font(
						$_FILES['acg_font_file'],
						$fonts[ $family_slug ]['family'],
						$weight
					);
					if ( is_wp_error( $result ) ) {
						echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
					} else {
						echo '<div class="notice notice-success"><p>' . esc_html__( 'Font weight uploaded successfully.', 'alynt-certificate-generator' ) . '</p></div>';
						$fonts = $font_service->get_global_fonts();
					}
				}
			}

			if ( 'delete_weight' === $action && isset( $_POST['acg_font_family_slug'], $_POST['acg_font_weight'] ) ) {
				$family_slug = sanitize_key( wp_unslash( $_POST['acg_font_family_slug'] ) );
				$weight = sanitize_key( wp_unslash( $_POST['acg_font_weight'] ) );
				$result = $font_service->delete_font_weight( $family_slug, $weight );
				if ( is_wp_error( $result ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
				} else {
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Font weight deleted successfully.', 'alynt-certificate-generator' ) . '</p></div>';
					$fonts = $font_service->get_global_fonts();
				}
			}

			if ( 'delete_family' === $action && isset( $_POST['acg_font_family_slug'] ) ) {
				$family_slug = sanitize_key( wp_unslash( $_POST['acg_font_family_slug'] ) );
				$result = $font_service->delete_font_family( $family_slug );
				if ( is_wp_error( $result ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
				} else {
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Font family deleted successfully.', 'alynt-certificate-generator' ) . '</p></div>';
					$fonts = $font_service->get_global_fonts();
				}
			}
		}

		?>
		<div class="acg-fonts-manager">
			<h2><?php esc_html_e( 'Custom Fonts', 'alynt-certificate-generator' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Upload custom TTF or OTF fonts to use in your certificate templates. Download fonts from Google Fonts or other sources.', 'alynt-certificate-generator' ); ?></p>

			<!-- Create New Font Family -->
			<div class="acg-font-add-family" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
				<h3><?php esc_html_e( 'Add New Font Family', 'alynt-certificate-generator' ); ?></h3>
				<form method="post" action="">
					<?php wp_nonce_field( 'acg_font_management', 'acg_font_nonce' ); ?>
					<input type="hidden" name="acg_font_action" value="create_family" />
					<p>
						<label for="acg_font_family_name"><?php esc_html_e( 'Font Family Name:', 'alynt-certificate-generator' ); ?></label><br />
						<input type="text" name="acg_font_family_name" id="acg_font_family_name" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., Roboto, Open Sans', 'alynt-certificate-generator' ); ?>" required />
					</p>
					<p>
						<?php submit_button( __( 'Create Font Family', 'alynt-certificate-generator' ), 'secondary', 'submit', false ); ?>
					</p>
				</form>
			</div>

			<!-- Existing Font Families -->
			<?php if ( empty( $fonts ) ) : ?>
				<p><?php esc_html_e( 'No custom fonts have been uploaded yet. Create a font family above to get started.', 'alynt-certificate-generator' ); ?></p>
			<?php else : ?>
				<?php foreach ( $fonts as $family_slug => $family_data ) : ?>
					<div class="acg-font-family" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
						<h3>
							<?php echo esc_html( $family_data['family'] ); ?>
							<span style="font-weight: normal; font-size: 12px; color: #666;">
								(<?php echo esc_html( $family_slug ); ?>)
							</span>
						</h3>

						<!-- Font Weights Table -->
						<table class="widefat striped" style="margin-bottom: 15px;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Weight', 'alynt-certificate-generator' ); ?></th>
									<th><?php esc_html_e( 'Status', 'alynt-certificate-generator' ); ?></th>
									<th><?php esc_html_e( 'Preview', 'alynt-certificate-generator' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'alynt-certificate-generator' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $allowed_weights as $weight_key => $weight_label ) : ?>
									<?php $has_weight = isset( $family_data['weights'][ $weight_key ] ); ?>
									<tr>
										<td><strong><?php echo esc_html( $weight_label ); ?></strong></td>
										<td>
											<?php if ( $has_weight ) : ?>
												<span style="color: green;">&#10004; <?php esc_html_e( 'Uploaded', 'alynt-certificate-generator' ); ?></span>
											<?php else : ?>
												<span style="color: #999;">&#8212; <?php esc_html_e( 'Not uploaded', 'alynt-certificate-generator' ); ?></span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ( $has_weight ) : ?>
												<span style="font-family: '<?php echo esc_attr( $family_data['family'] ); ?>', sans-serif;">
													<?php esc_html_e( 'The quick brown fox jumps over the lazy dog', 'alynt-certificate-generator' ); ?>
												</span>
											<?php else : ?>
												&mdash;
											<?php endif; ?>
										</td>
										<td>
											<?php if ( $has_weight ) : ?>
												<form method="post" action="" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this font weight?', 'alynt-certificate-generator' ); ?>');">
													<?php wp_nonce_field( 'acg_font_management', 'acg_font_nonce' ); ?>
													<input type="hidden" name="acg_font_action" value="delete_weight" />
													<input type="hidden" name="acg_font_family_slug" value="<?php echo esc_attr( $family_slug ); ?>" />
													<input type="hidden" name="acg_font_weight" value="<?php echo esc_attr( $weight_key ); ?>" />
													<button type="submit" class="button button-small" style="color: #a00;">
														<?php esc_html_e( 'Delete', 'alynt-certificate-generator' ); ?>
													</button>
												</form>
											<?php else : ?>
												<form method="post" action="" enctype="multipart/form-data" style="display: inline-flex; align-items: center; gap: 8px;">
													<?php wp_nonce_field( 'acg_font_management', 'acg_font_nonce' ); ?>
													<input type="hidden" name="acg_font_action" value="upload_weight" />
													<input type="hidden" name="acg_font_family_slug" value="<?php echo esc_attr( $family_slug ); ?>" />
													<input type="hidden" name="acg_font_weight" value="<?php echo esc_attr( $weight_key ); ?>" />
													<input type="file" name="acg_font_file" accept=".ttf,.otf" required style="width: 200px;" />
													<button type="submit" class="button button-small">
														<?php esc_html_e( 'Upload', 'alynt-certificate-generator' ); ?>
													</button>
												</form>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<!-- Delete Family -->
						<form method="post" action="" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this entire font family and all its weights?', 'alynt-certificate-generator' ); ?>');">
							<?php wp_nonce_field( 'acg_font_management', 'acg_font_nonce' ); ?>
							<input type="hidden" name="acg_font_action" value="delete_family" />
							<input type="hidden" name="acg_font_family_slug" value="<?php echo esc_attr( $family_slug ); ?>" />
							<button type="submit" class="button" style="color: #a00;">
								<?php esc_html_e( 'Delete Entire Font Family', 'alynt-certificate-generator' ); ?>
							</button>
						</form>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<!-- Help Text -->
			<div class="acg-font-help" style="margin-top: 30px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
				<h4><?php esc_html_e( 'How to Use Custom Fonts', 'alynt-certificate-generator' ); ?></h4>
				<ol>
					<li><?php esc_html_e( 'Visit Google Fonts (fonts.google.com) and find a font you like.', 'alynt-certificate-generator' ); ?></li>
					<li><?php esc_html_e( 'Click "Download family" to get the font files (TTF format).', 'alynt-certificate-generator' ); ?></li>
					<li><?php esc_html_e( 'Create a font family above with the same name (e.g., "Roboto").', 'alynt-certificate-generator' ); ?></li>
					<li><?php esc_html_e( 'Upload each weight variant (Regular, Bold, Italic, Bold Italic) as needed.', 'alynt-certificate-generator' ); ?></li>
					<li><?php esc_html_e( 'The font will now appear in the template builder font dropdown.', 'alynt-certificate-generator' ); ?></li>
				</ol>
				<p><strong><?php esc_html_e( 'Note:', 'alynt-certificate-generator' ); ?></strong> <?php esc_html_e( 'Each font weight is a separate file. For example, Roboto-Regular.ttf, Roboto-Bold.ttf, etc.', 'alynt-certificate-generator' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Detect common SMTP plugins.
	 *
	 * @return bool
	 */
	private function has_smtp_plugin(): bool {
		$known = array(
			'wp-mail-smtp/wp_mail_smtp.php',
			'easy-wp-smtp/easy-wp-smtp.php',
			'post-smtp/postman-smtp.php',
			'wp-smtp/wp-smtp.php',
		);

		$active_plugins = (array) \get_option( 'active_plugins', array() );
		$network_active = (array) \get_site_option( 'active_sitewide_plugins', array() );

		foreach ( $known as $plugin_file ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return true;
			}
			if ( isset( $network_active[ $plugin_file ] ) ) {
				return true;
			}
		}

		return false;
	}
}

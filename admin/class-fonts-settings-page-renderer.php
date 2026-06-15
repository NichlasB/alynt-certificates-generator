<?php
/**
 * Fonts settings page markup renderer.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the custom font settings page markup.
 */
class Alynt_Certificate_Generator_Fonts_Settings_Page_Renderer {
	/**
	 * Render an admin notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type.
	 */
	public function render_notice( string $message, string $type ): void {
		$role = 'error' === $type ? 'alert' : 'status';
		printf(
			'<div class="notice notice-%1$s is-dismissible" role="%2$s" aria-live="polite"><p>%3$s</p></div>',
			esc_attr( $type ),
			esc_attr( $role ),
			esc_html( $message )
		);
	}

	/**
	 * Render the fonts page markup.
	 *
	 * @param array $fonts           Registered fonts.
	 * @param array $allowed_weights Allowed font weights.
	 */
	public function render( array $fonts, array $allowed_weights ): void {
		?>
		<div class="acg-fonts-manager">
			<h2><?php esc_html_e( 'Custom Fonts', 'alynt-certificate-generator' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Upload custom TTF or OTF fonts to use in your certificate templates. Download fonts from Google Fonts or other sources.', 'alynt-certificate-generator' ); ?></p>

			<?php $this->render_create_family_form(); ?>

			<?php if ( empty( $fonts ) ) : ?>
				<div class="acg-empty-state">
					<h3><?php esc_html_e( 'No custom fonts yet', 'alynt-certificate-generator' ); ?></h3>
					<p><?php esc_html_e( 'Create a font family above, then upload each font weight you need for certificate templates.', 'alynt-certificate-generator' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $fonts as $family_slug => $family_data ) : ?>
					<?php $this->render_font_family( (string) $family_slug, $family_data, $allowed_weights ); ?>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php $this->render_help_text(); ?>
		</div>
		<?php
	}

	/**
	 * Render the create-family form.
	 */
	private function render_create_family_form(): void {
		?>
		<div class="acg-font-add-family" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
			<h3><?php esc_html_e( 'Add New Font Family', 'alynt-certificate-generator' ); ?></h3>
			<form method="post" action="">
				<?php wp_nonce_field( 'acg_font_management', 'acg_font_nonce' ); ?>
				<input type="hidden" name="acg_font_action" value="create_family" />
				<p>
					<label for="acg_font_family_name"><?php esc_html_e( 'Font Family Name', 'alynt-certificate-generator' ); ?></label><br />
					<input type="text" name="acg_font_family_name" id="acg_font_family_name" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., Roboto, Open Sans', 'alynt-certificate-generator' ); ?>" required aria-required="true" />
				</p>
				<p>
					<?php submit_button( __( 'Create Font Family', 'alynt-certificate-generator' ), 'secondary', 'submit', false ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render one font family panel.
	 *
	 * @param string $family_slug     Font family slug.
	 * @param array  $family_data     Font family data.
	 * @param array  $allowed_weights Allowed font weights.
	 */
	private function render_font_family( string $family_slug, array $family_data, array $allowed_weights ): void {
		?>
		<div class="acg-font-family" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
			<h3>
				<?php echo esc_html( $family_data['family'] ); ?>
				<span style="font-weight: normal; font-size: 12px; color: #666;">
					(<?php echo esc_html( $family_slug ); ?>)
				</span>
			</h3>

			<table class="widefat striped" style="margin-bottom: 15px;">
				<caption class="screen-reader-text">
				<?php
				echo esc_html(
					sprintf(
					/* translators: %s: font family name. */
						__( 'Font weights for %s', 'alynt-certificate-generator' ),
						(string) $family_data['family']
					)
				);
				?>
				</caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Weight', 'alynt-certificate-generator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'alynt-certificate-generator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Preview', 'alynt-certificate-generator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'alynt-certificate-generator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $allowed_weights as $weight_key => $weight_label ) : ?>
						<?php $this->render_font_weight_row( $family_slug, $family_data, (string) $weight_key, (string) $weight_label ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php $this->render_delete_family_form( $family_slug ); ?>
		</div>
		<?php
	}

	/**
	 * Render one font weight row.
	 *
	 * @param string $family_slug  Font family slug.
	 * @param array  $family_data  Font family data.
	 * @param string $weight_key   Font weight key.
	 * @param string $weight_label Font weight label.
	 */
	private function render_font_weight_row( string $family_slug, array $family_data, string $weight_key, string $weight_label ): void {
		$has_weight = isset( $family_data['weights'][ $weight_key ] );
		?>
		<tr>
			<th scope="row"><strong><?php echo esc_html( $weight_label ); ?></strong></th>
			<td><?php $this->render_weight_status( $has_weight ); ?></td>
			<td><?php $this->render_weight_preview( $has_weight, $family_data ); ?></td>
			<td>
				<?php
				if ( $has_weight ) {
					$this->render_delete_weight_form( $family_slug, $weight_key );
				} else {
					$this->render_upload_weight_form( $family_slug, $weight_key );
				}
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render a font weight status.
	 *
	 * @param bool $has_weight Whether the weight is uploaded.
	 */
	private function render_weight_status( bool $has_weight ): void {
		if ( $has_weight ) {
			?>
			<span style="color: green;">&#10004; <?php esc_html_e( 'Uploaded', 'alynt-certificate-generator' ); ?></span>
			<?php
			return;
		}
		?>
		<span style="color: #999;">&#8212; <?php esc_html_e( 'Not uploaded', 'alynt-certificate-generator' ); ?></span>
		<?php
	}

	/**
	 * Render a font weight preview.
	 *
	 * @param bool  $has_weight  Whether the weight is uploaded.
	 * @param array $family_data Font family data.
	 */
	private function render_weight_preview( bool $has_weight, array $family_data ): void {
		if ( ! $has_weight ) {
			echo '&mdash;';
			return;
		}
		?>
		<span style="font-family: '<?php echo esc_attr( $family_data['family'] ); ?>', sans-serif;">
			<?php esc_html_e( 'The quick brown fox jumps over the lazy dog', 'alynt-certificate-generator' ); ?>
		</span>
		<?php
	}

	/**
	 * Render the delete-weight form.
	 *
	 * @param string $family_slug Font family slug.
	 * @param string $weight_key  Font weight key.
	 */
	private function render_delete_weight_form( string $family_slug, string $weight_key ): void {
		$confirm_message = sprintf(
			/* translators: 1: font family slug, 2: font weight key. */
			__( 'Delete the %1$s %2$s font weight? This action cannot be undone.', 'alynt-certificate-generator' ),
			$family_slug,
			$weight_key
		);
		?>
		<form method="post" action="" style="display: inline;" onsubmit="return confirm('<?php echo esc_js( $confirm_message ); ?>');">
			<?php wp_nonce_field( 'acg_font_management', 'acg_font_nonce' ); ?>
			<input type="hidden" name="acg_font_action" value="delete_weight" />
			<input type="hidden" name="acg_font_family_slug" value="<?php echo esc_attr( $family_slug ); ?>" />
			<input type="hidden" name="acg_font_weight" value="<?php echo esc_attr( $weight_key ); ?>" />
			<button type="submit" class="button-link button-link-delete">
				<?php esc_html_e( 'Delete', 'alynt-certificate-generator' ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * Render the upload-weight form.
	 *
	 * @param string $family_slug Font family slug.
	 * @param string $weight_key  Font weight key.
	 */
	private function render_upload_weight_form( string $family_slug, string $weight_key ): void {
		?>
		<form method="post" action="" enctype="multipart/form-data" style="display: inline-flex; align-items: center; gap: 8px;">
			<?php wp_nonce_field( 'acg_font_management', 'acg_font_nonce' ); ?>
			<input type="hidden" name="acg_font_action" value="upload_weight" />
			<input type="hidden" name="acg_font_family_slug" value="<?php echo esc_attr( $family_slug ); ?>" />
			<input type="hidden" name="acg_font_weight" value="<?php echo esc_attr( $weight_key ); ?>" />
			<label class="screen-reader-text" for="<?php echo esc_attr( 'acg_font_file_' . $family_slug . '_' . $weight_key ); ?>">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: font family slug, 2: font weight key. */
						__( 'Font file for %1$s %2$s', 'alynt-certificate-generator' ),
						$family_slug,
						$weight_key
					)
				);
				?>
			</label>
			<input type="file" id="<?php echo esc_attr( 'acg_font_file_' . $family_slug . '_' . $weight_key ); ?>" name="acg_font_file" accept=".ttf,.otf" required aria-required="true" style="width: 200px;" />
			<button type="submit" class="button button-small">
				<?php esc_html_e( 'Upload', 'alynt-certificate-generator' ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * Render the delete-family form.
	 *
	 * @param string $family_slug Font family slug.
	 */
	private function render_delete_family_form( string $family_slug ): void {
		$confirm_message = sprintf(
			/* translators: %s: font family slug. */
			__( 'Delete the %s font family and all of its weights? This action cannot be undone.', 'alynt-certificate-generator' ),
			$family_slug
		);
		?>
		<form method="post" action="" style="display: inline;" onsubmit="return confirm('<?php echo esc_js( $confirm_message ); ?>');">
			<?php wp_nonce_field( 'acg_font_management', 'acg_font_nonce' ); ?>
			<input type="hidden" name="acg_font_action" value="delete_family" />
			<input type="hidden" name="acg_font_family_slug" value="<?php echo esc_attr( $family_slug ); ?>" />
			<button type="submit" class="button-link button-link-delete">
				<?php esc_html_e( 'Delete Entire Font Family', 'alynt-certificate-generator' ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * Render the fonts help text.
	 */
	private function render_help_text(): void {
		?>
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
		<?php
	}
}

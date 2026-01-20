<?php
/**
 * Email template admin UI.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

class Alynt_Certificate_Generator_Email_Template_Admin {
	/**
	 * Register metaboxes for email templates.
	 */
	public function register_metaboxes(): void {
		\add_meta_box(
			'acg_email_template_settings',
			__( 'Email Template Settings', 'alynt-certificate-generator' ),
			array( $this, 'render_metabox' ),
			'acg_email_template',
			'normal',
			'high'
		);
	}

	/**
	 * Render email template settings.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_metabox( \WP_Post $post ): void {
		\wp_nonce_field( 'acg_email_template_save', 'acg_email_template_nonce' );

		$template_id = (int) \get_post_meta( $post->ID, 'acg_email_template_id', true );
		$enabled     = (bool) \get_post_meta( $post->ID, 'acg_email_enabled', true );
		$to          = (string) \get_post_meta( $post->ID, 'acg_email_to', true );
		$subject     = (string) \get_post_meta( $post->ID, 'acg_email_subject', true );
		$body        = (string) \get_post_meta( $post->ID, 'acg_email_body', true );
		$attach_pdf  = (bool) \get_post_meta( $post->ID, 'acg_email_attach_pdf', true );

		$templates = \get_posts(
			array(
				'post_type'      => 'acg_certificate_template',
				'post_status'    => 'any',
				'numberposts'    => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'cache_results'  => false,
				'suppress_filters' => true,
			)
		);

		echo '<p><label for="acg_email_template_id">' . esc_html__( 'Certificate Template', 'alynt-certificate-generator' ) . '</label></p>';
		echo '<select name="acg_email_template_id" id="acg_email_template_id">';
		echo '<option value="">' . esc_html__( 'Select a template', 'alynt-certificate-generator' ) . '</option>';
		foreach ( $templates as $template_post_id ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $template_post_id ),
				selected( $template_id, $template_post_id, false ),
				esc_html( \get_the_title( $template_post_id ) )
			);
		}
		echo '</select>';

		echo '<p style="margin-top:12px;">';
		echo '<label><input type="checkbox" name="acg_email_enabled" value="1" ' . checked( $enabled, true, false ) . ' /> ';
		echo esc_html__( 'Enable this email template', 'alynt-certificate-generator' ) . '</label>';
		echo '</p>';

		echo '<p><label for="acg_email_to">' . esc_html__( 'To (supports variables like {email})', 'alynt-certificate-generator' ) . '</label></p>';
		echo '<input type="text" class="widefat" name="acg_email_to" id="acg_email_to" value="' . esc_attr( $to ) . '" />';

		echo '<p><label for="acg_email_subject">' . esc_html__( 'Subject', 'alynt-certificate-generator' ) . '</label></p>';
		echo '<input type="text" class="widefat" name="acg_email_subject" id="acg_email_subject" value="' . esc_attr( $subject ) . '" />';

		echo '<p><label for="acg_email_body">' . esc_html__( 'Body', 'alynt-certificate-generator' ) . '</label></p>';
		\wp_editor(
			$body,
			'acg_email_body',
			array(
				'textarea_name' => 'acg_email_body',
				'textarea_rows' => 10,
				'media_buttons' => false,
			)
		);

		echo '<p style="margin-top:12px;">';
		echo '<label><input type="checkbox" name="acg_email_attach_pdf" value="1" ' . checked( $attach_pdf, true, false ) . ' /> ';
		echo esc_html__( 'Attach generated PDF', 'alynt-certificate-generator' ) . '</label>';
		echo '</p>';
	}

	/**
	 * Save email template meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_meta( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['acg_email_template_nonce'] ) || ! \wp_verify_nonce( wp_unslash( $_POST['acg_email_template_nonce'] ), 'acg_email_template_save' ) ) {
			return;
		}

		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE, $post_id ) ) {
			return;
		}

		$template_id = isset( $_POST['acg_email_template_id'] ) ? absint( wp_unslash( $_POST['acg_email_template_id'] ) ) : 0;
		$enabled     = isset( $_POST['acg_email_enabled'] );
		$to          = isset( $_POST['acg_email_to'] ) ? sanitize_text_field( wp_unslash( $_POST['acg_email_to'] ) ) : '';
		$subject     = isset( $_POST['acg_email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['acg_email_subject'] ) ) : '';
		$body        = isset( $_POST['acg_email_body'] ) ? wp_kses_post( wp_unslash( $_POST['acg_email_body'] ) ) : '';
		$attach_pdf  = isset( $_POST['acg_email_attach_pdf'] );

		\update_post_meta( $post_id, 'acg_email_template_id', $template_id );
		\update_post_meta( $post_id, 'acg_email_enabled', $enabled );
		\update_post_meta( $post_id, 'acg_email_to', $to );
		\update_post_meta( $post_id, 'acg_email_subject', $subject );
		\update_post_meta( $post_id, 'acg_email_body', $body );
		\update_post_meta( $post_id, 'acg_email_attach_pdf', $attach_pdf );
	}
}

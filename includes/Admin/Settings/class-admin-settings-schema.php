<?php
/**
 * Settings schema definitions.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Admin\Settings;

class Alynt_Certificate_Generator_Admin_Settings_Schema {
	/**
	 * Return schema array.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_schema(): array {
		return array(
			'pdf_storage_path' => array(
				'tab'         => 'general',
				'type'        => 'text',
				'label'       => __( 'PDF Storage Path', 'alynt-certificate-generator' ),
				'description' => __( 'Custom upload subdirectory or leave blank for default.', 'alynt-certificate-generator' ),
				'default'     => '',
			),
			'default_date_format' => array(
				'tab'         => 'general',
				'type'        => 'select',
				'label'       => __( 'Default Date Format', 'alynt-certificate-generator' ),
				'description' => __( 'Used for date fields and generation dates.', 'alynt-certificate-generator' ),
				'default'     => 'Y-m-d',
				'options'     => array(
					'Y-m-d'  => __( 'YYYY-MM-DD', 'alynt-certificate-generator' ),
					'm/d/Y'  => __( 'MM/DD/YYYY', 'alynt-certificate-generator' ),
					'd/m/Y'  => __( 'DD/MM/YYYY', 'alynt-certificate-generator' ),
					'F d, Y' => __( 'Month DD, YYYY', 'alynt-certificate-generator' ),
					'd F Y'  => __( 'DD Month YYYY', 'alynt-certificate-generator' ),
				),
			),
			'certificate_id_prefix' => array(
				'tab'         => 'general',
				'type'        => 'text',
				'label'       => __( 'Certificate ID Prefix', 'alynt-certificate-generator' ),
				'description' => __( 'Prefix applied to generated certificate IDs.', 'alynt-certificate-generator' ),
				'default'     => 'ACG-',
			),
			'certificate_id_format' => array(
				'tab'         => 'general',
				'type'        => 'text',
				'label'       => __( 'Certificate ID Format', 'alynt-certificate-generator' ),
				'description' => __( 'Use {prefix} and {id} placeholders.', 'alynt-certificate-generator' ),
				'default'     => '{prefix}{id}',
			),
			'delete_data_on_uninstall' => array(
				'tab'         => 'general',
				'type'        => 'checkbox',
				'label'       => __( 'Delete data on uninstall', 'alynt-certificate-generator' ),
				'description' => __( 'Remove templates and logs when the plugin is uninstalled.', 'alynt-certificate-generator' ),
				'default'     => false,
			),
			'delete_files_on_uninstall' => array(
				'tab'         => 'general',
				'type'        => 'checkbox',
				'label'       => __( 'Delete certificate files on uninstall', 'alynt-certificate-generator' ),
				'description' => __( 'Remove generated PDFs when the plugin is uninstalled.', 'alynt-certificate-generator' ),
				'default'     => false,
			),
			'webhook_rate_limit_per_minute' => array(
				'tab'         => 'webhooks',
				'type'        => 'number',
				'label'       => __( 'Rate Limit (per minute)', 'alynt-certificate-generator' ),
				'description' => __( 'Maximum incoming webhook requests per minute.', 'alynt-certificate-generator' ),
				'default'     => 100,
				'min'         => 1,
			),
			'webhook_retry_schedule' => array(
				'tab'         => 'webhooks',
				'type'        => 'text',
				'label'       => __( 'Retry Schedule (seconds)', 'alynt-certificate-generator' ),
				'description' => __( 'Comma-separated retry delays, e.g. 60,300,1800,7200.', 'alynt-certificate-generator' ),
				'default'     => '60,300,1800,7200',
			),
			'webhook_signature_secret' => array(
				'tab'         => 'webhooks',
				'type'        => 'text',
				'label'       => __( 'Signature Secret', 'alynt-certificate-generator' ),
				'description' => __( 'Secret used for HMAC verification. Leave blank to disable.', 'alynt-certificate-generator' ),
				'default'     => '',
			),
			'email_from_name' => array(
				'tab'         => 'email',
				'type'        => 'text',
				'label'       => __( 'From Name', 'alynt-certificate-generator' ),
				'description' => __( 'Default sender name for certificate emails.', 'alynt-certificate-generator' ),
				'default'     => \get_bloginfo( 'name' ),
			),
			'email_from_address' => array(
				'tab'         => 'email',
				'type'        => 'email',
				'label'       => __( 'From Email', 'alynt-certificate-generator' ),
				'description' => __( 'Default sender email for certificate emails.', 'alynt-certificate-generator' ),
				'default'     => \get_bloginfo( 'admin_email' ),
			),
			'email_footer' => array(
				'tab'         => 'email',
				'type'        => 'textarea',
				'label'       => __( 'Email Footer', 'alynt-certificate-generator' ),
				'description' => __( 'Footer content appended to certificate emails.', 'alynt-certificate-generator' ),
				'default'     => '',
			),
			'log_retention_days' => array(
				'tab'         => 'logs',
				'type'        => 'number',
				'label'       => __( 'Log Retention (days)', 'alynt-certificate-generator' ),
				'description' => __( 'Automatically purge logs older than this value.', 'alynt-certificate-generator' ),
				'default'     => 365,
				'min'         => 1,
			),
			'enable_csv_export' => array(
				'tab'         => 'logs',
				'type'        => 'checkbox',
				'label'       => __( 'Enable CSV export', 'alynt-certificate-generator' ),
				'description' => __( 'Allow CSV export from the logs screen.', 'alynt-certificate-generator' ),
				'default'     => true,
			),
			'enable_bulk_cleanup' => array(
				'tab'         => 'logs',
				'type'        => 'checkbox',
				'label'       => __( 'Enable bulk cleanup tools', 'alynt-certificate-generator' ),
				'description' => __( 'Allow bulk cleanup of logs.', 'alynt-certificate-generator' ),
				'default'     => false,
			),
		);
	}

	/**
	 * Get default values.
	 *
	 * @return array<string, mixed>
	 */
	public function get_defaults(): array {
		$defaults = array();
		foreach ( $this->get_schema() as $key => $field ) {
			$defaults[ $key ] = $field['default'];
		}

		return $defaults;
	}

	/**
	 * Get fields for a tab.
	 *
	 * @param string $tab Tab ID.
	 * @return array<string, array<string, mixed>>
	 */
	public function get_fields_for_tab( string $tab ): array {
		$fields = array();
		foreach ( $this->get_schema() as $key => $field ) {
			if ( isset( $field['tab'] ) && $tab === $field['tab'] ) {
				$fields[ $key ] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Check if tab exists in schema.
	 *
	 * @param string $tab Tab ID.
	 * @return bool
	 */
	public function is_valid_tab( string $tab ): bool {
		// The fonts tab is valid but has no schema fields (custom UI).
		if ( 'fonts' === $tab ) {
			return true;
		}

		foreach ( $this->get_schema() as $field ) {
			if ( isset( $field['tab'] ) && $tab === $field['tab'] ) {
				return true;
			}
		}

		return false;
	}
}

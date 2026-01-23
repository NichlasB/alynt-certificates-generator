<?php
/**
 * REST API service for font management.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Font_Service;

class Alynt_Certificate_Generator_Font_Rest_Service {
	/**
	 * Font service.
	 *
	 * @var Alynt_Certificate_Generator_Font_Service
	 */
	private $font_service;

	public function __construct() {
		$this->font_service = new Alynt_Certificate_Generator_Font_Service();
	}

	/**
	 * Get all available fonts.
	 *
	 * Returns system fonts, global custom fonts, and optionally template-specific fonts.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_fonts( WP_REST_Request $request ) {
		$template_id = $request->get_param( 'template_id' );
		$template_id = $template_id ? (int) $template_id : 0;

		$system_fonts = $this->font_service->get_system_fonts();
		$global_fonts = $this->font_service->get_global_fonts();
		$template_fonts = array();

		if ( $template_id > 0 ) {
			$template_fonts = $this->font_service->get_template_fonts( $template_id );
		}

		// Format system fonts for response.
		$formatted_system = array();
		foreach ( $system_fonts as $slug => $data ) {
			$formatted_system[] = array(
				'family'    => $data['family'],
				'slug'      => $slug,
				'type'      => 'system',
				'weights'   => array_keys( array_filter( $data['weights'] ) ),
			);
		}

		// Format custom fonts for response.
		$formatted_global = array();
		foreach ( $global_fonts as $slug => $data ) {
			$formatted_global[] = array(
				'family'    => $data['family'],
				'slug'      => $slug,
				'type'      => 'global',
				'weights'   => array_keys( $data['weights'] ?? array() ),
			);
		}

		$formatted_template = array();
		foreach ( $template_fonts as $slug => $data ) {
			$formatted_template[] = array(
				'family'    => $data['family'],
				'slug'      => $slug,
				'type'      => 'template',
				'weights'   => array_keys( $data['weights'] ?? array() ),
			);
		}

		return new WP_REST_Response(
			array(
				'system'   => $formatted_system,
				'global'   => $formatted_global,
				'template' => $formatted_template,
			),
			200
		);
	}

	/**
	 * Get global fonts only.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_global_fonts( WP_REST_Request $request ) {
		$fonts = $this->font_service->get_global_fonts();

		$formatted = array();
		foreach ( $fonts as $slug => $data ) {
			$formatted[] = array(
				'family'  => $data['family'],
				'slug'    => $slug,
				'weights' => $data['weights'] ?? array(),
			);
		}

		return new WP_REST_Response( $formatted, 200 );
	}

	/**
	 * Create a new font family.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_font_family( WP_REST_Request $request ) {
		$family_name = $request->get_param( 'family_name' );
		$template_id = $request->get_param( 'template_id' );
		$template_id = $template_id ? (int) $template_id : 0;

		if ( empty( $family_name ) ) {
			return new WP_Error( 'acg_missing_family', __( 'Font family name is required.', 'alynt-certificate-generator' ), array( 'status' => 400 ) );
		}

		$result = $this->font_service->create_font_family( $family_name, $template_id );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * Upload a font weight.
	 *
	 * This endpoint expects multipart/form-data with:
	 * - family_slug: The font family slug
	 * - weight: The weight identifier (regular, bold, etc.)
	 * - font_file: The uploaded TTF/OTF file
	 * - template_id (optional): Template ID for template-specific fonts
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_font_weight( WP_REST_Request $request ) {
		$family_slug = $request->get_param( 'family_slug' );
		$weight = $request->get_param( 'weight' );
		$template_id = $request->get_param( 'template_id' );
		$template_id = $template_id ? (int) $template_id : 0;

		if ( empty( $family_slug ) || empty( $weight ) ) {
			return new WP_Error( 'acg_missing_params', __( 'Family slug and weight are required.', 'alynt-certificate-generator' ), array( 'status' => 400 ) );
		}

		// Get the uploaded file.
		$files = $request->get_file_params();
		if ( empty( $files['font_file'] ) ) {
			return new WP_Error( 'acg_missing_file', __( 'Font file is required.', 'alynt-certificate-generator' ), array( 'status' => 400 ) );
		}

		// Get the font family to get the display name.
		if ( $template_id > 0 ) {
			$fonts = $this->font_service->get_template_fonts( $template_id );
		} else {
			$fonts = $this->font_service->get_global_fonts();
		}

		if ( ! isset( $fonts[ $family_slug ] ) ) {
			return new WP_Error( 'acg_family_not_found', __( 'Font family not found.', 'alynt-certificate-generator' ), array( 'status' => 404 ) );
		}

		$family_name = $fonts[ $family_slug ]['family'];

		$result = $this->font_service->upload_font(
			$files['font_file'],
			$family_name,
			$weight,
			$template_id
		);

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * Delete a font family.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_font_family( WP_REST_Request $request ) {
		$family_slug = $request->get_param( 'family_slug' );
		$template_id = $request->get_param( 'template_id' );
		$template_id = $template_id ? (int) $template_id : 0;

		if ( empty( $family_slug ) ) {
			return new WP_Error( 'acg_missing_slug', __( 'Font family slug is required.', 'alynt-certificate-generator' ), array( 'status' => 400 ) );
		}

		$result = $this->font_service->delete_font_family( $family_slug, $template_id );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Delete a specific font weight.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_font_weight( WP_REST_Request $request ) {
		$family_slug = $request->get_param( 'family_slug' );
		$weight = $request->get_param( 'weight' );
		$template_id = $request->get_param( 'template_id' );
		$template_id = $template_id ? (int) $template_id : 0;

		if ( empty( $family_slug ) || empty( $weight ) ) {
			return new WP_Error( 'acg_missing_params', __( 'Family slug and weight are required.', 'alynt-certificate-generator' ), array( 'status' => 400 ) );
		}

		$result = $this->font_service->delete_font_weight( $family_slug, $weight, $template_id );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}
}

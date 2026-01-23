<?php
/**
 * Font management service.
 *
 * Main facade for font operations, delegating to validator and converter.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

use WP_Error;

class Alynt_Certificate_Generator_Font_Service {
	/**
	 * Option key for global custom fonts.
	 */
	const OPTION_KEY = 'acg_custom_fonts';

	/**
	 * Post meta key for per-template fonts.
	 */
	const META_KEY = 'acg_template_fonts';

	/**
	 * Allowed font weight identifiers (proxy to validator).
	 */
	const ALLOWED_WEIGHTS = array(
		'regular'     => 'Regular',
		'bold'        => 'Bold',
		'italic'      => 'Italic',
		'bold_italic' => 'Bold Italic',
	);

	/**
	 * Font validator instance.
	 *
	 * @var Alynt_Certificate_Generator_Font_Validator
	 */
	private $validator;

	/**
	 * Font converter instance.
	 *
	 * @var Alynt_Certificate_Generator_Font_Converter
	 */
	private $converter;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->validator = new Alynt_Certificate_Generator_Font_Validator();
		$this->converter = new Alynt_Certificate_Generator_Font_Converter();
	}

	/**
	 * Get the fonts storage directory path.
	 *
	 * @return string
	 */
	public function get_fonts_dir(): string {
		return $this->converter->get_fonts_dir();
	}

	/**
	 * Get the fonts storage directory URL.
	 *
	 * @return string
	 */
	public function get_fonts_url(): string {
		return $this->converter->get_fonts_url();
	}

	/**
	 * Ensure fonts directory exists with security files.
	 *
	 * @return bool
	 */
	public function ensure_fonts_directory(): bool {
		return $this->converter->ensure_fonts_directory();
	}

	/**
	 * Validate a TTF file.
	 *
	 * @param string $file_path Path to the TTF file.
	 * @return array|WP_Error Font info on success, WP_Error on failure.
	 */
	public function validate_ttf_file( string $file_path ) {
		return $this->validator->validate( $file_path );
	}

	/**
	 * Convert TTF to TCPDF format and store.
	 *
	 * @param string $ttf_path   Path to the TTF file.
	 * @param string $family_slug Font family slug.
	 * @param string $weight     Weight identifier.
	 * @return array|WP_Error Converted font info or error.
	 */
	public function convert_and_store_font( string $ttf_path, string $family_slug, string $weight ) {
		return $this->converter->convert_and_store( $ttf_path, $family_slug, $weight );
	}

	/**
	 * Upload and add a font weight to a family.
	 *
	 * @param array  $file        $_FILES array element.
	 * @param string $family_name Font family display name.
	 * @param string $weight      Weight identifier.
	 * @param int    $template_id Optional template ID for per-template fonts.
	 * @return array|WP_Error
	 */
	public function upload_font( array $file, string $family_name, string $weight, int $template_id = 0 ) {
		// Check for upload errors.
		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'acg_upload_error', __( 'File upload failed.', 'alynt-certificate-generator' ) );
		}

		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'acg_upload_error', __( 'Invalid file upload.', 'alynt-certificate-generator' ) );
		}

		// Validate file extension.
		$ext_check = $this->validator->validate_extension( $file['name'] );
		if ( is_wp_error( $ext_check ) ) {
			return $ext_check;
		}

		// Generate family slug.
		$family_slug = sanitize_title( $family_name );
		if ( '' === $family_slug ) {
			return new WP_Error( 'acg_font_invalid_name', __( 'Invalid font family name.', 'alynt-certificate-generator' ) );
		}

		// Convert and store.
		$result = $this->converter->convert_and_store( $file['tmp_name'], $family_slug, $weight );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update registry.
		if ( $template_id > 0 ) {
			$this->add_template_font( $template_id, $family_name, $family_slug, $weight, $result );
		} else {
			$this->add_global_font( $family_name, $family_slug, $weight, $result );
		}

		return array(
			'family'     => $family_name,
			'slug'       => $family_slug,
			'weight'     => $weight,
			'tcpdf_name' => $result['tcpdf_name'],
		);
	}

	/**
	 * Add a font weight to global registry.
	 */
	private function add_global_font( string $family_name, string $family_slug, string $weight, array $font_data ): void {
		$fonts = $this->get_global_fonts();

		if ( ! isset( $fonts[ $family_slug ] ) ) {
			$fonts[ $family_slug ] = array(
				'family'  => $family_name,
				'slug'    => $family_slug,
				'weights' => array(),
			);
		}

		$fonts[ $family_slug ]['weights'][ $weight ] = array(
			'label'      => self::ALLOWED_WEIGHTS[ $weight ],
			'tcpdf_name' => $font_data['tcpdf_name'],
			'file_path'  => $font_data['file_path'],
		);

		\update_option( self::OPTION_KEY, $fonts );
	}

	/**
	 * Add a font weight to template-specific registry.
	 */
	private function add_template_font( int $template_id, string $family_name, string $family_slug, string $weight, array $font_data ): void {
		$fonts = $this->get_template_fonts( $template_id );

		if ( ! isset( $fonts[ $family_slug ] ) ) {
			$fonts[ $family_slug ] = array(
				'family'  => $family_name,
				'slug'    => $family_slug,
				'weights' => array(),
			);
		}

		$fonts[ $family_slug ]['weights'][ $weight ] = array(
			'label'      => self::ALLOWED_WEIGHTS[ $weight ],
			'tcpdf_name' => $font_data['tcpdf_name'],
			'file_path'  => $font_data['file_path'],
		);

		\update_post_meta( $template_id, self::META_KEY, wp_json_encode( $fonts ) );
	}

	/**
	 * Get all global custom fonts.
	 */
	public function get_global_fonts(): array {
		$fonts = \get_option( self::OPTION_KEY, array() );
		return is_array( $fonts ) ? $fonts : array();
	}

	/**
	 * Get template-specific fonts.
	 */
	public function get_template_fonts( int $template_id ): array {
		$raw = (string) \get_post_meta( $template_id, self::META_KEY, true );
		if ( '' === $raw ) {
			return array();
		}
		$fonts = json_decode( $raw, true );
		return is_array( $fonts ) ? $fonts : array();
	}

	/**
	 * Get all available fonts for a template (global + template-specific).
	 */
	public function get_all_fonts_for_template( int $template_id ): array {
		return array_merge( $this->get_global_fonts(), $this->get_template_fonts( $template_id ) );
	}

	/** Get system fonts (built-in TCPDF fonts). */
	public function get_system_fonts(): array {
		$w = array( 'regular' => true, 'bold' => true, 'italic' => true, 'bold_italic' => true );
		return array(
			'arial' => array( 'family' => 'Arial', 'slug' => 'arial', 'tcpdf' => 'helvetica', 'weights' => $w ),
			'helvetica' => array( 'family' => 'Helvetica', 'slug' => 'helvetica', 'tcpdf' => 'helvetica', 'weights' => $w ),
			'times_new_roman' => array( 'family' => 'Times New Roman', 'slug' => 'times_new_roman', 'tcpdf' => 'times', 'weights' => $w ),
			'georgia' => array( 'family' => 'Georgia', 'slug' => 'georgia', 'tcpdf' => 'times', 'weights' => $w ),
			'courier_new' => array( 'family' => 'Courier New', 'slug' => 'courier_new', 'tcpdf' => 'courier', 'weights' => $w ),
			'verdana' => array( 'family' => 'Verdana', 'slug' => 'verdana', 'tcpdf' => 'helvetica', 'weights' => $w ),
		);
	}

	/** Delete a font family. */
	public function delete_font_family( string $family_slug, int $template_id = 0 ) {
		$family_slug = sanitize_title( $family_slug );
		$fonts = $template_id > 0 ? $this->get_template_fonts( $template_id ) : $this->get_global_fonts();
		if ( ! isset( $fonts[ $family_slug ] ) ) {
			return new WP_Error( 'acg_font_not_found', __( 'Font family not found.', 'alynt-certificate-generator' ) );
		}
		$this->converter->delete_family_directory( $family_slug );
		unset( $fonts[ $family_slug ] );
		$template_id > 0 ? \update_post_meta( $template_id, self::META_KEY, wp_json_encode( $fonts ) ) : \update_option( self::OPTION_KEY, $fonts );
		return true;
	}

	/** Delete a specific font weight. */
	public function delete_font_weight( string $family_slug, string $weight, int $template_id = 0 ) {
		$family_slug = sanitize_title( $family_slug );
		$weight = sanitize_key( $weight );
		$fonts = $template_id > 0 ? $this->get_template_fonts( $template_id ) : $this->get_global_fonts();
		if ( ! isset( $fonts[ $family_slug ] ) ) {
			return new WP_Error( 'acg_font_not_found', __( 'Font family not found.', 'alynt-certificate-generator' ) );
		}
		if ( ! isset( $fonts[ $family_slug ]['weights'][ $weight ] ) ) {
			return new WP_Error( 'acg_font_weight_not_found', __( 'Font weight not found.', 'alynt-certificate-generator' ) );
		}
		$this->converter->delete_font_files( $family_slug, $fonts[ $family_slug ]['weights'][ $weight ]['tcpdf_name'] );
		unset( $fonts[ $family_slug ]['weights'][ $weight ] );
		if ( empty( $fonts[ $family_slug ]['weights'] ) ) {
			unset( $fonts[ $family_slug ] );
			$this->converter->cleanup_empty_directory( $family_slug );
		}
		$template_id > 0 ? \update_post_meta( $template_id, self::META_KEY, wp_json_encode( $fonts ) ) : \update_option( self::OPTION_KEY, $fonts );
		return true;
	}

	/** Resolve a font for PDF generation. */
	public function resolve_font_for_pdf( string $font_family, bool $bold, bool $italic, int $template_id = 0 ): array {
		$weight = $this->determine_weight( $bold, $italic );
		$font_slug = sanitize_title( $font_family );
		foreach ( $this->get_all_fonts_for_template( $template_id ) as $family_slug => $family_data ) {
			if ( $family_slug === $font_slug || strtolower( $family_data['family'] ) === strtolower( $font_family ) ) {
				return $this->resolve_custom_font( $family_data, $weight, $bold, $italic );
			}
		}
		return $this->resolve_system_font( $font_family, $font_slug );
	}

	private function determine_weight( bool $bold, bool $italic ): string {
		if ( $bold && $italic ) return 'bold_italic';
		if ( $bold ) return 'bold';
		if ( $italic ) return 'italic';
		return 'regular';
	}

	private function resolve_custom_font( array $family_data, string $weight, bool $bold, bool $italic ): array {
		$weights = $family_data['weights'] ?? array();
		if ( isset( $weights[ $weight ] ) ) {
			return array( 'tcpdf_name' => $weights[ $weight ]['tcpdf_name'], 'file_path' => $weights[ $weight ]['file_path'], 'is_custom' => true, 'simulate_bold' => false, 'simulate_italic' => false );
		}
		if ( isset( $weights['regular'] ) ) {
			return array( 'tcpdf_name' => $weights['regular']['tcpdf_name'], 'file_path' => $weights['regular']['file_path'], 'is_custom' => true, 'simulate_bold' => $bold && ! isset( $weights['bold'] ), 'simulate_italic' => $italic && ! isset( $weights['italic'] ) );
		}
		$first = reset( $weights );
		return $first ? array( 'tcpdf_name' => $first['tcpdf_name'], 'file_path' => $first['file_path'], 'is_custom' => true, 'simulate_bold' => $bold, 'simulate_italic' => $italic ) : $this->default_font_response();
	}

	private function resolve_system_font( string $font_family, string $font_slug ): array {
		foreach ( $this->get_system_fonts() as $sys_slug => $sys_data ) {
			if ( $sys_slug === $font_slug || strtolower( $sys_data['family'] ) === strtolower( $font_family ) ) {
				return array( 'tcpdf_name' => $sys_data['tcpdf'], 'file_path' => '', 'is_custom' => false, 'simulate_bold' => false, 'simulate_italic' => false );
			}
		}
		return $this->default_font_response();
	}

	private function default_font_response(): array {
		return array( 'tcpdf_name' => 'helvetica', 'file_path' => '', 'is_custom' => false, 'simulate_bold' => false, 'simulate_italic' => false );
	}

	/** Create a new font family entry without uploading files. */
	public function create_font_family( string $family_name, int $template_id = 0 ) {
		$family_slug = sanitize_title( $family_name );
		if ( '' === $family_slug ) {
			return new WP_Error( 'acg_font_invalid_name', __( 'Invalid font family name.', 'alynt-certificate-generator' ) );
		}
		$fonts = $template_id > 0 ? $this->get_template_fonts( $template_id ) : $this->get_global_fonts();
		if ( isset( $fonts[ $family_slug ] ) ) {
			return new WP_Error( 'acg_font_exists', __( 'Font family already exists.', 'alynt-certificate-generator' ) );
		}
		$fonts[ $family_slug ] = array( 'family' => $family_name, 'slug' => $family_slug, 'weights' => array() );
		$template_id > 0 ? \update_post_meta( $template_id, self::META_KEY, wp_json_encode( $fonts ) ) : \update_option( self::OPTION_KEY, $fonts );
		return array( 'family' => $family_name, 'slug' => $family_slug );
	}
}

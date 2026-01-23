<?php
/**
 * Font conversion service.
 *
 * Handles TCPDF font conversion and file storage.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

use WP_Error;
use TCPDF_FONTS;

class Alynt_Certificate_Generator_Font_Converter {
	/**
	 * Font validator instance.
	 *
	 * @var Alynt_Certificate_Generator_Font_Validator
	 */
	private $validator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->validator = new Alynt_Certificate_Generator_Font_Validator();
	}

	/**
	 * Get the fonts storage directory path.
	 *
	 * @return string
	 */
	public function get_fonts_dir(): string {
		$upload_dir = \wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'acg-fonts/';
	}

	/**
	 * Get the fonts storage directory URL.
	 *
	 * @return string
	 */
	public function get_fonts_url(): string {
		$upload_dir = \wp_upload_dir();
		return trailingslashit( $upload_dir['baseurl'] ) . 'acg-fonts/';
	}

	/**
	 * Ensure fonts directory exists with security files.
	 *
	 * @return bool
	 */
	public function ensure_fonts_directory(): bool {
		$fonts_dir = $this->get_fonts_dir();

		if ( ! file_exists( $fonts_dir ) ) {
			\wp_mkdir_p( $fonts_dir );
		}

		$this->create_security_files( $fonts_dir );

		return is_dir( $fonts_dir ) && is_writable( $fonts_dir );
	}

	/**
	 * Create security files in the fonts directory.
	 *
	 * @param string $fonts_dir Fonts directory path.
	 */
	private function create_security_files( string $fonts_dir ): void {
		// Add .htaccess to prevent direct access to font files.
		$htaccess = $fonts_dir . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$htaccess_content = "# Protect font files\n<FilesMatch \"\\.(ttf|php|z|ctg\\.z)$\">\n    Order Allow,Deny\n    Deny from all\n</FilesMatch>\n";
			file_put_contents( $htaccess, $htaccess_content );
		}

		// Add index.php for security.
		$index = $fonts_dir . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '<?php // Silence is golden.' );
		}
	}

	/**
	 * Convert TTF to TCPDF format and store.
	 *
	 * @param string $ttf_path    Path to the TTF file.
	 * @param string $family_slug Font family slug.
	 * @param string $weight      Weight identifier (regular, bold, etc.).
	 * @return array|WP_Error Converted font info or error.
	 */
	public function convert_and_store( string $ttf_path, string $family_slug, string $weight ) {
		if ( ! $this->ensure_fonts_directory() ) {
			return new WP_Error( 'acg_font_dir_error', __( 'Cannot create fonts directory.', 'alynt-certificate-generator' ) );
		}

		// Validate the font file.
		$validation = $this->validator->validate( $ttf_path );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$family_slug = sanitize_title( $family_slug );
		$weight = sanitize_key( $weight );

		if ( ! $this->validator->is_valid_weight( $weight ) ) {
			return new WP_Error( 'acg_font_invalid_weight', __( 'Invalid font weight.', 'alynt-certificate-generator' ) );
		}

		// Prepare family directory.
		$family_dir = $this->get_fonts_dir() . $family_slug . '/';
		if ( ! file_exists( $family_dir ) ) {
			\wp_mkdir_p( $family_dir );
		}

		// Generate TCPDF font name and copy TTF file.
		$tcpdf_name = $family_slug . '_' . $weight;
		$ttf_dest = $family_dir . $tcpdf_name . '.ttf';

		if ( ! copy( $ttf_path, $ttf_dest ) ) {
			return new WP_Error( 'acg_font_copy_error', __( 'Cannot copy font file.', 'alynt-certificate-generator' ) );
		}

		// Convert using TCPDF_FONTS.
		$font_file = $this->convert_with_tcpdf( $ttf_dest, $family_dir );
		if ( is_wp_error( $font_file ) ) {
			@unlink( $ttf_dest );
			return $font_file;
		}

		return array(
			'tcpdf_name' => basename( $font_file, '.php' ),
			'file_path'  => $family_dir . basename( $font_file ),
			'ttf_path'   => $ttf_dest,
			'family_dir' => $family_dir,
		);
	}

	/**
	 * Convert TTF file using TCPDF_FONTS.
	 *
	 * @param string $ttf_path   Path to TTF file.
	 * @param string $output_dir Output directory for converted files.
	 * @return string|WP_Error Path to PHP font file or error.
	 */
	private function convert_with_tcpdf( string $ttf_path, string $output_dir ) {
		try {
			$font_file = TCPDF_FONTS::addTTFfont(
				$ttf_path,
				'TrueTypeUnicode',
				'',
				32,
				$output_dir
			);

			if ( ! $font_file ) {
				return new WP_Error( 'acg_font_convert_error', __( 'Font conversion failed.', 'alynt-certificate-generator' ) );
			}

			return $font_file;
		} catch ( \Exception $e ) {
			return new WP_Error( 'acg_font_convert_error', $e->getMessage() );
		}
	}

	/**
	 * Delete font weight files.
	 *
	 * @param string $family_slug Font family slug.
	 * @param string $tcpdf_name  TCPDF font name.
	 */
	public function delete_font_files( string $family_slug, string $tcpdf_name ): void {
		$family_dir = $this->get_fonts_dir() . $family_slug . '/';

		$files_to_delete = array(
			$family_dir . $tcpdf_name . '.php',
			$family_dir . $tcpdf_name . '.z',
			$family_dir . $tcpdf_name . '.ctg.z',
			$family_dir . $tcpdf_name . '.ttf',
		);

		foreach ( $files_to_delete as $file ) {
			if ( file_exists( $file ) ) {
				@unlink( $file );
			}
		}
	}

	/**
	 * Delete entire font family directory.
	 *
	 * @param string $family_slug Font family slug.
	 */
	public function delete_family_directory( string $family_slug ): void {
		$family_dir = $this->get_fonts_dir() . $family_slug . '/';
		if ( is_dir( $family_dir ) ) {
			$this->delete_directory( $family_dir );
		}
	}

	/**
	 * Clean up empty family directory.
	 *
	 * @param string $family_slug Font family slug.
	 */
	public function cleanup_empty_directory( string $family_slug ): void {
		$family_dir = $this->get_fonts_dir() . $family_slug . '/';
		if ( is_dir( $family_dir ) ) {
			@rmdir( $family_dir );
		}
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Directory path.
	 */
	private function delete_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );
			} else {
				@unlink( $path );
			}
		}

		@rmdir( $dir );
	}
}

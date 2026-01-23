<?php
/**
 * Font validation service.
 *
 * Handles TTF/OTF file validation and metadata extraction.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

use WP_Error;

class Alynt_Certificate_Generator_Font_Validator {
	/**
	 * Allowed font weight identifiers.
	 *
	 * @var array
	 */
	const ALLOWED_WEIGHTS = array(
		'regular'     => 'Regular',
		'bold'        => 'Bold',
		'italic'      => 'Italic',
		'bold_italic' => 'Bold Italic',
	);

	/**
	 * Maximum font file size (10MB).
	 *
	 * @var int
	 */
	const MAX_FILE_SIZE = 10485760;

	/**
	 * Valid TTF/OTF magic bytes.
	 *
	 * @var array
	 */
	const VALID_MAGIC_BYTES = array(
		"\x00\x01\x00\x00", // TTF.
		'true',            // TrueType.
		'typ1',            // Type 1.
		'OTTO',            // OpenType with CFF.
	);

	/**
	 * Validate a TTF/OTF file.
	 *
	 * @param string $file_path Path to the font file.
	 * @return array|WP_Error Font info on success, WP_Error on failure.
	 */
	public function validate( string $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'acg_font_not_found', __( 'Font file not found.', 'alynt-certificate-generator' ) );
		}

		// Check file size.
		$file_size = filesize( $file_path );
		if ( $file_size > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'acg_font_too_large', __( 'Font file exceeds 10MB limit.', 'alynt-certificate-generator' ) );
		}

		// Check magic bytes.
		$magic_check = $this->check_magic_bytes( $file_path );
		if ( is_wp_error( $magic_check ) ) {
			return $magic_check;
		}

		// Extract font metadata.
		return $this->extract_font_info( $file_path );
	}

	/**
	 * Validate file extension.
	 *
	 * @param string $filename Original filename.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function validate_extension( string $filename ) {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'ttf', 'otf' ), true ) ) {
			return new WP_Error( 'acg_font_invalid_type', __( 'Only TTF and OTF files are allowed.', 'alynt-certificate-generator' ) );
		}
		return true;
	}

	/**
	 * Validate font weight identifier.
	 *
	 * @param string $weight Weight identifier.
	 * @return bool
	 */
	public function is_valid_weight( string $weight ): bool {
		return isset( self::ALLOWED_WEIGHTS[ $weight ] );
	}

	/**
	 * Check magic bytes for TTF/OTF format.
	 *
	 * @param string $file_path Path to font file.
	 * @return bool|WP_Error
	 */
	private function check_magic_bytes( string $file_path ) {
		$handle = fopen( $file_path, 'rb' );
		if ( ! $handle ) {
			return new WP_Error( 'acg_font_read_error', __( 'Cannot read font file.', 'alynt-certificate-generator' ) );
		}

		$magic = fread( $handle, 4 );
		fclose( $handle );

		if ( ! in_array( $magic, self::VALID_MAGIC_BYTES, true ) ) {
			return new WP_Error( 'acg_font_invalid', __( 'Invalid font file format. Only TTF and OTF files are supported.', 'alynt-certificate-generator' ) );
		}

		return true;
	}

	/**
	 * Extract font information from TTF file.
	 *
	 * @param string $file_path Path to TTF file.
	 * @return array|WP_Error
	 */
	private function extract_font_info( string $file_path ) {
		$handle = fopen( $file_path, 'rb' );
		if ( ! $handle ) {
			return new WP_Error( 'acg_font_read_error', __( 'Cannot read font file.', 'alynt-certificate-generator' ) );
		}

		// Read the offset table.
		$offset_table = fread( $handle, 12 );
		if ( strlen( $offset_table ) < 12 ) {
			fclose( $handle );
			return new WP_Error( 'acg_font_invalid', __( 'Invalid font file structure.', 'alynt-certificate-generator' ) );
		}

		$data = unpack( 'Nsfnt_version/nnumTables/nsearchRange/nentrySelector/nrangeShift', $offset_table );
		if ( ! $data ) {
			fclose( $handle );
			return new WP_Error( 'acg_font_invalid', __( 'Cannot parse font offset table.', 'alynt-certificate-generator' ) );
		}

		$name_table = $this->find_name_table( $handle, $data['numTables'] );
		if ( 0 === $name_table['offset'] ) {
			fclose( $handle );
			return $this->fallback_font_info( $file_path );
		}

		$names = $this->parse_name_table( $handle, $name_table['offset'] );
		fclose( $handle );

		if ( '' === $names['family_name'] ) {
			$names['family_name'] = pathinfo( $file_path, PATHINFO_FILENAME );
		}
		if ( '' === $names['full_name'] ) {
			$names['full_name'] = $names['family_name'];
		}

		return $names;
	}

	/**
	 * Find the 'name' table in the font file.
	 *
	 * @param resource $handle File handle.
	 * @param int      $num_tables Number of tables.
	 * @return array Table offset and length.
	 */
	private function find_name_table( $handle, int $num_tables ): array {
		for ( $i = 0; $i < $num_tables; $i++ ) {
			$table_record = fread( $handle, 16 );
			if ( strlen( $table_record ) < 16 ) {
				break;
			}

			$table_data = unpack( 'a4tag/Nchecksum/Noffset/Nlength', $table_record );
			if ( 'name' === $table_data['tag'] ) {
				return array(
					'offset' => $table_data['offset'],
					'length' => $table_data['length'],
				);
			}
		}

		return array( 'offset' => 0, 'length' => 0 );
	}

	/**
	 * Parse the name table to extract font names.
	 *
	 * @param resource $handle File handle.
	 * @param int      $offset Table offset.
	 * @return array Family name and full name.
	 */
	private function parse_name_table( $handle, int $offset ): array {
		fseek( $handle, $offset );
		$name_header = fread( $handle, 6 );
		if ( strlen( $name_header ) < 6 ) {
			return array( 'family_name' => '', 'full_name' => '' );
		}

		$name_data = unpack( 'nformat/ncount/nstringOffset', $name_header );
		$string_offset = $offset + $name_data['stringOffset'];
		$count = $name_data['count'];

		$family_name = '';
		$full_name = '';

		for ( $i = 0; $i < $count; $i++ ) {
			$name_record = fread( $handle, 12 );
			if ( strlen( $name_record ) < 12 ) {
				break;
			}

			$record = unpack( 'nplatformID/nencodingID/nlanguageID/nnameID/nlength/noffset', $name_record );

			// nameID 1 = Font Family, nameID 4 = Full Name.
			if ( in_array( $record['nameID'], array( 1, 4 ), true ) ) {
				$name_string = $this->read_name_string( $handle, $string_offset + $record['offset'], $record['length'], $record['platformID'] );

				if ( 1 === $record['nameID'] && '' === $family_name ) {
					$family_name = $name_string;
				}
				if ( 4 === $record['nameID'] && '' === $full_name ) {
					$full_name = $name_string;
				}
			}

			if ( '' !== $family_name && '' !== $full_name ) {
				break;
			}
		}

		return array(
			'family_name' => $family_name,
			'full_name'   => $full_name,
		);
	}

	/**
	 * Read and decode a name string from the font file.
	 *
	 * @param resource $handle File handle.
	 * @param int      $offset String offset.
	 * @param int      $length String length.
	 * @param int      $platform_id Platform ID for encoding.
	 * @return string
	 */
	private function read_name_string( $handle, int $offset, int $length, int $platform_id ): string {
		$current_pos = ftell( $handle );
		fseek( $handle, $offset );
		$name_string = fread( $handle, $length );
		fseek( $handle, $current_pos );

		// Handle encoding based on platform.
		if ( 3 === $platform_id ) {
			// Windows: UTF-16BE.
			$name_string = mb_convert_encoding( $name_string, 'UTF-8', 'UTF-16BE' );
		} elseif ( 1 === $platform_id ) {
			// Mac: mostly ASCII/MacRoman.
			$name_string = iconv( 'macintosh', 'UTF-8//IGNORE', $name_string );
		}

		return trim( (string) $name_string );
	}

	/**
	 * Return fallback font info based on filename.
	 *
	 * @param string $file_path File path.
	 * @return array
	 */
	private function fallback_font_info( string $file_path ): array {
		$name = pathinfo( $file_path, PATHINFO_FILENAME );
		return array(
			'family_name' => $name,
			'full_name'   => $name,
		);
	}
}

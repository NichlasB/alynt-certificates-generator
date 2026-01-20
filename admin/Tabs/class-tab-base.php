<?php
/**
 * Base tab definition.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi\Tabs;

abstract class Alynt_Certificate_Generator_Tab_Base {
	/**
	 * Tab ID.
	 *
	 * @return string
	 */
	abstract public function get_id(): string;

	/**
	 * Tab title.
	 *
	 * @return string
	 */
	abstract public function get_title(): string;

	/**
	 * Tab description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return '';
	}
}

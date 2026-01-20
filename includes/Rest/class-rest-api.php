<?php
/**
 * REST API orchestrator.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Rest;

use WP_REST_Request;
use WP_REST_Server;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Download_Service;
use Alynt\CertificateGenerator\Rest\Alynt_Certificate_Generator_Webhook_Service;
use Alynt\CertificateGenerator\Rest\Alynt_Certificate_Generator_Bulk_Service;

class Alynt_Certificate_Generator_Rest_Api {
	/**
	 * Template service.
	 *
	 * @var Alynt_Certificate_Generator_Template_Service
	 */
	private $template_service;

	/**
	 * Download service.
	 *
	 * @var Alynt_Certificate_Generator_Download_Service
	 */
	private $download_service;

	/**
	 * Incoming webhook service.
	 *
	 * @var Alynt_Certificate_Generator_Webhook_Service
	 */
	private $webhook_service;

	/**
	 * Bulk status service.
	 *
	 * @var Alynt_Certificate_Generator_Bulk_Service
	 */
	private $bulk_service;

	public function __construct() {
		$this->template_service = new Alynt_Certificate_Generator_Template_Service();
		$this->download_service = new Alynt_Certificate_Generator_Download_Service();
		$this->webhook_service  = new Alynt_Certificate_Generator_Webhook_Service();
		$this->bulk_service     = new Alynt_Certificate_Generator_Bulk_Service();
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		\register_rest_route(
			'acg/v1',
			'/templates/(?P<id>\\d+)/variables',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this->template_service, 'update_variables' ),
				'permission_callback' => array( $this, 'can_manage_templates' ),
				'args'                => array(
					'id'        => array(
						'type'     => 'integer',
						'required' => true,
					),
					'variables' => array(
						'required' => true,
					),
				),
			)
		);

		\register_rest_route(
			'acg/v1',
			'/certificates/(?P<certificate_id>[A-Za-z0-9\\-]+)/download',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this->download_service, 'serve_download' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'certificate_id' => array(
						'type'     => 'string',
						'required' => true,
					),
					'token' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		\register_rest_route(
			'acg/v1',
			'/templates/(?P<id>\\d+)/incoming',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this->webhook_service, 'handle_incoming' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		\register_rest_route(
			'acg/v1',
			'/bulk/(?P<bulk_id>[A-Za-z0-9\\-_]+)/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this->bulk_service, 'get_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'bulk_id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Permission callback for template endpoints.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return bool
	 */
	public function can_manage_templates( WP_REST_Request $request ): bool {
		return \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE );
	}
}

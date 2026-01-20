<?php
/**
 * Core plugin class.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator;

use Alynt\CertificateGenerator\AdminUi\Alynt_Certificate_Generator_Admin_Menu;
use Alynt\CertificateGenerator\AdminUi\Alynt_Certificate_Generator_Admin_Notices;
use Alynt\CertificateGenerator\AdminUi\Alynt_Certificate_Generator_Certificate_Log_Page;
use Alynt\CertificateGenerator\AdminUi\Alynt_Certificate_Generator_Bulk_Generator_Page;
use Alynt\CertificateGenerator\AdminUi\Alynt_Certificate_Generator_Admin_Assets;
use Alynt\CertificateGenerator\AdminUi\Alynt_Certificate_Generator_Email_Template_Admin;
use Alynt\CertificateGenerator\AdminUi\Alynt_Certificate_Generator_Settings;
use Alynt\CertificateGenerator\AdminUi\Alynt_Certificate_Generator_Template_Admin;
use Alynt\CertificateGenerator\Cpt\Alynt_Certificate_Generator_Cpt_Manager;
use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Database;
use Alynt\CertificateGenerator\Frontend\Alynt_Certificate_Generator_Frontend;
use Alynt\CertificateGenerator\Rest\Alynt_Certificate_Generator_Rest_Api;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Webhook_Dispatcher;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Bulk_Service;

class Alynt_Certificate_Generator_Plugin {
	/**
	 * Plugin loader.
	 *
	 * @var Alynt_Certificate_Generator_Loader
	 */
	private $loader;

	/**
	 * Plugin name/text-domain.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	public function __construct() {
		$this->plugin_name = ALYNT_CERTIFICATE_GENERATOR_TEXT_DOMAIN;
		$this->version     = ALYNT_CERTIFICATE_GENERATOR_VERSION;
		$this->loader      = new Alynt_Certificate_Generator_Loader( ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR );

		Alynt_Certificate_Generator_Database::maybe_migrate();

		$this->set_locale();
		$this->define_cpt_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_rest_hooks();
		$this->define_webhook_hooks();
		$this->define_bulk_hooks();
	}

	/**
	 * Register plugin localization.
	 */
	private function set_locale(): void {
		$i18n = new Alynt_Certificate_Generator_I18n();
		$this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register admin hooks.
	 */
	private function define_admin_hooks(): void {
		if ( ! \is_admin() ) {
			return;
		}

		$admin_menu     = new Alynt_Certificate_Generator_Admin_Menu( $this->plugin_name, $this->version );
		$settings       = new Alynt_Certificate_Generator_Settings();
		$template_admin = new Alynt_Certificate_Generator_Template_Admin( $this->plugin_name, $this->version );
		$email_admin    = new Alynt_Certificate_Generator_Email_Template_Admin();
		$admin_notices  = new Alynt_Certificate_Generator_Admin_Notices();
		$log_page       = new Alynt_Certificate_Generator_Certificate_Log_Page();
		$bulk_page      = new Alynt_Certificate_Generator_Bulk_Generator_Page();
		$admin_assets   = new Alynt_Certificate_Generator_Admin_Assets();

		$this->loader->add_action( 'admin_menu', $admin_menu, 'register_menu' );
		$this->loader->add_action( 'admin_init', $settings, 'register' );
		$this->loader->add_action( 'admin_init', $log_page, 'register_actions' );
		$this->loader->add_action( 'admin_init', $bulk_page, 'register_actions' );
		$this->loader->add_action( 'add_meta_boxes', $template_admin, 'register_metaboxes' );
		$this->loader->add_action( 'save_post_acg_certificate_template', $template_admin, 'save_template_meta' );
		$this->loader->add_action( 'admin_enqueue_scripts', $template_admin, 'enqueue_assets' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin_assets, 'enqueue_assets' );
		$this->loader->add_action( 'admin_notices', $template_admin, 'render_admin_errors' );
		$this->loader->add_action( 'add_meta_boxes', $email_admin, 'register_metaboxes' );
		$this->loader->add_action( 'save_post_acg_email_template', $email_admin, 'save_meta' );
		$this->loader->add_action( 'admin_notices', $admin_notices, 'render_webhook_notice' );
	}

	/**
	 * Register CPT hooks.
	 */
	private function define_cpt_hooks(): void {
		$cpt_manager = new Alynt_Certificate_Generator_Cpt_Manager();
		$this->loader->add_action( 'init', $cpt_manager, 'register_post_types' );
		$this->loader->add_action( 'init', $cpt_manager, 'register_meta' );
	}

	/**
	 * Register public hooks.
	 */
	private function define_public_hooks(): void {
		$frontend = new Alynt_Certificate_Generator_Frontend( $this->plugin_name, $this->version );
		$this->loader->add_action( 'init', $frontend, 'register_shortcodes' );
	}

	/**
	 * Register REST hooks.
	 */
	private function define_rest_hooks(): void {
		$api = new Alynt_Certificate_Generator_Rest_Api();
		$this->loader->add_action( 'rest_api_init', $api, 'register_routes' );
	}

	/**
	 * Register webhook processing hooks.
	 */
	private function define_webhook_hooks(): void {
		$dispatcher = new Alynt_Certificate_Generator_Webhook_Dispatcher();
		$this->loader->add_action( 'alynt_certificate_generator_send_webhook', $dispatcher, 'handle_send', 10, 5 );
	}

	/**
	 * Register bulk processing hooks.
	 */
	private function define_bulk_hooks(): void {
		$bulk_service = new Alynt_Certificate_Generator_Bulk_Service();
		$this->loader->add_action( 'alynt_certificate_generator_bulk_generate', $bulk_service, 'process_row', 10, 4 );
	}

	/**
	 * Run the loader.
	 */
	public function run(): void {
		$this->loader->run();
	}
}

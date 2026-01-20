<?php
/**
 * Plugin loader and autoloader.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator;

class Alynt_Certificate_Generator_Loader {
	/**
	 * Registered actions.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $actions = array();

	/**
	 * Registered filters.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $filters = array();

	/**
	 * Plugin base directory path.
	 *
	 * @var string
	 */
	private $base_dir;

	public function __construct( string $base_dir = '' ) {
		$this->base_dir = '' !== $base_dir
			? rtrim( $base_dir, "/\\" ) . DIRECTORY_SEPARATOR
			: dirname( __DIR__ ) . DIRECTORY_SEPARATOR;

		$this->register_autoloader();
	}

	/**
	 * Register the plugin autoloader.
	 */
	public function register_autoloader(): void {
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Autoload plugin classes.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public function autoload( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file           = $this->get_class_file_path( $relative_class );

		if ( null !== $file && file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Resolve class name to file path.
	 *
	 * @param string $relative_class Relative class name (without base namespace).
	 * @return string|null
	 */
	private function get_class_file_path( string $relative_class ): ?string {
		$segments = explode( '\\', $relative_class );
		if ( empty( $segments ) ) {
			return null;
		}

		$directory_map = array(
			'AdminUi'  => 'admin',
			'Frontend' => 'public',
			'Admin'    => 'includes/Admin',
			'Rest'     => 'includes/Rest',
			'Services' => 'includes/Services',
			'Database' => 'includes/Database',
			'Helpers'  => 'includes/Helpers',
			'Cpt'      => 'includes/Cpt',
		);

		$directory = 'includes';
		if ( isset( $directory_map[ $segments[0] ] ) ) {
			$directory = $directory_map[ $segments[0] ];
			array_shift( $segments );
		}

		if ( empty( $segments ) ) {
			return null;
		}

		$class_name = array_pop( $segments );
		$subdir     = '';
		if ( ! empty( $segments ) ) {
			$subdir = implode( DIRECTORY_SEPARATOR, $segments ) . DIRECTORY_SEPARATOR;
		}

		$normalized = preg_replace( '/^Alynt_Certificate_Generator_/', '', $class_name );
		$normalized = str_replace( '_', '-', $normalized );
		$filename   = 'class-' . strtolower( $normalized ) . '.php';

		return $this->base_dir . $directory . DIRECTORY_SEPARATOR . $subdir . $filename;
	}

	/**
	 * Add an action to the collection to be registered with WordPress.
	 *
	 * @param string $hook          Action hook name.
	 * @param object $component     Object instance.
	 * @param string $callback      Method name on the component.
	 * @param int    $priority      Hook priority.
	 * @param int    $accepted_args Number of accepted args.
	 */
	public function add_action(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Add a filter to the collection to be registered with WordPress.
	 *
	 * @param string $hook          Filter hook name.
	 * @param object $component     Object instance.
	 * @param string $callback      Method name on the component.
	 * @param int    $priority      Hook priority.
	 * @param int    $accepted_args Number of accepted args.
	 */
	public function add_filter(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->filters[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Register the hooks with WordPress.
	 */
	public function run(): void {
		foreach ( $this->actions as $hook ) {
			\add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->filters as $hook ) {
			\add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}

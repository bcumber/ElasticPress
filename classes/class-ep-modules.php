<?php

class EP_Module_Loader {

	public function load_all() {
		$modules = get_option( 'ep_active_modules', array() );

		$module_objects = array();

		foreach ( $modules as $module_path ) {
			$module = require_once( $module_path );
			$module = new $module;

			$module_objects[$module_path] = $module;
		}

		$already_setup = array();

		$to_setup = array();

		foreach ( $module_objects as $module_slug => $module ) {
			array_unshift( $to_setup, $module_slug );

			while ( true ) {
				$deps = $to_setup[0]->get_dependencies();

				if ( empty( $deps ) || array_intersect( $deps, $to_setup ) === count( $deps ) ) {
					// break and set them all up
					break;
				} else {
					array_splice( $to_setup, 0, 0, $deps );
				}
			}

			foreach ( $to_setup as $module_setup_slug ) {
				if ( empty( $already_setup[$module_setup_slug] ) ) {
					// Do set up
					$module_objects[$module_setup_slug]->setup();
				}

				$already_setup[$module_setup_slug] = true;
			}

			$to_setup = array();
		}
	}

	public function get_available() {
		if ( ! $cache_modules = get_transient( 'ep_available_modules' ) ) {
			$cache_modules = array();
		}

		$modules = array();
		$modules_root = EP_MODULES_DIR;

		$modules_dir = @opendir( $modules_root);
		$module_files = array();

		if ( $modules_dir ) {
			while (($file = readdir( $modules_dir ) ) !== false ) {
				if ( substr( $file, 0, 1 ) === '.' ) {
					continue;
				}

				// Check one dir deep
				if ( is_dir( $modules_root . '/' . $file ) ) {

					$modules_subdir = @opendir( $modules_root . '/' . $file );

					if ( $modules_subdir ) {
						while ( ( $subfile = readdir( $modules_subdir ) ) !== false ) {
							if ( substr( $subfile, 0, 1 ) === '.' ) {
								continue;
							}

							if ( substr( $subfile, -4 ) === '.php' ) {
								$plugin_files[] = "$file/$subfile";
							}
						}

						closedir( $modules_subdir );
					}
				} else {
					if ( substr( $file, -4 ) === '.php' ) {
						$module_files[] = $file;
					}
				}
			}

			closedir( $modules_dir );
		}

		if ( empty( $module_files ) ) {
			return $modules;
		}

		foreach ( $module_files as $module_file ) {
			$module = include_once( "$modules_root/$module_file" );

			if ( is_a( $module, 'EP_Module' ) ) {
				$modules["$modules_root/$module_file"] = $module->get_slug();
			}
		}

		//set_transient( 'ep_available_modules', $modules, HOUR_IN_SECONDS);

		return $modules;
	}

	/**
	 * Return a singleton instance of the class.
	 *
	 * @return object
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();

			$instance->load_all();
		}

		return $instance;
	}
}

EP_Module_Loader::factory();

<?php
/**
 * Plugin Name: Blox - Shortcodes Addon
 * Plugin URI:  https://www.bloxwp.com
 * Description: Enables the Shortcode Addon for Blox, which enables shortcode positioning
 * Author:      Nick Diego
 * Author URI:  https://www.outermost.co
 * Version:     1.0.1
 * Text Domain: blox-shortcodes
 * Domain Path: languages
 *
 * Blox is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Blox is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Blox. If not, see <http://www.gnu.org/licenses/>.
 */


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Make sure that Genesis is active before enabling the plugin
register_activation_hook( __FILE__ , 'blox_shortcodes_activation_check' );

/**
 * This function runs on plugin activation. It checks to make sure the required
 * minimum Blox version is installed. If not, it deactivates the plugin.
 *
 * @since 1.0.0
 */
function blox_shortcodes_activation_check() {

    if ( ! class_exists( 'Blox_Main' ) || class_exists( 'Blox_Shortcodes_Main' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) ); // Deactivate plugin
        wp_die( sprintf( __( 'Sorry, you can\'t activate the %1$sShortcodes Add-on%2$s unless you have Blox installed. Go back to the %3$sPlugins Page%4$s.', 'blox-shortcodes' ), '<em>', '</em>', '<a href="javascript:history.back()">', '</a>' ) );
    }

    $blox_version = Blox_Main::get_instance()->version;

    if ( version_compare( $blox_version, '1.4.0', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) ); // Deactivate plugin
        wp_die( sprintf( __( 'Sorry, you can\'t activate %1$sShortcodes Add-on%2$s unless you have Blox v1.4.0+ installed. You are currently running Blox v%3$s. Go back to the %4$sPlugins Page%5$s.', 'blox-shortcodes' ), '<em>', '</em>', $blox_version, '<a href="javascript:history.back()">', '</a>' ) );
    }
}


add_action( 'plugins_loaded', 'blox_load_shortcodes_addon' );
/**
 * Load the class. Must be called after all plugins are loaded
 *
 * @since 1.0.0
 */
function blox_load_shortcodes_addon() {

	// If Blox is not active or if the addon class already exists, bail...
	if ( ! class_exists( 'Blox_Main' ) || class_exists( 'Blox_Shortcodes_Main' ) ) {
		return;
	} else {
        $blox_version = Blox_Main::get_instance()->version;

        if ( version_compare( $blox_version, '1.3.1', '<' ) ) {
            return;
        }
    }

	/**
	 * Main plugin class.
	 *
	 * @since 1.0.0
	 *
	 * @package Blox
	 * @author  Nick Diego
	 */
	class Blox_Shortcodes_Main {

		/**
		 * Holds the class object.
		 *
		 * @since 1.0.0
		 *
		 * @var object
		 */
		public static $instance;

		/**
		 * Plugin version, used for cache-busting of style and script file references.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $version = '1.0.1';

		/**
		 * The name of the plugin.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $plugin_name = 'Blox - Shortcodes Addon';

		/**
		 * Unique plugin slug identifier.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $plugin_slug = 'blox-shortcodes';

		/**
		 * Plugin file.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $file = __FILE__;

		/**
		 * Primary class constructor.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {

			// Load the plugin textdomain.
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

			// Add additional links to the plugin's row on the admin plugin page
			add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

			// Initialize add-on's license settings field
			add_action( 'init', array( $this, 'license_init' ) );

			// Let Blox know the addon is active
			add_filter( 'blox_get_active_addons', array( $this, 'notify_of_active_addon' ), 10 );

            // Add shortcode settings
            add_action( 'blox_position_settings', array( $this, 'add_shortcode_settings' ), 10, 4 );

            // Add shortcode position format
            add_filter( 'blox_position_formats', array( $this, 'add_position_formats' ) );

            // Add shortcode to global block admin admin column
            add_filter( 'blox_admin_column_output_position', array( $this, 'admin_column_output' ), 10, 4 );

            // Add the blox shortcode
            add_shortcode( 'blox', array( $this, 'display_shortcode' ) );
        }


        /**
		 * Display the shortcode content if tests are passed
		 *
		 * @since 1.0.0
         *
         * @param array $atts All of the accepted shortcode atts
         *
         * @return string     The block output
		 */
        public function display_shortcode( $atts ) {

            // The accepted shortcode atts
            $atts = shortcode_atts( array(
                'id'    => '',
                'title' => '', // The Title does not currently do anything, just helps the user remember what the shortcode is
            ), $atts );

            // Check if there is an id specified
            if ( ! empty( $atts['id'] ) ) {
                $id = esc_attr( $atts['id'] );
            } else {
                return;
            }

            // Define the scope
            if ( strpos( $id, 'global' ) !== false ) {
                $scope = 'global';
            } else if ( strpos( $id, 'local' ) !== false ) {
                $scope = 'local';
            } else {
                return;
            }

            // Trim the id to remove the scope
            $id = substr( $id, strlen( $scope ) + 1 );

            // Get the global and local enable flags
            $global_enable = blox_get_option( 'global_enable', false );
            $local_enable  = blox_get_option( 'local_enable', false );

            // Get the block data
            if ( $scope == 'global' &&  $global_enable ) {

                $block  = get_post_meta( $id, '_blox_content_blocks_data', true );
                $global = true;

                // If there is no block associated with the id given, bail
                if ( empty( $block ) ) {
                    return;
                }

            } else if ( $scope == 'local' && $local_enable && is_singular() ) {

                // Local blocks only run on singular pages, so make sure it is a singular page before proceding and also that local blocks are enabled

                // Get the post type of the current page, and our array of enabled post types
                $post_type     = get_post_type( get_the_ID() );
                $enabled_pages = blox_get_option( 'local_enabled_pages', '' );
                $global 	   = false;

                // Make sure local blocks are allowed on this post type
                if ( ! empty( $enabled_pages ) && in_array( $post_type, $enabled_pages ) ) {

                    // Get all of the Local Content Blocks
                    $local_blocks = get_post_meta( get_the_ID(), '_blox_content_blocks_data', true );

                    // Get the block data, and if there is no local block with that id, bail
                    if ( ! empty( $local_blocks[$id] ) ) {
                        $block = $local_blocks[$id];
                    } else {
                        return;
                    }
                }
            } else {
                return;
            }

            // Check to make sure the position format it set to shortcode, and if not, don't show the content
            $position_format = ! empty( $block['position']['position_format'] ) ? esc_attr( $block['position']['position_format'] ) : '';
            if ( $position_format != 'shortcode' ) {
                return;
            }

            // The display test begins as true
            $display_test = true;

            // Let all available tests filter the test parameter
            $display_test = apply_filters( 'blox_display_test', $display_test, $id, $block, $global );

            // If the test parameter is still true, proceed with block positioning
            if ( $display_test == true ) {

                $output = '';

                // Make sure the function exists before printing the content, causes conflict with some plugins (Yoast SEO Premium)
                if ( function_exists( 'blox_frontend_content' ) ) {

                    // We need to use output buffering here to ensure the slider content is contained in the wrapper div
                    ob_start();
                    blox_frontend_content( null, array( $id, $block, $global ) );
                    $output = ob_get_clean();
                }

                return $output;
            }
        }


        /**
         * Add the shortcode settings to the Position tab
         *
         * @since 1.0.0
         *
         * @param int $id             The block id
         * @param string $name_prefix The prefix for saving each setting
         * @param string $get_prefix  The prefix for retrieving each setting
         * @param bool $global        The block state
         */
        public function add_shortcode_settings( $id, $name_prefix, $get_prefix, $global ) {

            $scope = $global ? "global" : 'local';
            $block = get_post( $id );

            $style = 'width: 100%;padding: 10px;background-color: #fafafa;border: 1px solid #dfdfdf;box-sizing: border-box;font-family: Menlo, Consolas, \'DejaVu Sans Mono\', monospace;margin-bottom: 15px;';

            ?>
            <table class="form-table blox-position-format-type shortcode">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo __( 'Shortcode', 'blox' ); ?></th>
                        <td>
                            <div class="shortcode-display" style="<?php echo $style;?>">[blox id="<?php echo $scope . '_' . $id; ?>"]</div>
                            <div class="blox-description">
                                <?php
                                    _e( 'Copy and paste this above shortcode anywhere that accepts a shortcode. Visibility and location settings are respected when using shortcode positioning.', 'blox-shortcodes' );
                                    if ( ! $global ) {
                                        echo ' ' . sprintf( __( 'Also note that regardless of position type, local blocks will %1$sonly%2$s display on the page, post, or custom post type that they were created on.', 'blox-shortcodes' ), '<strong>', '</strong>' );
                                    }
                                ?>
                            </div>
                            <?php

                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php
        }


        /**
         * Preview the shortcode in the global block position admin column
         *
         * @since 1.0.0
         *
         * @param string $output          What we want to show in the admin column
         * @param string $position_format The position format
         * @param string $id              The block id
         * @param array $block_data       Array of all block data
         *
         * @return string                 A preview of the shortcode
         */
        public function admin_column_output( $output, $position_format, $id, $block_data ) {

            if ( $position_format == 'shortcode' ) {
                return '<div class="shortcode-display" style="font-family: Menlo, Consolas, \'DejaVu Sans Mono\', monospace;">[blox id="global_' . $id .'"]</div>';
            }
        }


        /**
         * Add the shortcode position format
         *
         * @since 1.0.0
         *
         * @param array $formats  Array of all custom position formats
         *
         * @return array $formats An update array of formats with shortcode added
         */
        public function add_position_formats( $formats ) {

            $formats['shortcode'] = __( 'Shortcode', 'blox' );

            return $formats;
        }


		/**
		 * Loads the plugin textdomain for translation.
		 *
		 * @since 1.0.0
		 */
		public function load_textdomain() {
			load_plugin_textdomain( $this->plugin_slug, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}


		/**
		 * Adds additional links to the plugin row meta links
		 *
		 * @since 1.0.0
		 *
		 * @param array $links   Already defined meta links
		 * @param string $file   Plugin file path and name being processed
		 *
		 * @return array $links  The new array of meta links
		 */
		function plugin_row_meta( $links, $file ) {

			// If we are not on the correct plugin, abort
			if ( $file != 'blox-shortcodes/blox-shortcodes.php' ) {
				return $links;
			}

			$docs_link = esc_url( add_query_arg( array(
					'utm_source'   => 'admin-plugins-page',
					'utm_medium'   => 'plugin',
					'utm_campaign' => 'BloxPluginsPage',
					'utm_content'  => 'plugin-page-link'
				), 'https://www.bloxwp.com/documentation/shortcode-positioning' )
			);

			$new_links = array(
				'<a href="' . $docs_link . '">' . esc_html__( 'Documentation', 'blox-shortcodes' ) . '</a>',
			);

			$links = array_merge( $links, $new_links );

			return $links;
		}


		/**
		 * Load license settings
		 *
		 * @since 1.0.0
		 */
		public function license_init() {

			// Setup the license
			if ( class_exists( 'Blox_License' ) ) {
				$blox_shortcodes_addon_license = new Blox_License( __FILE__, 'Shortcodes Addon', '1.0.0', 'Nicholas Diego', 'blox_shortcodes_addon_license_key', 'https://www.bloxwp.com', 'addons' );
			}
		}


		/**
		 * Let Blox know this add-on has been activated.
		 *
		 * @since 1.0.0
		 */
		public function notify_of_active_addon( $addons ) {

			$addons['shortcodes_addon'] = __( 'Shortcode Add-on', 'blox-shortcodes' );
			return $addons;
		}


		/**
		 * Returns the singleton instance of the class.
		 *
		 * @since 1.0.0
		 *
		 * @return object The class object.
		 */
		public static function get_instance() {

			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Blox_Shortcodes_Main ) ) {
				self::$instance = new Blox_Shortcodes_Main();
			}

			return self::$instance;
		}
	}

	// Load the main plugin class.
	$Blox_Shortcodes_Main = Blox_Shortcodes_Main::get_instance();
}

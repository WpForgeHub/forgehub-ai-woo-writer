<?php
/**
 * Plugin Name:       ForgeHub AI Woo Writer
 * Plugin URI:        https://wpforgehub.com/product/ai-woo-writer/
 * Description:       BYOK AI product writer for WooCommerce. Generate SEO descriptions, short descriptions, and feature bullets with preview and one-click apply.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WPForgeHub
 * Author URI:        https://wpforgehub.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       forgehub-ai-woo-writer
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPFH_AWW_VERSION', '1.0.0' );
define( 'WPFH_AWW_FILE', __FILE__ );
define( 'WPFH_AWW_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPFH_AWW_URL', plugin_dir_url( __FILE__ ) );

// <wpfh-pro-only>
/**
 * Freemius SDK helper wrapper (included only in the Pro build).
 *
 * @return object
 */
if ( ! function_exists( 'wpfh_aww_fs' ) ) {
	function wpfh_aww_fs() {
		global $wpfh_aww_fs;

		if ( ! isset( $wpfh_aww_fs ) ) {
			$fs_config = apply_filters(
				'wpfh_aww_fs_config',
				array(
					'id'         => get_option( 'wpfh_aww_fs_id', '' ),
					'public_key' => get_option( 'wpfh_aww_fs_public_key', '' ),
				)
			);
			$force_pro = defined( 'WPFH_AWW_FORCE_PRO' ) && WPFH_AWW_FORCE_PRO;
			$fs_path   = WPFH_AWW_PATH . 'freemius/start.php';

			if ( ! $force_pro && ! empty( $fs_config['id'] ) && ! empty( $fs_config['public_key'] ) && file_exists( $fs_path ) ) {
				require_once $fs_path;
				$wpfh_aww_fs = fs_dynamic_init(
					array(
						'id'             => (string) $fs_config['id'],
						'slug'           => 'forgehub-ai-woo-writer',
						'type'           => 'plugin',
						'public_key'     => (string) $fs_config['public_key'],
						'is_premium'     => false,
						'has_addons'     => false,
						'has_paid_plans' => true,
						'menu'           => array(
							'slug'       => 'forgehub-ai-woo-writer',
							'first-path' => 'options-general.php?page=forgehub-ai-woo-writer',
							'parent'     => array(
								'slug' => 'options-general.php',
							),
						),
					)
				);
				$wpfh_aww_fs->add_action( 'after_uninstall', 'wpfh_aww_uninstall_cleanup' );
			} else {
				$wpfh_aww_fs = new class( $force_pro ) {
					private $force_pro;

					public function __construct( $force_pro = false ) {
						$this->force_pro = (bool) $force_pro;
					}

					public function can_use_premium_code() {
						if ( $this->force_pro ) {
							return true;
						}
						// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
						return (bool) apply_filters( 'fs_can_use_premium_code__forgehub-ai-woo-writer', false );
					}

					public function is__premium_only() {
						return $this->can_use_premium_code();
					}

					public function is_plan( $plan ) {
						// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
						return (bool) apply_filters( 'fs_is_plan__forgehub-ai-woo-writer', false, $plan );
					}

					public function get_upgrade_url() {
						return 'https://wpforgehub.com/product/ai-woo-writer/#pricing';
					}
				};
			}
		}

		return $wpfh_aww_fs;
	}

	wpfh_aww_fs();
}

/**
 * Cleanup plugin data on uninstall (Pro build via after_uninstall hook).
 *
 * @return void
 */
function wpfh_aww_uninstall_cleanup() {
	delete_option( 'wpfh_aww_settings' );
	delete_option( 'wpfh_aww_templates' );
	delete_option( 'wpfh_aww_review_nudge' );
}
// </wpfh-pro-only>

/**
 * Return the canonical upgrade URL.
 *
 * @return string
 */
function wpfh_aww_get_upgrade_url() {
	$url = 'https://wpforgehub.com/product/ai-woo-writer/#pricing';
	// <wpfh-pro-only>
	if ( function_exists( 'wpfh_aww_fs' ) ) {
		$url = wpfh_aww_fs()->get_upgrade_url();
	}
	// </wpfh-pro-only>
	return $url;
}

/**
 * Check whether Pro features are available and licensed.
 *
 * @return bool
 */
function wpfh_aww_is_pro() {
	$is_pro = false;
	// <wpfh-pro-only>
	$is_pro = file_exists( WPFH_AWW_PATH . 'includes/pro/class-wpfh-aww-pro.php' )
		&& function_exists( 'wpfh_aww_fs' )
		&& wpfh_aww_fs()->can_use_premium_code();
	// </wpfh-pro-only>
	return $is_pro;
}

/**
 * Main Plugin Bootstrap Class.
 */
final class WPFH_AI_Woo_Writer {

	/**
	 * Singleton instance.
	 *
	 * @var WPFH_AI_Woo_Writer|null
	 */
	private static $instance = null;

	/**
	 * Review nudge instance.
	 *
	 * @var WPFH_Review_Nudge|null
	 */
	private $review_nudge = null;

	/**
	 * Return singleton instance.
	 *
	 * @return WPFH_AI_Woo_Writer
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 10 );
		add_filter( 'plugin_action_links_' . plugin_basename( WPFH_AWW_FILE ), array( $this, 'action_links' ) );
		register_activation_hook( WPFH_AWW_FILE, array( $this, 'on_activate' ) );
	}

	/**
	 * Declare WooCommerce High-Performance Order Storage (HPOS) compatibility.
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WPFH_AWW_FILE, true );
		}
	}

	/**
	 * Initialize default options on first activation.
	 *
	 * @return void
	 */
	public function on_activate() {
		require_once WPFH_AWW_PATH . 'includes/class-wpfh-aww-settings.php';
		if ( false === get_option( WPFH_AWW_Settings::OPTION_NAME, false ) ) {
			add_option( WPFH_AWW_Settings::OPTION_NAME, WPFH_AWW_Settings::get_defaults() );
		}
	}

	/**
	 * Initialize plugin modules.
	 *
	 * @return void
	 */
	public function init() {
		require_once WPFH_AWW_PATH . 'includes/class-wpfh-review-nudge.php';
		require_once WPFH_AWW_PATH . 'includes/class-wpfh-aww-settings.php';
		require_once WPFH_AWW_PATH . 'includes/class-wpfh-aww-ai.php';
		require_once WPFH_AWW_PATH . 'includes/class-wpfh-aww-admin.php';

		$this->review_nudge = new WPFH_Review_Nudge(
			array(
				'plugin_slug'   => 'forgehub-ai-woo-writer',
				'plugin_name'   => 'ForgeHub AI Woo Writer',
				'option_prefix' => 'wpfh_aww',
				'github_url'    => 'https://github.com/WpForgeHub/forgehub-ai-woo-writer',
				'min_actions'   => 5,
				'min_days'      => 7,
				'trigger_mode'  => 'or',
				'screens'       => array( 'settings_page_forgehub-ai-woo-writer', 'product' ),
			)
		);
		$this->review_nudge->register();

		WPFH_AWW_Admin::get_instance();

		// <wpfh-pro-only>
		if ( wpfh_aww_is_pro() ) {
			$pro_file = WPFH_AWW_PATH . 'includes/pro/class-wpfh-aww-pro.php';
			if ( file_exists( $pro_file ) ) {
				require_once $pro_file;
				WPFH_AWW_Pro::get_instance();
			}
		}
		// </wpfh-pro-only>
	}

	/**
	 * Return review nudge instance.
	 *
	 * @return WPFH_Review_Nudge|null
	 */
	public function get_review_nudge() {
		return $this->review_nudge;
	}

	/**
	 * Add Settings and calm Priority Support & Pro links to the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function action_links( $links ) {
		$settings_url = admin_url( 'options-general.php?page=forgehub-ai-woo-writer' );
		$custom_links = array(
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'forgehub-ai-woo-writer' ) . '</a>',
		);

		if ( ! wpfh_aww_is_pro() ) {
			$custom_links[] = '<a href="' . esc_url( wpfh_aww_get_upgrade_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Priority Support & Pro', 'forgehub-ai-woo-writer' ) . '</a>';
		}

		return array_merge( $custom_links, $links );
	}
}

WPFH_AI_Woo_Writer::get_instance();

<?php
/**
 * Admin UI, Product Metabox, and AJAX handlers for ForgeHub AI Woo Writer.
 *
 * @package ForgeHub_AI_Woo_Writer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPFH_AWW_Admin
 */
class WPFH_AWW_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var WPFH_AWW_Admin|null
	 */
	private static $instance = null;

	/**
	 * Return singleton instance.
	 *
	 * @return WPFH_AWW_Admin
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_product_metabox' ) );

		add_action( 'wp_ajax_wpfh_aww_generate_single', array( $this, 'ajax_generate_single' ) );
		add_action( 'wp_ajax_wpfh_aww_apply_single', array( $this, 'ajax_apply_single' ) );
		add_action( 'wp_ajax_wpfh_aww_test_connection', array( $this, 'ajax_test_connection' ) );
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Register Settings submenu page.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'ForgeHub AI Woo Writer', 'forgehub-ai-woo-writer' ),
			__( 'AI Woo Writer', 'forgehub-ai-woo-writer' ),
			'manage_options',
			'forgehub-ai-woo-writer',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'wpfh_aww_settings_group',
			WPFH_AWW_Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WPFH_AWW_Settings', 'sanitize' ),
			)
		);
	}

	/**
	 * Register single-product edit metabox.
	 *
	 * @return void
	 */
	public function register_product_metabox() {
		if ( ! post_type_exists( 'product' ) ) {
			return;
		}

		add_meta_box(
			'wpfh_aww_product_metabox',
			__( 'ForgeHub AI Woo Writer (BYOK)', 'forgehub-ai-woo-writer' ),
			array( $this, 'render_product_metabox' ),
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue admin CSS and JS with filemtime() cache-busting.
	 *
	 * @param string $hook Current admin screen hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_settings = ( 'settings_page_forgehub-ai-woo-writer' === $hook );
		$is_product  = ( is_object( $screen ) && 'product' === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) );

		if ( ! $is_settings && ! $is_product ) {
			return;
		}

		$css_path = WPFH_AWW_PATH . 'assets/wpfh-aww-admin.css';
		$js_path  = WPFH_AWW_PATH . 'assets/wpfh-aww-admin.js';

		wp_enqueue_style(
			'wpfh-aww-admin',
			WPFH_AWW_URL . 'assets/wpfh-aww-admin.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : WPFH_AWW_VERSION
		);

		wp_enqueue_script(
			'wpfh-aww-admin',
			WPFH_AWW_URL . 'assets/wpfh-aww-admin.js',
			array(),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : WPFH_AWW_VERSION,
			true
		);

		$settings  = WPFH_AWW_Settings::get_all();
		$providers = WPFH_AWW_AI::get_providers();

		wp_localize_script(
			'wpfh-aww-admin',
			'wpfhAwwData',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'wpfh_aww_ajax_nonce' ),
				'isPro'        => wpfh_aww_is_pro(),
				'providers'    => $providers,
				'savedModel'   => $settings['model'],
				'settingsUrl'  => admin_url( 'options-general.php?page=forgehub-ai-woo-writer' ),
				'i18n'         => array(
					'generating'      => __( 'Generating SEO product copy...', 'forgehub-ai-woo-writer' ),
					'generated'       => __( 'Preview ready. Review the generated copy below and apply in one click.', 'forgehub-ai-woo-writer' ),
					'appliedEditor'   => __( 'Applied to product editor fields. Click Update/Publish when ready.', 'forgehub-ai-woo-writer' ),
					'savingProduct'   => __( 'Saving directly to product...', 'forgehub-ai-woo-writer' ),
					'savedProduct'    => __( 'Product descriptions saved successfully.', 'forgehub-ai-woo-writer' ),
					'testing'         => __( 'Testing BYOK provider connection...', 'forgehub-ai-woo-writer' ),
					'selectField'     => __( 'Select at least one output field to generate.', 'forgehub-ai-woo-writer' ),
					'requestFailed'   => __( 'Request failed. Please verify your API key and try again.', 'forgehub-ai-woo-writer' ),
					'loadingProducts' => __( 'Loading matching products...', 'forgehub-ai-woo-writer' ),
					'noProducts'      => __( 'No products matched the selected filters.', 'forgehub-ai-woo-writer' ),
					'selectProduct'   => __( 'Select at least one product to process.', 'forgehub-ai-woo-writer' ),
					'queueRunning'    => __( 'Batch queue running...', 'forgehub-ai-woo-writer' ),
					'queueStopped'    => __( 'Batch queue stopped by user.', 'forgehub-ai-woo-writer' ),
					'queueComplete'   => __( 'Batch queue completed.', 'forgehub-ai-woo-writer' ),
					'templateSaved'   => __( 'Brand voice template saved.', 'forgehub-ai-woo-writer' ),
					'templateDeleted' => __( 'Brand voice template deleted.', 'forgehub-ai-woo-writer' ),
					'templateEmpty'   => __( 'Template name and prompt instructions are required.', 'forgehub-ai-woo-writer' ),
					'getApiKey'       => __( 'Get API Key', 'forgehub-ai-woo-writer' ),
				),
			)
		);
	}

	/**
	 * Render main settings page (and Pro panels via hook).
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = WPFH_AWW_Settings::get_all();
		$providers  = WPFH_AWW_AI::get_providers();
		$tones      = WPFH_AWW_Settings::get_tones();
		$is_pro     = wpfh_aww_is_pro();
		$woo_active = self::is_woocommerce_active();
		$has_key    = '' !== trim( (string) $settings['api_key'] );
		$active_p   = isset( $providers[ $settings['provider'] ] ) ? $providers[ $settings['provider'] ] : $providers['openai'];
		?>
		<div class="wrap wpfh-aww-wrap">
			<div class="wpfh-aww-header">
				<div>
					<h1><?php esc_html_e( 'ForgeHub AI Woo Writer', 'forgehub-ai-woo-writer' ); ?></h1>
					<p class="wpfh-aww-subtitle">
						<?php esc_html_e( 'Generate conversion-focused WooCommerce product descriptions, short summaries, and feature bullets using your own AI API key (BYOK).', 'forgehub-ai-woo-writer' ); ?>
					</p>
				</div>
				<span class="wpfh-aww-badge <?php echo $is_pro ? 'wpfh-aww-badge-pro' : 'wpfh-aww-badge-free'; ?>">
					<?php echo $is_pro ? esc_html__( 'Pro Active', 'forgehub-ai-woo-writer' ) : esc_html__( 'Free Edition', 'forgehub-ai-woo-writer' ); ?>
				</span>
			</div>

			<?php if ( ! $woo_active ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'WooCommerce is not active. Activate WooCommerce to use the product editor AI writer.', 'forgehub-ai-woo-writer' ); ?></p>
				</div>
			<?php endif; ?>

			<div id="wpfh-aww-feedback" class="wpfh-aww-feedback" role="status" aria-live="polite" hidden></div>

			<div class="wpfh-aww-card">
				<h2><?php esc_html_e( 'BYOK AI Provider & Writing Defaults', 'forgehub-ai-woo-writer' ); ?></h2>
				<form method="post" action="options.php">
					<?php settings_fields( 'wpfh_aww_settings_group' ); ?>
					<div class="wpfh-aww-grid">
						<div class="wpfh-aww-field">
							<label for="wpfh-aww-provider"><?php esc_html_e( 'AI Provider', 'forgehub-ai-woo-writer' ); ?></label>
							<select id="wpfh-aww-provider" name="<?php echo esc_attr( WPFH_AWW_Settings::OPTION_NAME ); ?>[provider]">
								<?php foreach ( $providers as $key => $info ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['provider'], $key ); ?>>
										<?php echo esc_html( $info['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="wpfh-aww-help" id="wpfh-aww-key-link-wrap">
								<?php if ( ! empty( $active_p['key_url'] ) ) : ?>
									<a id="wpfh-aww-key-link" href="<?php echo esc_url( $active_p['key_url'] ); ?>" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e( 'Get API Key', 'forgehub-ai-woo-writer' ); ?> &rarr;
									</a>
								<?php endif; ?>
							</p>
						</div>

						<div class="wpfh-aww-field">
							<label for="wpfh-aww-api-key"><?php esc_html_e( 'API Key (Stored Locally)', 'forgehub-ai-woo-writer' ); ?></label>
							<input
								type="password"
								id="wpfh-aww-api-key"
								name="<?php echo esc_attr( WPFH_AWW_Settings::OPTION_NAME ); ?>[api_key]"
								value=""
								autocomplete="new-password"
								placeholder="<?php echo $has_key ? esc_attr__( 'Saved (leave blank to keep existing key)', 'forgehub-ai-woo-writer' ) : esc_attr__( 'sk-... / AIza... / sk-ant-...', 'forgehub-ai-woo-writer' ); ?>"
							/>
							<?php if ( $has_key ) : ?>
								<label class="wpfh-aww-inline-check">
									<input type="checkbox" name="<?php echo esc_attr( WPFH_AWW_Settings::OPTION_NAME ); ?>[clear_api_key]" value="1" />
									<?php esc_html_e( 'Clear saved API key', 'forgehub-ai-woo-writer' ); ?>
								</label>
							<?php endif; ?>
						</div>

						<div class="wpfh-aww-field">
							<label for="wpfh-aww-model"><?php esc_html_e( 'Preset Model', 'forgehub-ai-woo-writer' ); ?></label>
							<select id="wpfh-aww-model" name="<?php echo esc_attr( WPFH_AWW_Settings::OPTION_NAME ); ?>[model]">
								<?php foreach ( $active_p['models'] as $m_id => $m_label ) : ?>
									<option value="<?php echo esc_attr( $m_id ); ?>" <?php selected( $settings['model'], $m_id ); ?>>
										<?php echo esc_html( $m_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="wpfh-aww-field">
							<label for="wpfh-aww-custom-model"><?php esc_html_e( 'Custom Model ID (Optional Override)', 'forgehub-ai-woo-writer' ); ?></label>
							<input
								type="text"
								id="wpfh-aww-custom-model"
								name="<?php echo esc_attr( WPFH_AWW_Settings::OPTION_NAME ); ?>[custom_model]"
								value="<?php echo esc_attr( $settings['custom_model'] ); ?>"
								placeholder="<?php esc_attr_e( 'e.g. gpt-4.1-mini or claude-sonnet-4', 'forgehub-ai-woo-writer' ); ?>"
							/>
						</div>

						<div class="wpfh-aww-field" id="wpfh-aww-endpoint-wrap" <?php echo 'custom' === $settings['provider'] ? '' : 'hidden'; ?>>
							<label for="wpfh-aww-custom-endpoint"><?php esc_html_e( 'Custom OpenAI-Compatible Base URL', 'forgehub-ai-woo-writer' ); ?></label>
							<input
								type="url"
								id="wpfh-aww-custom-endpoint"
								name="<?php echo esc_attr( WPFH_AWW_Settings::OPTION_NAME ); ?>[custom_endpoint]"
								value="<?php echo esc_attr( $settings['custom_endpoint'] ); ?>"
								placeholder="https://api.groq.com/openai/v1"
							/>
						</div>

						<div class="wpfh-aww-field">
							<label for="wpfh-aww-tone"><?php esc_html_e( 'Default Writing Tone', 'forgehub-ai-woo-writer' ); ?></label>
							<select id="wpfh-aww-tone" name="<?php echo esc_attr( WPFH_AWW_Settings::OPTION_NAME ); ?>[default_tone]">
								<?php foreach ( $tones as $t_key => $t_label ) : ?>
									<option value="<?php echo esc_attr( $t_key ); ?>" <?php selected( $settings['default_tone'], $t_key ); ?>>
										<?php echo esc_html( $t_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="wpfh-aww-field wpfh-aww-checkbox-field">
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( WPFH_AWW_Settings::OPTION_NAME ); ?>[overwrite_existing]"
									value="1"
									<?php checked( 1, (int) $settings['overwrite_existing'] ); ?>
								/>
								<?php esc_html_e( 'Allow overwriting non-empty descriptions by default', 'forgehub-ai-woo-writer' ); ?>
							</label>
						</div>

						<?php do_action( 'wpfh_aww_pro_settings_fields', $settings ); ?>
					</div>

					<div class="wpfh-aww-action-bar">
						<?php submit_button( __( 'Save Settings', 'forgehub-ai-woo-writer' ), 'primary', 'submit', false ); ?>
						<button type="button" id="wpfh-aww-test-btn" class="button button-secondary">
							<?php esc_html_e( 'Test API Connection', 'forgehub-ai-woo-writer' ); ?>
						</button>
					</div>
				</form>
			</div>

			<?php do_action( 'wpfh_aww_pro_panels', $settings ); ?>

			<div class="wpfh-aww-card">
				<h2><?php esc_html_e( 'Quick Start', 'forgehub-ai-woo-writer' ); ?></h2>
				<ol class="wpfh-aww-steps">
					<li><?php esc_html_e( 'Select your preferred AI provider above (OpenAI, Anthropic Claude, Google Gemini, OpenRouter, DeepSeek, or Custom) and paste your API key.', 'forgehub-ai-woo-writer' ); ?></li>
					<li><?php esc_html_e( 'Open any WooCommerce product under Products > Edit.', 'forgehub-ai-woo-writer' ); ?></li>
					<li><?php esc_html_e( 'Use the ForgeHub AI Woo Writer metabox to choose fields, enter focus keywords, preview the generated copy, and apply it in one click.', 'forgehub-ai-woo-writer' ); ?></li>
				</ol>
			</div>

			<?php if ( ! $is_pro ) : ?>
				<p class="wpfh-aww-calm-footer">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: link to WPForgeHub Pro and priority support page */
							__( 'Need bulk category batch generation, custom brand voice prompt templates, multi-language output, or SEO meta sync? Explore %s.', 'forgehub-ai-woo-writer' ),
							'<a href="' . esc_url( wpfh_aww_get_upgrade_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Priority Support & Pro', 'forgehub-ai-woo-writer' ) . '</a>'
						),
						array(
							'a' => array(
								'href'   => array(),
								'target' => array(),
								'rel'    => array(),
							),
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render single-product edit screen metabox.
	 *
	 * @param WP_Post $post Current product post object.
	 * @return void
	 */
	public function render_product_metabox( $post ) {
		$settings = WPFH_AWW_Settings::get_all();
		$tones    = WPFH_AWW_Settings::get_tones();
		$has_key  = '' !== trim( (string) $settings['api_key'] ) || 'custom' === $settings['provider'];
		?>
		<div class="wpfh-aww-metabox" data-product-id="<?php echo esc_attr( (string) $post->ID ); ?>">
			<?php if ( ! $has_key ) : ?>
				<div class="wpfh-aww-inline-notice">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: link to plugin settings page */
							__( 'No BYOK API key configured yet. Add your key under %s to start generating copy.', 'forgehub-ai-woo-writer' ),
							'<a href="' . esc_url( admin_url( 'options-general.php?page=forgehub-ai-woo-writer' ) ) . '">' . esc_html__( 'Settings > AI Woo Writer', 'forgehub-ai-woo-writer' ) . '</a>'
						),
						array(
							'a' => array(
								'href' => array(),
							),
						)
					);
					?>
				</div>
			<?php endif; ?>

			<div id="wpfh-aww-meta-feedback" class="wpfh-aww-feedback" role="status" aria-live="polite" hidden></div>

			<div class="wpfh-aww-grid wpfh-aww-meta-grid">
				<div class="wpfh-aww-field">
					<span class="wpfh-aww-label"><?php esc_html_e( 'Fields to Generate', 'forgehub-ai-woo-writer' ); ?></span>
					<div class="wpfh-aww-checks">
						<label><input type="checkbox" class="wpfh-aww-field-cb" value="long_description" checked /> <?php esc_html_e( 'SEO Long Description', 'forgehub-ai-woo-writer' ); ?></label>
						<label><input type="checkbox" class="wpfh-aww-field-cb" value="short_description" checked /> <?php esc_html_e( 'Short Description', 'forgehub-ai-woo-writer' ); ?></label>
						<label><input type="checkbox" class="wpfh-aww-field-cb" value="bullets" checked /> <?php esc_html_e( 'Feature Bullets', 'forgehub-ai-woo-writer' ); ?></label>
					</div>
				</div>

				<div class="wpfh-aww-field">
					<label for="wpfh-aww-meta-tone"><?php esc_html_e( 'Writing Tone', 'forgehub-ai-woo-writer' ); ?></label>
					<select id="wpfh-aww-meta-tone">
						<?php foreach ( $tones as $t_key => $t_label ) : ?>
							<option value="<?php echo esc_attr( $t_key ); ?>" <?php selected( $settings['default_tone'], $t_key ); ?>>
								<?php echo esc_html( $t_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wpfh-aww-field">
					<label for="wpfh-aww-meta-keywords"><?php esc_html_e( 'Focus SEO Keywords / Product Notes', 'forgehub-ai-woo-writer' ); ?></label>
					<input
						type="text"
						id="wpfh-aww-meta-keywords"
						placeholder="<?php esc_attr_e( 'e.g. waterproof, organic cotton, 2-year warranty', 'forgehub-ai-woo-writer' ); ?>"
					/>
				</div>

				<div class="wpfh-aww-field wpfh-aww-checkbox-field">
					<label>
						<input
							type="checkbox"
							id="wpfh-aww-meta-overwrite"
							value="1"
							<?php checked( 1, (int) $settings['overwrite_existing'] ); ?>
						/>
						<?php esc_html_e( 'Overwrite existing non-empty fields', 'forgehub-ai-woo-writer' ); ?>
					</label>
				</div>

				<?php do_action( 'wpfh_aww_pro_metabox_fields', $post, $settings ); ?>
			</div>

			<div class="wpfh-aww-action-bar">
				<button type="button" id="wpfh-aww-generate-btn" class="button button-primary">
					<?php esc_html_e( 'Generate & Preview Copy', 'forgehub-ai-woo-writer' ); ?>
				</button>
			</div>

			<div id="wpfh-aww-preview-box" class="wpfh-aww-preview-box" hidden>
				<h3><?php esc_html_e( 'Generated Copy Preview', 'forgehub-ai-woo-writer' ); ?></h3>
				<div class="wpfh-aww-field">
					<label for="wpfh-aww-prev-long"><?php esc_html_e( 'SEO Long Description (HTML)', 'forgehub-ai-woo-writer' ); ?></label>
					<textarea id="wpfh-aww-prev-long" rows="6"></textarea>
				</div>
				<div class="wpfh-aww-field">
					<label for="wpfh-aww-prev-short"><?php esc_html_e( 'Short Description', 'forgehub-ai-woo-writer' ); ?></label>
					<textarea id="wpfh-aww-prev-short" rows="3"></textarea>
				</div>
				<div class="wpfh-aww-field">
					<label for="wpfh-aww-prev-bullets"><?php esc_html_e( 'Key Feature Bullets (one per line)', 'forgehub-ai-woo-writer' ); ?></label>
					<textarea id="wpfh-aww-prev-bullets" rows="4"></textarea>
				</div>
				<div class="wpfh-aww-field" id="wpfh-aww-prev-seo-wrap" <?php echo wpfh_aww_is_pro() ? '' : 'hidden'; ?>>
					<label for="wpfh-aww-prev-seo"><?php esc_html_e( 'SEO Meta Description', 'forgehub-ai-woo-writer' ); ?></label>
					<input type="text" id="wpfh-aww-prev-seo" />
				</div>

				<div class="wpfh-aww-action-bar">
					<button type="button" id="wpfh-aww-apply-editor-btn" class="button button-primary">
						<?php esc_html_e( 'Apply to Editor (One-Click)', 'forgehub-ai-woo-writer' ); ?>
					</button>
					<button type="button" id="wpfh-aww-apply-save-btn" class="button button-secondary">
						<?php esc_html_e( 'Save Directly to Product', 'forgehub-ai-woo-writer' ); ?>
					</button>
					<label class="wpfh-aww-inline-check">
						<input type="checkbox" id="wpfh-aww-append-bullets" value="1" checked />
						<?php esc_html_e( 'Append bullets list to Long Description', 'forgehub-ai-woo-writer' ); ?>
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Build combined HTML long description with optional bullets list.
	 *
	 * @param string $long_html      Long description HTML.
	 * @param array  $bullets        Bullet points array.
	 * @param bool   $append_bullets Whether to append bullets as <ul>.
	 * @return string
	 */
	public static function compose_long_description( $long_html, $bullets = array(), $append_bullets = true ) {
		$html = wp_kses_post( (string) $long_html );
		if ( $append_bullets && ! empty( $bullets ) && is_array( $bullets ) ) {
			$items = array();
			foreach ( $bullets as $bullet ) {
				$clean = sanitize_text_field( (string) $bullet );
				if ( '' !== $clean ) {
					$items[] = '<li>' . esc_html( $clean ) . '</li>';
				}
			}
			if ( ! empty( $items ) ) {
				$heading = '<h3>' . esc_html__( 'Key Features', 'forgehub-ai-woo-writer' ) . '</h3>';
				$html    = trim( $html . "\n\n" . $heading . "\n<ul>\n" . implode( "\n", $items ) . "\n</ul>" );
			}
		}
		return $html;
	}

	/**
	 * Apply generated copy to a WooCommerce product via CRUD APIs.
	 *
	 * @param WC_Product $product   Product object.
	 * @param array      $copy_data Copy payload (long_description, short_description, bullets, seo_meta_description).
	 * @param array      $options   Apply options (fields, overwrite, append_bullets, sync_seo_meta).
	 * @return array Summary of updated fields.
	 */
	public static function apply_copy_to_product( $product, $copy_data, $options = array() ) {
		$fields         = ! empty( $options['fields'] ) && is_array( $options['fields'] ) ? $options['fields'] : array( 'long_description', 'short_description', 'bullets' );
		$overwrite      = ! empty( $options['overwrite'] );
		$append_bullets = ! isset( $options['append_bullets'] ) || ! empty( $options['append_bullets'] );
		$updated_fields = array();

		$bullets = isset( $copy_data['bullets'] ) && is_array( $copy_data['bullets'] ) ? $copy_data['bullets'] : array();

		if ( in_array( 'long_description', $fields, true ) || in_array( 'bullets', $fields, true ) ) {
			$current_long = trim( (string) $product->get_description() );
			if ( $overwrite || '' === $current_long ) {
				$new_long = in_array( 'long_description', $fields, true ) ? (string) ( $copy_data['long_description'] ?? '' ) : $current_long;
				$use_list = in_array( 'bullets', $fields, true ) && $append_bullets;
				$composed = self::compose_long_description( $new_long, $use_list ? $bullets : array(), $use_list );
				if ( '' !== $composed ) {
					$product->set_description( $composed );
					$updated_fields[] = 'long_description';
				}
			}
		}

		if ( in_array( 'short_description', $fields, true ) ) {
			$current_short = trim( (string) $product->get_short_description() );
			$new_short     = sanitize_textarea_field( (string) ( $copy_data['short_description'] ?? '' ) );
			if ( '' !== $new_short && ( $overwrite || '' === $current_short ) ) {
				$product->set_short_description( $new_short );
				$updated_fields[] = 'short_description';
			}
		}

		if ( ! empty( $updated_fields ) ) {
			$product->save();
		}

		/**
		 * Fires after copy is applied to a WooCommerce product (used by Pro for SEO meta sync).
		 *
		 * @param WC_Product $product        Product object.
		 * @param array      $copy_data      Generated copy data.
		 * @param array      $options        Apply options.
		 * @param array      $updated_fields Updated field keys.
		 */
		do_action( 'wpfh_aww_after_apply_copy', $product, $copy_data, $options, $updated_fields );

		if ( ! empty( $updated_fields ) && class_exists( 'WPFH_Review_Nudge' ) ) {
			WPFH_Review_Nudge::track_success( 'wpfh_aww', 1 );
		}

		return $updated_fields;
	}

	/**
	 * AJAX: Generate preview copy for a single WooCommerce product.
	 *
	 * @return void
	 */
	public function ajax_generate_single() {
		check_ajax_referer( 'wpfh_aww_ajax_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		if ( ! $product_id || ! current_user_can( 'edit_post', $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied or invalid product ID.', 'forgehub-ai-woo-writer' ) ), 403 );
		}

		if ( ! self::is_woocommerce_active() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce must be active to generate product copy.', 'forgehub-ai-woo-writer' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'forgehub-ai-woo-writer' ) ) );
		}

		$fields = isset( $_POST['fields'] )
			? array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['fields'] ) ), array( 'long_description', 'short_description', 'bullets' ) ) )
			: array( 'long_description', 'short_description', 'bullets' );
		if ( empty( $fields ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one field to generate.', 'forgehub-ai-woo-writer' ) ) );
		}

		$tone     = isset( $_POST['tone'] ) ? sanitize_key( wp_unslash( $_POST['tone'] ) ) : '';
		$keywords = isset( $_POST['keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['keywords'] ) ) : '';
		$language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'English';
		$tpl_id   = isset( $_POST['template_id'] ) ? sanitize_key( wp_unslash( $_POST['template_id'] ) ) : '';

		$options = array(
			'fields'          => $fields,
			'tone'            => $tone,
			'keywords'        => $keywords,
			'language'        => wpfh_aww_is_pro() ? $language : 'English',
			'template_prompt' => '',
		);

		$options = apply_filters( 'wpfh_aww_generation_options', $options, $tpl_id, $product );

		$context = WPFH_AWW_AI::extract_product_context( $product );
		$title_override = isset( $_POST['product_title'] ) ? sanitize_text_field( wp_unslash( $_POST['product_title'] ) ) : '';
		if ( '' !== $title_override ) {
			$context['name'] = $title_override;
		}

		$result = WPFH_AWW_AI::generate_copy( $context, $options );
		if ( empty( $result['success'] ) ) {
			$nudge = WPFH_AI_Woo_Writer::get_instance()->get_review_nudge();
			if ( $nudge ) {
				$nudge->record_error();
			}
			wp_send_json_error( array( 'message' => $result['error'] ?? __( 'AI generation failed.', 'forgehub-ai-woo-writer' ) ) );
		}

		wp_send_json_success( $result['data'] );
	}

	/**
	 * AJAX: Save previewed copy directly to the WooCommerce product.
	 *
	 * @return void
	 */
	public function ajax_apply_single() {
		check_ajax_referer( 'wpfh_aww_ajax_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		if ( ! $product_id || ! current_user_can( 'edit_post', $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied or invalid product ID.', 'forgehub-ai-woo-writer' ) ), 403 );
		}

		if ( ! self::is_woocommerce_active() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'forgehub-ai-woo-writer' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'forgehub-ai-woo-writer' ) ) );
		}

		$fields = isset( $_POST['fields'] )
			? array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['fields'] ) ), array( 'long_description', 'short_description', 'bullets' ) ) )
			: array( 'long_description', 'short_description', 'bullets' );

		$raw_bullets = isset( $_POST['bullets'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bullets'] ) ) : '';
		$bullets     = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw_bullets ) ) ) );

		$copy_data = array(
			'long_description'     => isset( $_POST['long_description'] ) ? wp_kses_post( wp_unslash( $_POST['long_description'] ) ) : '',
			'short_description'    => isset( $_POST['short_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['short_description'] ) ) : '',
			'bullets'              => $bullets,
			'seo_meta_description' => isset( $_POST['seo_meta_description'] ) ? sanitize_text_field( wp_unslash( $_POST['seo_meta_description'] ) ) : '',
		);

		$options = array(
			'fields'         => $fields,
			'overwrite'      => ! empty( $_POST['overwrite'] ),
			'append_bullets' => ! empty( $_POST['append_bullets'] ),
			'sync_seo_meta'  => ! empty( $_POST['sync_seo_meta'] ),
		);

		$updated = self::apply_copy_to_product( $product, $copy_data, $options );

		if ( empty( $updated ) ) {
			wp_send_json_success(
				array(
					'updated' => array(),
					'message' => __( 'No fields were overwritten because existing content is protected. Enable "Overwrite existing non-empty fields" to replace it.', 'forgehub-ai-woo-writer' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'updated' => $updated,
				'message' => __( 'Product descriptions saved successfully.', 'forgehub-ai-woo-writer' ),
			)
		);
	}

	/**
	 * AJAX: Test BYOK AI provider connectivity from Settings page.
	 *
	 * @return void
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'wpfh_aww_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'forgehub-ai-woo-writer' ) ), 403 );
		}

		$result = WPFH_AWW_AI::generate_copy(
			array(
				'name'       => 'Stainless Steel Insulated Travel Mug',
				'categories' => 'Kitchen & Drinkware',
				'attributes' => 'Capacity: 500ml | Material: 304 Stainless Steel',
				'price'      => '29.00',
				'sku'        => 'TEST-MUG-500',
			),
			array(
				'fields'   => array( 'short_description' ),
				'tone'     => 'professional',
				'language' => 'English',
			)
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => $result['error'] ?? __( 'Connection failed.', 'forgehub-ai-woo-writer' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Connection verified! Your BYOK AI provider returned valid structured product copy.', 'forgehub-ai-woo-writer' ),
			)
		);
	}
}

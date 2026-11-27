<?php
/**
 * Pro Module for ForgeHub AI Woo Writer.
 *
 * Provides:
 * 1. Category / Filter-Based Bulk Product Copy Generation (AJAX Batch Queue)
 * 2. Reusable Brand Voice & Custom Prompt Templates
 * 3. Multi-Language Output Selection (12+ languages)
 * 4. Automatic SEO Meta Description & Product Excerpt Synchronization
 *
 * @package ForgeHub_AI_Woo_Writer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPFH_AWW_Pro
 */
class WPFH_AWW_Pro {

	/**
	 * Option name storing custom brand voice prompt templates.
	 */
	const TEMPLATES_OPTION = 'wpfh_aww_templates';

	/**
	 * Singleton instance.
	 *
	 * @var WPFH_AWW_Pro|null
	 */
	private static $instance = null;

	/**
	 * Return singleton instance.
	 *
	 * @return WPFH_AWW_Pro
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
		add_action( 'wpfh_aww_pro_settings_fields', array( $this, 'render_pro_settings_fields' ) );
		add_action( 'wpfh_aww_pro_metabox_fields', array( $this, 'render_pro_metabox_fields' ), 10, 2 );
		add_action( 'wpfh_aww_pro_panels', array( $this, 'render_pro_panels' ) );
		add_filter( 'wpfh_aww_generation_options', array( $this, 'inject_template_prompt' ), 10, 3 );
		add_action( 'wpfh_aww_after_apply_copy', array( $this, 'sync_seo_meta' ), 10, 4 );

		add_action( 'wp_ajax_wpfh_aww_pro_query_products', array( $this, 'ajax_query_products' ) );
		add_action( 'wp_ajax_wpfh_aww_pro_batch_item', array( $this, 'ajax_batch_item' ) );
		add_action( 'wp_ajax_wpfh_aww_pro_save_template', array( $this, 'ajax_save_template' ) );
		add_action( 'wp_ajax_wpfh_aww_pro_delete_template', array( $this, 'ajax_delete_template' ) );
	}

	/**
	 * Retrieve saved brand voice templates.
	 *
	 * @return array
	 */
	public static function get_templates() {
		$saved = get_option( self::TEMPLATES_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return $saved;
	}

	/**
	 * Render Pro fields in Settings form (Default Language + SEO Meta Sync).
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	public function render_pro_settings_fields( $settings ) {
		$languages = WPFH_AWW_Settings::get_languages();
		?>
		<div class="wpfh-aww-field">
			<label for="wpfh-aww-language"><?php esc_html_e( 'Default Output Language (Pro)', 'forgehub-ai-woo-writer' ); ?></label>
			<select id="wpfh-aww-language" name="<?php echo esc_attr( WPFH_AWW_Settings::OPTION_NAME ); ?>[default_language]">
				<?php foreach ( $languages as $l_key => $l_label ) : ?>
					<option value="<?php echo esc_attr( $l_key ); ?>" <?php selected( $settings['default_language'], $l_key ); ?>>
						<?php echo esc_html( $l_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="wpfh-aww-field wpfh-aww-checkbox-field">
			<label>
				<input
					type="checkbox"
					name="<?php echo esc_attr( WPFH_AWW_Settings::OPTION_NAME ); ?>[sync_seo_meta]"
					value="1"
					<?php checked( 1, (int) $settings['sync_seo_meta'] ); ?>
				/>
				<?php esc_html_e( 'Auto-sync SEO Meta Description (Yoast, Rank Math, AIOSEO, ForgeHub SEO)', 'forgehub-ai-woo-writer' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Render Pro controls inside the single-product metabox.
	 *
	 * @param WP_Post $post     Current product post.
	 * @param array   $settings Current plugin settings.
	 * @return void
	 */
	public function render_pro_metabox_fields( $post, $settings ) {
		$languages = WPFH_AWW_Settings::get_languages();
		$templates = self::get_templates();
		?>
		<div class="wpfh-aww-field">
			<label for="wpfh-aww-meta-language"><?php esc_html_e( 'Output Language (Pro)', 'forgehub-ai-woo-writer' ); ?></label>
			<select id="wpfh-aww-meta-language">
				<?php foreach ( $languages as $l_key => $l_label ) : ?>
					<option value="<?php echo esc_attr( $l_key ); ?>" <?php selected( $settings['default_language'], $l_key ); ?>>
						<?php echo esc_html( $l_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="wpfh-aww-field">
			<label for="wpfh-aww-meta-template"><?php esc_html_e( 'Brand Voice Template (Pro)', 'forgehub-ai-woo-writer' ); ?></label>
			<select id="wpfh-aww-meta-template">
				<option value=""><?php esc_html_e( 'Default Store Prompt', 'forgehub-ai-woo-writer' ); ?></option>
				<?php foreach ( $templates as $tpl_id => $tpl ) : ?>
					<option value="<?php echo esc_attr( $tpl_id ); ?>">
						<?php echo esc_html( $tpl['name'] ?? $tpl_id ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="wpfh-aww-field wpfh-aww-checkbox-field">
			<label>
				<input
					type="checkbox"
					id="wpfh-aww-meta-sync-seo"
					value="1"
					<?php checked( 1, (int) $settings['sync_seo_meta'] ); ?>
				/>
				<?php esc_html_e( 'Sync SEO Meta Description on Save', 'forgehub-ai-woo-writer' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Render Pro panels on Settings page: Bulk AJAX Batch Queue + Brand Voice Templates.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	public function render_pro_panels( $settings ) {
		$categories = array();
		if ( taxonomy_exists( 'product_cat' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				)
			);
			if ( is_array( $terms ) ) {
				$categories = $terms;
			}
		}

		$tones     = WPFH_AWW_Settings::get_tones();
		$languages = WPFH_AWW_Settings::get_languages();
		$templates = self::get_templates();
		?>
		<!-- Pro Panel 1: Bulk AJAX Batch Queue -->
		<div class="wpfh-aww-card wpfh-aww-pro-card">
			<h2><?php esc_html_e( 'Bulk Product Copy Generator (Pro AJAX Batch Queue)', 'forgehub-ai-woo-writer' ); ?></h2>
			<p class="wpfh-aww-meta">
				<?php esc_html_e( 'Filter WooCommerce products by category or missing descriptions and process them safely one-by-one in a resilient AJAX queue.', 'forgehub-ai-woo-writer' ); ?>
			</p>

			<div class="wpfh-aww-grid">
				<div class="wpfh-aww-field">
					<label for="wpfh-aww-bulk-cat"><?php esc_html_e( 'Product Category', 'forgehub-ai-woo-writer' ); ?></label>
					<select id="wpfh-aww-bulk-cat">
						<option value=""><?php esc_html_e( 'All Categories', 'forgehub-ai-woo-writer' ); ?></option>
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->slug ); ?>"><?php echo esc_html( $cat->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wpfh-aww-field">
					<label for="wpfh-aww-bulk-desc-filter"><?php esc_html_e( 'Description Filter', 'forgehub-ai-woo-writer' ); ?></label>
					<select id="wpfh-aww-bulk-desc-filter">
						<option value="all"><?php esc_html_e( 'All Products', 'forgehub-ai-woo-writer' ); ?></option>
						<option value="missing_any"><?php esc_html_e( 'Missing Long or Short Description', 'forgehub-ai-woo-writer' ); ?></option>
						<option value="missing_long"><?php esc_html_e( 'Missing Long Description Only', 'forgehub-ai-woo-writer' ); ?></option>
						<option value="missing_short"><?php esc_html_e( 'Missing Short Description Only', 'forgehub-ai-woo-writer' ); ?></option>
					</select>
				</div>

				<div class="wpfh-aww-field">
					<label for="wpfh-aww-bulk-search"><?php esc_html_e( 'Search Keyword', 'forgehub-ai-woo-writer' ); ?></label>
					<input type="text" id="wpfh-aww-bulk-search" placeholder="<?php esc_attr_e( 'Product name or SKU...', 'forgehub-ai-woo-writer' ); ?>" />
				</div>

				<div class="wpfh-aww-field">
					<label for="wpfh-aww-bulk-tone"><?php esc_html_e( 'Writing Tone', 'forgehub-ai-woo-writer' ); ?></label>
					<select id="wpfh-aww-bulk-tone">
						<?php foreach ( $tones as $t_key => $t_label ) : ?>
							<option value="<?php echo esc_attr( $t_key ); ?>" <?php selected( $settings['default_tone'], $t_key ); ?>>
								<?php echo esc_html( $t_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wpfh-aww-field">
					<label for="wpfh-aww-bulk-language"><?php esc_html_e( 'Output Language', 'forgehub-ai-woo-writer' ); ?></label>
					<select id="wpfh-aww-bulk-language">
						<?php foreach ( $languages as $l_key => $l_label ) : ?>
							<option value="<?php echo esc_attr( $l_key ); ?>" <?php selected( $settings['default_language'], $l_key ); ?>>
								<?php echo esc_html( $l_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wpfh-aww-field">
					<label for="wpfh-aww-bulk-template"><?php esc_html_e( 'Brand Voice Template', 'forgehub-ai-woo-writer' ); ?></label>
					<select id="wpfh-aww-bulk-template">
						<option value=""><?php esc_html_e( 'Default Store Prompt', 'forgehub-ai-woo-writer' ); ?></option>
						<?php foreach ( $templates as $tpl_id => $tpl ) : ?>
							<option value="<?php echo esc_attr( $tpl_id ); ?>">
								<?php echo esc_html( $tpl['name'] ?? $tpl_id ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wpfh-aww-field wpfh-aww-checkbox-field">
					<label>
						<input type="checkbox" id="wpfh-aww-bulk-overwrite" value="1" <?php checked( 1, (int) $settings['overwrite_existing'] ); ?> />
						<?php esc_html_e( 'Overwrite existing descriptions', 'forgehub-ai-woo-writer' ); ?>
					</label>
				</div>

				<div class="wpfh-aww-field wpfh-aww-checkbox-field">
					<label>
						<input type="checkbox" id="wpfh-aww-bulk-sync-seo" value="1" <?php checked( 1, (int) $settings['sync_seo_meta'] ); ?> />
						<?php esc_html_e( 'Sync SEO Meta Description', 'forgehub-ai-woo-writer' ); ?>
					</label>
				</div>
			</div>

			<div class="wpfh-aww-action-bar">
				<button type="button" id="wpfh-aww-bulk-load-btn" class="button button-secondary">
					<?php esc_html_e( 'Load Matching Products', 'forgehub-ai-woo-writer' ); ?>
				</button>
				<button type="button" id="wpfh-aww-bulk-start-btn" class="button button-primary" disabled>
					<?php esc_html_e( 'Start Batch Generation', 'forgehub-ai-woo-writer' ); ?>
				</button>
				<button type="button" id="wpfh-aww-bulk-stop-btn" class="button button-secondary" hidden>
					<?php esc_html_e( 'Stop Queue', 'forgehub-ai-woo-writer' ); ?>
				</button>
			</div>

			<div id="wpfh-aww-bulk-progress-wrap" class="wpfh-aww-progress-wrap" hidden>
				<div class="wpfh-aww-progress-bar">
					<div id="wpfh-aww-bulk-progress-fill" class="wpfh-aww-progress-fill" style="width:0%;"></div>
				</div>
				<p id="wpfh-aww-bulk-progress-text" class="wpfh-aww-meta"></p>
			</div>

			<div class="wpfh-aww-table-wrap">
				<table class="wp-list-table widefat fixed striped wpfh-aww-table" id="wpfh-aww-bulk-table">
					<thead>
						<tr>
							<th style="width:36px;"><input type="checkbox" id="wpfh-aww-bulk-select-all" aria-label="<?php esc_attr_e( 'Select all products', 'forgehub-ai-woo-writer' ); ?>" /></th>
							<th style="width:70px;"><?php esc_html_e( 'ID', 'forgehub-ai-woo-writer' ); ?></th>
							<th><?php esc_html_e( 'Product', 'forgehub-ai-woo-writer' ); ?></th>
							<th><?php esc_html_e( 'Long Desc', 'forgehub-ai-woo-writer' ); ?></th>
							<th><?php esc_html_e( 'Short Desc', 'forgehub-ai-woo-writer' ); ?></th>
							<th><?php esc_html_e( 'Queue Status', 'forgehub-ai-woo-writer' ); ?></th>
						</tr>
					</thead>
					<tbody id="wpfh-aww-bulk-tbody">
						<tr>
							<td colspan="6"><?php esc_html_e( 'Click "Load Matching Products" to populate the batch queue.', 'forgehub-ai-woo-writer' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Pro Panel 2: Brand Voice & Prompt Templates -->
		<div class="wpfh-aww-card wpfh-aww-pro-card">
			<h2><?php esc_html_e( 'Brand Voice & Custom Prompt Templates (Pro)', 'forgehub-ai-woo-writer' ); ?></h2>
			<p class="wpfh-aww-meta">
				<?php esc_html_e( 'Define reusable brand voice guidelines (e.g., eco-friendly minimalism, B2B technical specs, luxury storytelling) to keep copy consistent across your catalog.', 'forgehub-ai-woo-writer' ); ?>
			</p>

			<div class="wpfh-aww-grid">
				<div class="wpfh-aww-field">
					<label for="wpfh-aww-tpl-name"><?php esc_html_e( 'Template Name', 'forgehub-ai-woo-writer' ); ?></label>
					<input type="text" id="wpfh-aww-tpl-name" placeholder="<?php esc_attr_e( 'e.g. Sustainable Fashion Voice', 'forgehub-ai-woo-writer' ); ?>" />
				</div>
				<div class="wpfh-aww-field wpfh-aww-field-wide">
					<label for="wpfh-aww-tpl-prompt"><?php esc_html_e( 'Brand Voice / Prompt Instructions', 'forgehub-ai-woo-writer' ); ?></label>
					<textarea id="wpfh-aww-tpl-prompt" rows="3" placeholder="<?php esc_attr_e( 'Emphasize ethically sourced materials, avoid clichés, include care tips in the final paragraph.', 'forgehub-ai-woo-writer' ); ?>"></textarea>
				</div>
			</div>

			<div class="wpfh-aww-action-bar">
				<button type="button" id="wpfh-aww-tpl-save-btn" class="button button-primary">
					<?php esc_html_e( 'Save Brand Voice Template', 'forgehub-ai-woo-writer' ); ?>
				</button>
			</div>

			<div class="wpfh-aww-table-wrap" style="margin-top:14px;">
				<table class="wp-list-table widefat fixed striped wpfh-aww-table">
					<thead>
						<tr>
							<th style="width:220px;"><?php esc_html_e( 'Template Name', 'forgehub-ai-woo-writer' ); ?></th>
							<th><?php esc_html_e( 'Instructions', 'forgehub-ai-woo-writer' ); ?></th>
							<th style="width:110px;"><?php esc_html_e( 'Action', 'forgehub-ai-woo-writer' ); ?></th>
						</tr>
					</thead>
					<tbody id="wpfh-aww-tpl-tbody">
						<?php if ( empty( $templates ) ) : ?>
							<tr>
								<td colspan="3"><?php esc_html_e( 'No custom brand voice templates yet.', 'forgehub-ai-woo-writer' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $templates as $tpl_id => $tpl ) : ?>
								<tr data-tpl-id="<?php echo esc_attr( $tpl_id ); ?>">
									<td><strong><?php echo esc_html( $tpl['name'] ?? '' ); ?></strong></td>
									<td><?php echo esc_html( $tpl['prompt'] ?? '' ); ?></td>
									<td>
										<button type="button" class="button button-small wpfh-aww-tpl-delete-btn" data-tpl-id="<?php echo esc_attr( $tpl_id ); ?>">
											<?php esc_html_e( 'Delete', 'forgehub-ai-woo-writer' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Inject selected Brand Voice template prompt into AI generation options.
	 *
	 * @param array      $options Existing options.
	 * @param string     $tpl_id  Selected template ID.
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	public function inject_template_prompt( $options, $tpl_id, $product ) {
		if ( '' === $tpl_id ) {
			return $options;
		}
		$templates = self::get_templates();
		if ( isset( $templates[ $tpl_id ]['prompt'] ) ) {
			$options['template_prompt'] = sanitize_textarea_field( (string) $templates[ $tpl_id ]['prompt'] );
		}
		return $options;
	}

	/**
	 * Sync generated SEO meta description to popular SEO plugins and product meta.
	 *
	 * @param WC_Product $product        Product object.
	 * @param array      $copy_data      Generated copy data.
	 * @param array      $options        Apply options.
	 * @param array      $updated_fields Updated field keys.
	 * @return void
	 */
	public function sync_seo_meta( $product, $copy_data, $options, $updated_fields ) {
		if ( empty( $options['sync_seo_meta'] ) ) {
			return;
		}

		$meta_desc = sanitize_text_field( (string) ( $copy_data['seo_meta_description'] ?? '' ) );
		if ( '' === $meta_desc ) {
			return;
		}

		$product_id = (int) $product->get_id();
		$overwrite  = ! empty( $options['overwrite'] );

		$meta_keys = array(
			'_yoast_wpseo_metadesc',
			'rank_math_description',
			'_aioseo_description',
			'_wpfh_seo_description',
		);

		foreach ( $meta_keys as $meta_key ) {
			$existing = trim( (string) get_post_meta( $product_id, $meta_key, true ) );
			if ( $overwrite || '' === $existing ) {
				update_post_meta( $product_id, $meta_key, $meta_desc );
			}
		}
	}

	/**
	 * AJAX: Query WooCommerce products for the Pro bulk batch queue.
	 *
	 * @return void
	 */
	public function ajax_query_products() {
		check_ajax_referer( 'wpfh_aww_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'forgehub-ai-woo-writer' ) ), 403 );
		}

		if ( ! WPFH_AWW_Admin::is_woocommerce_active() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'forgehub-ai-woo-writer' ) ) );
		}

		$category    = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$desc_filter = isset( $_POST['desc_filter'] ) ? sanitize_key( wp_unslash( $_POST['desc_filter'] ) ) : 'all';
		$search      = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		$args = array(
			'status' => array( 'publish', 'draft', 'private' ),
			'limit'  => 100,
			'orderby' => 'ID',
			'order'  => 'DESC',
		);

		if ( '' !== $category ) {
			$args['category'] = array( $category );
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$products = wc_get_products( $args );
		$rows     = array();

		foreach ( $products as $product ) {
			$has_long  = '' !== trim( wp_strip_all_tags( (string) $product->get_description() ) );
			$has_short = '' !== trim( wp_strip_all_tags( (string) $product->get_short_description() ) );

			if ( 'missing_long' === $desc_filter && $has_long ) {
				continue;
			}
			if ( 'missing_short' === $desc_filter && $has_short ) {
				continue;
			}
			if ( 'missing_any' === $desc_filter && $has_long && $has_short ) {
				continue;
			}

			$rows[] = array(
				'id'        => (int) $product->get_id(),
				'name'      => $product->get_name(),
				'has_long'  => $has_long,
				'has_short' => $has_short,
				'edit_url'  => get_edit_post_link( $product->get_id(), 'raw' ),
			);
		}

		wp_send_json_success( array( 'products' => $rows ) );
	}

	/**
	 * AJAX: Process a single product in the Pro bulk batch queue.
	 *
	 * @return void
	 */
	public function ajax_batch_item() {
		check_ajax_referer( 'wpfh_aww_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'forgehub-ai-woo-writer' ) ), 403 );
		}

		if ( ! WPFH_AWW_Admin::is_woocommerce_active() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'forgehub-ai-woo-writer' ) ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : false;
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'forgehub-ai-woo-writer' ) ) );
		}

		$overwrite = ! empty( $_POST['overwrite'] );
		$sync_seo  = ! empty( $_POST['sync_seo_meta'] );
		$tone      = isset( $_POST['tone'] ) ? sanitize_key( wp_unslash( $_POST['tone'] ) ) : 'persuasive';
		$language  = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'English';
		$tpl_id    = isset( $_POST['template_id'] ) ? sanitize_key( wp_unslash( $_POST['template_id'] ) ) : '';

		$has_long  = '' !== trim( (string) $product->get_description() );
		$has_short = '' !== trim( (string) $product->get_short_description() );
		if ( ! $overwrite && $has_long && $has_short ) {
			wp_send_json_success(
				array(
					'status'  => 'skipped',
					'message' => __( 'Skipped (already has descriptions)', 'forgehub-ai-woo-writer' ),
				)
			);
		}

		$options = array(
			'fields'          => array( 'long_description', 'short_description', 'bullets' ),
			'tone'            => $tone,
			'language'        => $language,
			'template_prompt' => '',
		);
		$options = apply_filters( 'wpfh_aww_generation_options', $options, $tpl_id, $product );

		$context = WPFH_AWW_AI::extract_product_context( $product );
		$result  = WPFH_AWW_AI::generate_copy( $context, $options );
		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => $result['error'] ?? __( 'Generation failed.', 'forgehub-ai-woo-writer' ) ) );
		}

		$updated = WPFH_AWW_Admin::apply_copy_to_product(
			$product,
			$result['data'],
			array(
				'fields'         => array( 'long_description', 'short_description', 'bullets' ),
				'overwrite'      => $overwrite,
				'append_bullets' => true,
				'sync_seo_meta'  => $sync_seo,
			)
		);

		wp_send_json_success(
			array(
				'status'  => 'done',
				'updated' => $updated,
				'message' => __( 'Generated & saved', 'forgehub-ai-woo-writer' ),
			)
		);
	}

	/**
	 * AJAX: Save a new Brand Voice / Prompt Template.
	 *
	 * @return void
	 */
	public function ajax_save_template() {
		check_ajax_referer( 'wpfh_aww_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'forgehub-ai-woo-writer' ) ), 403 );
		}

		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$prompt = isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : '';

		if ( '' === $name || '' === $prompt ) {
			wp_send_json_error( array( 'message' => __( 'Template name and prompt instructions are required.', 'forgehub-ai-woo-writer' ) ) );
		}

		$templates         = self::get_templates();
		$tpl_id            = sanitize_key( 'tpl_' . substr( md5( $name . microtime( true ) ), 0, 10 ) );
		$templates[ $tpl_id ] = array(
			'name'   => $name,
			'prompt' => $prompt,
		);

		update_option( self::TEMPLATES_OPTION, $templates, false );

		wp_send_json_success(
			array(
				'id'       => $tpl_id,
				'template' => $templates[ $tpl_id ],
			)
		);
	}

	/**
	 * AJAX: Delete a saved Brand Voice / Prompt Template.
	 *
	 * @return void
	 */
	public function ajax_delete_template() {
		check_ajax_referer( 'wpfh_aww_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'forgehub-ai-woo-writer' ) ), 403 );
		}

		$tpl_id    = isset( $_POST['template_id'] ) ? sanitize_key( wp_unslash( $_POST['template_id'] ) ) : '';
		$templates = self::get_templates();
		if ( isset( $templates[ $tpl_id ] ) ) {
			unset( $templates[ $tpl_id ] );
			update_option( self::TEMPLATES_OPTION, $templates, false );
		}

		wp_send_json_success( array( 'id' => $tpl_id ) );
	}
}

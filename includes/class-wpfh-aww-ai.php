<?php
/**
 * BYOK Multi-AI Product Copywriting Engine for ForgeHub AI Woo Writer.
 *
 * Supports OpenAI, Anthropic Claude, Google Gemini, OpenRouter, DeepSeek,
 * and Custom OpenAI-Compatible endpoints via native wp_remote_post().
 *
 * @package ForgeHub_AI_Woo_Writer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPFH_AWW_AI
 */
class WPFH_AWW_AI {

	/**
	 * Supported AI providers metadata.
	 *
	 * @return array
	 */
	public static function get_providers() {
		return array(
			'openai'     => array(
				'label'         => 'OpenAI (GPT-4o / GPT-4o Mini)',
				'default_model' => 'gpt-4o-mini',
				'key_url'       => 'https://platform.openai.com/api-keys',
				'models'        => array(
					'gpt-4o-mini' => 'GPT-4o Mini (Fast & Cost-Effective)',
					'gpt-4o'      => 'GPT-4o (Flagship Copywriting)',
				),
			),
			'anthropic'  => array(
				'label'         => 'Anthropic Claude (3.5 Haiku / Sonnet)',
				'default_model' => 'claude-3-5-haiku-20241022',
				'key_url'       => 'https://console.anthropic.com/settings/keys',
				'models'        => array(
					'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku (Fast & Natural)',
					'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Editorial Quality)',
				),
			),
			'gemini'     => array(
				'label'         => 'Google Gemini (2.0 Flash / 1.5 Pro)',
				'default_model' => 'gemini-2.0-flash',
				'key_url'       => 'https://aistudio.google.com/app/apikey',
				'models'        => array(
					'gemini-2.0-flash' => 'Gemini 2.0 Flash (Fast & Generous Tier)',
					'gemini-1.5-flash' => 'Gemini 1.5 Flash',
					'gemini-1.5-pro'   => 'Gemini 1.5 Pro (Deep Reasoning)',
				),
			),
			'openrouter' => array(
				'label'         => 'OpenRouter (Multi-Model Gateway)',
				'default_model' => 'openai/gpt-4o-mini',
				// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- Documentation link for user BYOK key.
				'key_url'       => 'https://openrouter.ai/keys',
				'models'        => array(
					'openai/gpt-4o-mini'          => 'OpenAI GPT-4o Mini (via OpenRouter)',
					'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet (via OpenRouter)',
					'google/gemini-flash-1.5'     => 'Gemini 1.5 Flash (via OpenRouter)',
					'deepseek/deepseek-chat'      => 'DeepSeek V3 (via OpenRouter)',
				),
			),
			'deepseek'   => array(
				'label'         => 'DeepSeek (V3 Chat / R1 Reasoner)',
				'default_model' => 'deepseek-chat',
				'key_url'       => 'https://platform.deepseek.com/api_keys',
				'models'        => array(
					'deepseek-chat'     => 'DeepSeek V3 Chat (Ultra Low-Cost)',
					'deepseek-reasoner' => 'DeepSeek R1 Reasoner',
				),
			),
			'custom'     => array(
				'label'         => 'Custom OpenAI-Compatible (Ollama, Groq, vLLM)',
				'default_model' => 'llama-3.3-70b-versatile',
				'key_url'       => '',
				'models'        => array(),
			),
		);
	}

	/**
	 * Build product context payload from a WC_Product.
	 *
	 * @param WC_Product $product Product instance.
	 * @return array
	 */
	public static function extract_product_context( $product ) {
		$cat_names = array();
		$terms     = get_the_terms( $product->get_id(), 'product_cat' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$cat_names[] = $term->name;
			}
		}

		$attr_list = array();
		foreach ( $product->get_attributes() as $attr ) {
			if ( is_object( $attr ) && method_exists( $attr, 'get_name' ) ) {
				$name = wc_attribute_label( $attr->get_name() );
				$vals = $attr->is_taxonomy()
					? wc_get_product_terms( $product->get_id(), $attr->get_name(), array( 'fields' => 'names' ) )
					: $attr->get_options();
				if ( ! empty( $vals ) ) {
					$attr_list[] = $name . ': ' . implode( ', ', $vals );
				}
			}
		}

		return array(
			'id'          => (int) $product->get_id(),
			'name'        => $product->get_name(),
			'sku'         => $product->get_sku(),
			'price'       => $product->get_price(),
			'categories'  => implode( ', ', $cat_names ),
			'attributes'  => implode( ' | ', $attr_list ),
			'current_short' => wp_strip_all_tags( (string) $product->get_short_description() ),
		);
	}

	/**
	 * Generate SEO product copy using configured BYOK provider.
	 *
	 * @param array $product_context Product metadata array.
	 * @param array $options         Generation options (fields, tone, language, keywords, template_prompt).
	 * @return array ['success' => bool, 'data' => array, 'error' => string]
	 */
	public static function generate_copy( $product_context, $options = array() ) {
		$settings = WPFH_AWW_Settings::get_all();
		$provider = $settings['provider'];
		$api_key  = trim( (string) $settings['api_key'] );
		$model    = '' !== trim( (string) $settings['custom_model'] ) ? trim( (string) $settings['custom_model'] ) : trim( (string) $settings['model'] );
		$endpoint = trim( (string) $settings['custom_endpoint'] );

		if ( '' === $api_key && 'custom' !== $provider ) {
			return array(
				'success' => false,
				'error'   => __( 'API key is missing. Add your BYOK API key under Settings > AI Woo Writer.', 'forgehub-ai-woo-writer' ),
			);
		}

		$providers = self::get_providers();
		if ( '' === $model && isset( $providers[ $provider ]['default_model'] ) ) {
			$model = $providers[ $provider ]['default_model'];
		}

		$tone     = ! empty( $options['tone'] ) ? sanitize_text_field( $options['tone'] ) : $settings['default_tone'];
		$language = ! empty( $options['language'] ) ? sanitize_text_field( $options['language'] ) : $settings['default_language'];
		$keywords = ! empty( $options['keywords'] ) ? sanitize_text_field( $options['keywords'] ) : '';
		$tpl_inst = ! empty( $options['template_prompt'] ) ? sanitize_textarea_field( $options['template_prompt'] ) : '';
		$fields   = ! empty( $options['fields'] ) && is_array( $options['fields'] ) ? $options['fields'] : array( 'long_description', 'short_description', 'bullets' );

		$system_prompt = 'You are an expert e-commerce SEO copywriter for WooCommerce stores. Return ONLY valid JSON with these exact keys: "long_description" (clean semantic HTML paragraphs <p> and <h3>, no <h1>), "short_description" (concise 1-2 sentence persuasive summary, plain text), "bullets" (array of 4-6 concise benefit-driven feature bullet strings), and "seo_meta_description" (compelling 140-155 character meta description). Do not wrap in markdown fences.';

		$user_lines = array(
			'Product Name: ' . ( $product_context['name'] ?? '' ),
			'Categories: ' . ( $product_context['categories'] ?? 'General' ),
			'Attributes: ' . ( $product_context['attributes'] ?? 'None' ),
			'Price: ' . ( $product_context['price'] ?? '' ),
			'SKU: ' . ( $product_context['sku'] ?? '' ),
			'Requested Fields: ' . implode( ', ', $fields ),
			'Writing Tone: ' . $tone,
			'Output Language: ' . $language,
		);
		if ( '' !== $keywords ) {
			$user_lines[] = 'Focus SEO Keywords / Notes: ' . $keywords;
		}
		if ( '' !== $tpl_inst ) {
			$user_lines[] = 'Brand Voice / Custom Template Instructions: ' . $tpl_inst;
		}
		$user_prompt = implode( "\n", $user_lines );

		switch ( $provider ) {
			case 'openai':
				// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- BYOK multi-provider client supports WP 6.0+.
				return self::call_openai_compatible( 'https://api.openai.com/v1', $api_key, $model, $system_prompt, $user_prompt );
			case 'openrouter':
				// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- BYOK multi-provider client supports WP 6.0+.
				return self::call_openai_compatible( 'https://openrouter.ai/api/v1', $api_key, $model, $system_prompt, $user_prompt );
			case 'deepseek':
				// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- BYOK multi-provider client supports WP 6.0+.
				return self::call_openai_compatible( 'https://api.deepseek.com', $api_key, $model, $system_prompt, $user_prompt );
			case 'custom':
				if ( '' === $endpoint ) {
					return array(
						'success' => false,
						'error'   => __( 'Custom endpoint URL is required for the Custom provider.', 'forgehub-ai-woo-writer' ),
					);
				}
				return self::call_openai_compatible( $endpoint, $api_key, $model, $system_prompt, $user_prompt );
			case 'anthropic':
				return self::call_anthropic( $api_key, $model, $system_prompt, $user_prompt );
			case 'gemini':
				return self::call_gemini( $api_key, $model, $system_prompt, $user_prompt );
			default:
				return array(
					'success' => false,
					'error'   => __( 'Unsupported AI provider.', 'forgehub-ai-woo-writer' ),
				);
		}
	}

	/**
	 * Call OpenAI / OpenRouter / DeepSeek / Custom OpenAI-compatible Chat Completions API.
	 *
	 * @param string $base_url      API base URL.
	 * @param string $api_key       Bearer API key.
	 * @param string $model         Model ID.
	 * @param string $system_prompt System instruction.
	 * @param string $user_prompt   User prompt.
	 * @return array
	 */
	private static function call_openai_compatible( $base_url, $api_key, $model, $system_prompt, $user_prompt ) {
		$url     = rtrim( $base_url, '/' ) . '/chat/completions';
		$headers = array( 'Content-Type' => 'application/json' );
		if ( '' !== $api_key ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		$payload = array(
			'model'           => $model,
			'temperature'     => 0.6,
			'max_tokens'      => 1200,
			'response_format' => array( 'type' => 'json_object' ),
			'messages'        => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
				'timeout' => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code . ' error from AI provider.';
			return array(
				'success' => false,
				'error'   => sanitize_text_field( (string) $msg ),
			);
		}

		$text = isset( $body['choices'][0]['message']['content'] ) ? (string) $body['choices'][0]['message']['content'] : '';
		return self::parse_json_response( $text );
	}

	/**
	 * Call Anthropic Claude Messages API.
	 *
	 * @param string $api_key       Anthropic API key.
	 * @param string $model         Model ID.
	 * @param string $system_prompt System instruction.
	 * @param string $user_prompt   User prompt.
	 * @return array
	 */
	private static function call_anthropic( $api_key, $model, $system_prompt, $user_prompt ) {
		// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- BYOK multi-provider client supports WP 6.0+.
		$url      = 'https://api.anthropic.com/v1/messages';
		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $model,
						'max_tokens' => 1200,
						'system'     => $system_prompt,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => $user_prompt,
							),
						),
					)
				),
				'timeout' => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code . ' error from Anthropic.';
			return array(
				'success' => false,
				'error'   => sanitize_text_field( (string) $msg ),
			);
		}

		$text = isset( $body['content'][0]['text'] ) ? (string) $body['content'][0]['text'] : '';
		return self::parse_json_response( $text );
	}

	/**
	 * Call Google Gemini generateContent API.
	 *
	 * @param string $api_key       Gemini API key.
	 * @param string $model         Model ID.
	 * @param string $system_prompt System instruction.
	 * @param string $user_prompt   User prompt.
	 * @return array
	 */
	private static function call_gemini( $api_key, $model, $system_prompt, $user_prompt ) {
		// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- BYOK multi-provider client supports WP 6.0+.
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		$payload = array(
			'systemInstruction' => array(
				'parts' => array(
					array( 'text' => $system_prompt ),
				),
			),
			'contents'          => array(
				array(
					'parts' => array(
						array( 'text' => $user_prompt ),
					),
				),
			),
			'generationConfig'  => array(
				'temperature'      => 0.6,
				'responseMimeType' => 'application/json',
			),
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code . ' error from Gemini.';
			return array(
				'success' => false,
				'error'   => sanitize_text_field( (string) $msg ),
			);
		}

		$text = isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ? (string) $body['candidates'][0]['content']['parts'][0]['text'] : '';
		return self::parse_json_response( $text );
	}

	/**
	 * Parse and sanitize structured JSON output from AI provider.
	 *
	 * @param string $text Raw model text.
	 * @return array
	 */
	private static function parse_json_response( $text ) {
		$text = trim( $text );
		if ( preg_match( '/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $matches ) ) {
			$text = $matches[1];
		}

		$decoded = json_decode( $text, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return array(
				'success' => false,
				'error'   => __( 'AI returned an unexpected format. Please try again or switch model.', 'forgehub-ai-woo-writer' ),
			);
		}

		$bullets_raw = isset( $decoded['bullets'] ) && is_array( $decoded['bullets'] ) ? $decoded['bullets'] : array();
		$bullets     = array();
		foreach ( $bullets_raw as $b ) {
			$clean = sanitize_text_field( (string) $b );
			if ( '' !== $clean ) {
				$bullets[] = $clean;
			}
		}

		return array(
			'success' => true,
			'data'    => array(
				'long_description'     => wp_kses_post( (string) ( $decoded['long_description'] ?? '' ) ),
				'short_description'    => sanitize_textarea_field( (string) ( $decoded['short_description'] ?? '' ) ),
				'bullets'              => $bullets,
				'seo_meta_description' => sanitize_text_field( (string) ( $decoded['seo_meta_description'] ?? '' ) ),
			),
		);
	}
}

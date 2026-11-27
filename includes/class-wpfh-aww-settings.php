<?php
/**
 * Settings helper for ForgeHub AI Woo Writer.
 *
 * @package ForgeHub_AI_Woo_Writer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPFH_AWW_Settings
 */
class WPFH_AWW_Settings {

	/**
	 * Option name in wp_options.
	 */
	const OPTION_NAME = 'wpfh_aww_settings';

	/**
	 * Return default plugin settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'provider'           => 'openai',
			'api_key'            => '',
			'model'              => 'gpt-4o-mini',
			'custom_model'       => '',
			'custom_endpoint'    => '',
			'default_tone'       => 'persuasive',
			'default_language'   => 'English',
			'overwrite_existing' => 0,
			'sync_seo_meta'      => 1,
		);
	}

	/**
	 * Retrieve merged plugin settings.
	 *
	 * @return array
	 */
	public static function get_all() {
		$saved = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::get_defaults() );
	}

	/**
	 * Supported writing tones.
	 *
	 * @return array
	 */
	public static function get_tones() {
		return array(
			'persuasive'   => __( 'Persuasive & Conversion-Focused', 'forgehub-ai-woo-writer' ),
			'professional' => __( 'Professional & Informative', 'forgehub-ai-woo-writer' ),
			'friendly'     => __( 'Friendly & Approachable', 'forgehub-ai-woo-writer' ),
			'luxury'       => __( 'Luxury & Editorial', 'forgehub-ai-woo-writer' ),
			'technical'    => __( 'Technical & Spec-Driven', 'forgehub-ai-woo-writer' ),
		);
	}

	/**
	 * Supported output languages.
	 *
	 * @return array
	 */
	public static function get_languages() {
		return array(
			'English'    => 'English',
			'Turkish'    => 'Turkish (Türkçe)',
			'German'     => 'German (Deutsch)',
			'French'     => 'French (Français)',
			'Spanish'    => 'Spanish (Español)',
			'Italian'    => 'Italian (Italiano)',
			'Portuguese' => 'Portuguese (Português)',
			'Dutch'      => 'Dutch (Nederlands)',
			'Polish'     => 'Polish (Polski)',
			'Arabic'     => 'Arabic (العربية)',
			'Japanese'   => 'Japanese (日本語)',
			'Korean'     => 'Korean (한국어)',
		);
	}

	/**
	 * Sanitize settings array.
	 *
	 * @param array $raw Raw input.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$providers = array_keys( WPFH_AWW_AI::get_providers() );
		$provider  = isset( $raw['provider'] ) ? sanitize_key( $raw['provider'] ) : 'openai';
		if ( ! in_array( $provider, $providers, true ) ) {
			$provider = 'openai';
		}

		$tones = array_keys( self::get_tones() );
		$tone  = isset( $raw['default_tone'] ) ? sanitize_key( $raw['default_tone'] ) : 'persuasive';
		if ( ! in_array( $tone, $tones, true ) ) {
			$tone = 'persuasive';
		}

		$existing = self::get_all();
		$api_key  = isset( $raw['api_key'] ) ? trim( sanitize_text_field( $raw['api_key'] ) ) : '';
		if ( '' === $api_key && ! empty( $existing['api_key'] ) && empty( $raw['clear_api_key'] ) ) {
			$api_key = $existing['api_key'];
		}

		return array(
			'provider'           => $provider,
			'api_key'            => $api_key,
			'model'              => isset( $raw['model'] ) ? sanitize_text_field( $raw['model'] ) : 'gpt-4o-mini',
			'custom_model'       => isset( $raw['custom_model'] ) ? sanitize_text_field( $raw['custom_model'] ) : '',
			'custom_endpoint'    => isset( $raw['custom_endpoint'] ) ? esc_url_raw( trim( (string) $raw['custom_endpoint'] ) ) : '',
			'default_tone'       => $tone,
			'default_language'   => isset( $raw['default_language'] ) ? sanitize_text_field( $raw['default_language'] ) : 'English',
			'overwrite_existing' => ! empty( $raw['overwrite_existing'] ) ? 1 : 0,
			'sync_seo_meta'      => ! empty( $raw['sync_seo_meta'] ) ? 1 : 0,
		);
	}
}

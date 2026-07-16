<?php
namespace HMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {
	public const DEFAULTS = array(
		'hmp_hottok'                    => '',
		'hmp_webhook_enabled'           => 1,
		'hmp_payload_retention_days'    => 90,
		'hmp_default_grace_period_days' => 3,
		'hmp_debug_mode'                => 0,
	);

	public static function add_defaults(): void {
		foreach ( self::DEFAULTS as $key => $value ) {
			add_option( $key, $value );
		}
	}

	public static function get( string $key ) {
		return get_option( $key, self::DEFAULTS[ $key ] ?? null );
	}

	public static function register(): void {
		add_action(
			'admin_init',
			static function () {
				register_setting( 'hmp_settings', 'hmp_hottok', array( 'sanitize_callback' => array( __CLASS__, 'sanitize_hottok' ) ) );
				register_setting( 'hmp_settings', 'hmp_webhook_enabled', array( 'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ) ) );
				register_setting( 'hmp_settings', 'hmp_payload_retention_days', array( 'sanitize_callback' => array( __CLASS__, 'sanitize_days' ) ) );
				register_setting( 'hmp_settings', 'hmp_default_grace_period_days', array( 'sanitize_callback' => array( __CLASS__, 'sanitize_days' ) ) );
				register_setting( 'hmp_settings', 'hmp_debug_mode', array( 'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ) ) );
			}
		);
	}

	public static function sanitize_hottok( $value ): string {
		$value = trim( sanitize_text_field( (string) $value ) );
		return '' === $value ? (string) get_option( 'hmp_hottok', '' ) : $value;
	}

	public static function sanitize_checkbox( $value ): int {
		return empty( $value ) ? 0 : 1;
	}

	public static function sanitize_days( $value ): int {
		return max( 0, absint( $value ) );
	}
}

<?php
namespace HMP\Webhook;

use HMP\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Authenticator {
	public function authenticate( ?\WP_REST_Request $request = null ) {
		$configured = trim( (string) Settings::get( 'hmp_hottok' ) );
		if ( '' === $configured ) {
			return new \WP_Error( 'hmp_hottok_not_configured', __( 'Webhook authentication is not configured.', 'hotmart-memberpress-pro' ), array( 'status' => 401 ) );
		}

		$provided = '';
		if ( $request ) {
			$provided = trim( (string) $request->get_header( 'x-hotmart-hottok' ) );
		}
		if ( '' === $provided ) {
			$server_keys = array( 'HTTP_X_HOTMART_HOTTOK', 'REDIRECT_HTTP_X_HOTMART_HOTTOK', 'X_HOTMART_HOTTOK' );
			foreach ( $server_keys as $key ) {
				if ( isset( $_SERVER[ $key ] ) ) {
					$provided = trim( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
					break;
				}
			}
		}

		if ( '' === $provided || ! hash_equals( $configured, $provided ) ) {
			return new \WP_Error( 'hmp_invalid_hottok', __( 'Invalid webhook credentials.', 'hotmart-memberpress-pro' ), array( 'status' => 401 ) );
		}
		return true;
	}
}

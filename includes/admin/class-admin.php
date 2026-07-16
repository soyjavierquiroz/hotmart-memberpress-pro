<?php
namespace HMP\Admin;

use HMP\Repositories\Event_Repository;
use HMP\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {
	private Event_Repository $events;

	public function __construct( Event_Repository $events ) {
		$this->events = $events;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu(): void {
		add_menu_page(
			__( 'Hotmart MemberPress', 'hotmart-memberpress-pro' ),
			__( 'Hotmart MemberPress', 'hotmart-memberpress-pro' ),
			'manage_options',
			'hotmart-memberpress-pro',
			array( $this, 'overview' ),
			'dashicons-rest-api'
		);
		add_submenu_page( 'hotmart-memberpress-pro', __( 'Overview', 'hotmart-memberpress-pro' ), __( 'Overview', 'hotmart-memberpress-pro' ), 'manage_options', 'hotmart-memberpress-pro', array( $this, 'overview' ) );
		add_submenu_page( 'hotmart-memberpress-pro', __( 'Settings', 'hotmart-memberpress-pro' ), __( 'Settings', 'hotmart-memberpress-pro' ), 'manage_options', 'hotmart-memberpress-pro-settings', array( $this, 'settings' ) );
	}

	public function overview(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'hotmart-memberpress-pro' ) );
		}
		$counts = $this->events->counts_by_status();
		$latest = $this->events->latest();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Hotmart MemberPress Overview', 'hotmart-memberpress-pro' ); ?></h1>
			<table class="widefat striped" style="max-width:900px">
				<tbody>
					<tr><th><?php esc_html_e( 'Webhook URL', 'hotmart-memberpress-pro' ); ?></th><td><code><?php echo esc_html( rest_url( 'hmp/v1/webhook' ) ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'MemberPress', 'hotmart-memberpress-pro' ); ?></th><td><?php echo class_exists( 'MeprTransaction' ) ? esc_html__( 'Available', 'hotmart-memberpress-pro' ) : esc_html__( 'Unavailable', 'hotmart-memberpress-pro' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'HOTTOK', 'hotmart-memberpress-pro' ); ?></th><td><?php echo Settings::get( 'hmp_hottok' ) ? esc_html__( 'Configured', 'hotmart-memberpress-pro' ) : esc_html__( 'Not configured', 'hotmart-memberpress-pro' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Webhook', 'hotmart-memberpress-pro' ); ?></th><td><?php echo Settings::get( 'hmp_webhook_enabled' ) ? esc_html__( 'Enabled', 'hotmart-memberpress-pro' ) : esc_html__( 'Disabled', 'hotmart-memberpress-pro' ); ?></td></tr>
				</tbody>
			</table>
			<h2><?php esc_html_e( 'Event totals', 'hotmart-memberpress-pro' ); ?></h2>
			<ul>
				<?php
				$labels = array(
					'received'  => __( 'Received', 'hotmart-memberpress-pro' ),
					'processed' => __( 'Processed', 'hotmart-memberpress-pro' ),
					'failed'    => __( 'Failed', 'hotmart-memberpress-pro' ),
					'ignored'   => __( 'Ignored', 'hotmart-memberpress-pro' ),
				);
				foreach ( $counts as $status => $total ) :
					?>
					<li><strong><?php echo esc_html( $labels[ $status ] ?? $status ); ?>:</strong> <?php echo esc_html( number_format_i18n( $total ) ); ?></li>
				<?php endforeach; ?>
			</ul>
			<h2><?php esc_html_e( 'Latest events', 'hotmart-memberpress-pro' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'ID', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Event', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Transaction', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Buyer', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Status', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Received', 'hotmart-memberpress-pro' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $latest ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No events have been received.', 'hotmart-memberpress-pro' ); ?></td></tr>
				<?php else : foreach ( $latest as $event ) : ?>
					<tr><td><?php echo esc_html( $event->id ); ?></td><td><?php echo esc_html( $event->hotmart_event ); ?></td><td><?php echo esc_html( $event->transaction_code ?: '—' ); ?></td><td><?php echo esc_html( $event->buyer_email ?: '—' ); ?></td><td><?php echo esc_html( $event->status ); ?></td><td><?php echo esc_html( $event->received_at ); ?></td></tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'hotmart-memberpress-pro' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Hotmart MemberPress Settings', 'hotmart-memberpress-pro' ); ?></h1>
			<form action="options.php" method="post">
				<?php settings_fields( 'hmp_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="hmp_hottok"><?php esc_html_e( 'HOTTOK', 'hotmart-memberpress-pro' ); ?></label></th><td><input class="regular-text" type="password" id="hmp_hottok" name="hmp_hottok" value="" autocomplete="new-password"><p class="description"><?php echo Settings::get( 'hmp_hottok' ) ? esc_html__( 'A token is configured. Leave blank to keep it unchanged.', 'hotmart-memberpress-pro' ) : esc_html__( 'Enter the HOTTOK supplied by Hotmart.', 'hotmart-memberpress-pro' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Webhook', 'hotmart-memberpress-pro' ); ?></th><td><input type="hidden" name="hmp_webhook_enabled" value="0"><label><input type="checkbox" name="hmp_webhook_enabled" value="1" <?php checked( Settings::get( 'hmp_webhook_enabled' ), 1 ); ?>> <?php esc_html_e( 'Enable webhook processing', 'hotmart-memberpress-pro' ); ?></label></td></tr>
					<tr><th scope="row"><label for="hmp_payload_retention_days"><?php esc_html_e( 'Retention days', 'hotmart-memberpress-pro' ); ?></label></th><td><input type="number" min="1" id="hmp_payload_retention_days" name="hmp_payload_retention_days" value="<?php echo esc_attr( Settings::get( 'hmp_payload_retention_days' ) ); ?>"></td></tr>
					<tr><th scope="row"><label for="hmp_default_grace_period_days"><?php esc_html_e( 'Default grace period days', 'hotmart-memberpress-pro' ); ?></label></th><td><input type="number" min="0" id="hmp_default_grace_period_days" name="hmp_default_grace_period_days" value="<?php echo esc_attr( Settings::get( 'hmp_default_grace_period_days' ) ); ?>"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Debug mode', 'hotmart-memberpress-pro' ); ?></th><td><input type="hidden" name="hmp_debug_mode" value="0"><label><input type="checkbox" name="hmp_debug_mode" value="1" <?php checked( Settings::get( 'hmp_debug_mode' ), 1 ); ?>> <?php esc_html_e( 'Enable debug mode', 'hotmart-memberpress-pro' ); ?></label></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

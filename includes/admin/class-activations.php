<?php
namespace HMP\Admin;

use HMP\MemberPress\Revocation_Service;
use HMP\Repositories\Activation_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activations {
	private Activation_Repository $activations;
	private Revocation_Service $revocations;

	public function __construct( Activation_Repository $activations, Revocation_Service $revocations ) {
		$this->activations = $activations;
		$this->revocations = $revocations;
	}

	public function register(): void {
		add_action( 'admin_post_hmp_revoke_activation', array( $this, 'revoke' ) );
		add_action( 'admin_post_hmp_reactivate_activation', array( $this, 'reactivate' ) );
	}

	public function render(): void {
		$this->authorize();
		$status = sanitize_key( $_GET['status'] ?? '' );
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$args   = array( 'status' => $status, 'search' => $search, 'limit' => 50, 'offset' => ( $paged - 1 ) * 50 );
		$rows   = $this->activations->query( $args );
		$total  = $this->activations->count( $args );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Membership activations', 'hotmart-memberpress-pro' ); ?></h1>
			<?php $this->notice(); ?>
			<form method="get"><input type="hidden" name="page" value="hotmart-memberpress-pro-activations"><select name="status"><option value=""><?php esc_html_e( 'All statuses', 'hotmart-memberpress-pro' ); ?></option><?php foreach ( array( 'active', 'canceled', 'revoked' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $this->status_label( $value ) ); ?></option><?php endforeach; ?></select> <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Transaction or subscription', 'hotmart-memberpress-pro' ); ?>"> <?php submit_button( __( 'Filter', 'hotmart-memberpress-pro' ), 'secondary', '', false ); ?></form>
			<p><?php echo esc_html( sprintf( __( '%d activations found.', 'hotmart-memberpress-pro' ), $total ) ); ?></p>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'User', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Membership', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Hotmart transaction', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'MemberPress transaction', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Subscription', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Status', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Start', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Expiration', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Revocation reason', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Last manual action', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Actions', 'hotmart-memberpress-pro' ); ?></th></tr></thead><tbody>
			<?php if ( empty( $rows ) ) : ?><tr><td colspan="11"><?php esc_html_e( 'No activations match the filters.', 'hotmart-memberpress-pro' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $rows as $row ) : $user = get_user_by( 'id', $row->user_id ); ?>
				<tr><td><?php echo esc_html( $user ? $user->user_email : '#' . $row->user_id ); ?></td><td><?php echo esc_html( get_the_title( (int) $row->membership_id ) ?: '#' . $row->membership_id ); ?></td><td><?php echo esc_html( $row->hotmart_transaction ); ?></td><td><?php echo esc_html( $row->memberpress_transaction_id ?: '—' ); ?></td><td><?php echo esc_html( $row->hotmart_subscription ?: '—' ); ?></td><td><?php echo esc_html( $this->status_label( $row->status ) ); ?></td><td><?php echo esc_html( $row->starts_at ?: '—' ); ?></td><td><?php echo esc_html( $row->expires_at ?: '—' ); ?></td><td><?php echo esc_html( $row->revocation_reason ?: '—' ); ?></td><td><?php echo esc_html( ! empty( $row->manual_action_at ) ? $row->manual_action . ' · ' . $row->manual_action_at . ' · ' . $row->manual_action_result : '—' ); ?></td><td>
					<?php if ( 'active' === $row->status || 'canceled' === $row->status ) : ?><a onclick="return confirm('<?php echo esc_js( __( 'Revoke only this activation?', 'hotmart-memberpress-pro' ) ); ?>')" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'hmp_revoke_activation', 'activation_id' => $row->id ), admin_url( 'admin-post.php' ) ), 'hmp_revoke_activation_' . $row->id ) ); ?>"><?php esc_html_e( 'Revoke', 'hotmart-memberpress-pro' ); ?></a><?php else : ?><a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'hmp_reactivate_activation', 'activation_id' => $row->id ), admin_url( 'admin-post.php' ) ), 'hmp_reactivate_activation_' . $row->id ) ); ?>"><?php esc_html_e( 'Reactivate', 'hotmart-memberpress-pro' ); ?></a><?php endif; ?>
				</td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php echo wp_kses_post( paginate_links( array( 'total' => max( 1, (int) ceil( $total / 50 ) ), 'current' => $paged, 'format' => '&paged=%#%' ) ) ); ?>
		</div>
		<?php
	}

	public function revoke(): void {
		$this->authorize();
		$id = absint( $_GET['activation_id'] ?? 0 );
		check_admin_referer( 'hmp_revoke_activation_' . $id );
		$result = $this->revocations->revoke_by_id( $id, sprintf( 'manual:user:%d:%s', get_current_user_id(), current_time( 'mysql', true ) ) );
		$this->record_manual_action( $id, 'revoke', $result );
		$this->redirect( is_wp_error( $result ) ? 'error' : 'revoked' );
	}

	public function reactivate(): void {
		$this->authorize();
		$id = absint( $_GET['activation_id'] ?? 0 );
		check_admin_referer( 'hmp_reactivate_activation_' . $id );
		$result = $this->revocations->reactivate_by_id( $id );
		$this->record_manual_action( $id, 'reactivate', $result );
		$this->redirect( is_wp_error( $result ) ? 'error' : 'reactivated' );
	}

	private function record_manual_action( int $id, string $action, $result ): void {
		$activation = $this->activations->find_by_id( $id );
		if ( ! $activation ) {
			return;
		}
		$this->activations->update_status(
			$id,
			$activation->status,
			array(
				'manual_action'        => $action,
				'manual_action_at'     => current_time( 'mysql', true ),
				'manual_action_result' => is_wp_error( $result ) ? $result->get_error_message() : __( 'Completed successfully.', 'hotmart-memberpress-pro' ),
			)
		);
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'hotmart-memberpress-pro' ) );
		}
	}

	private function status_label( string $status ): string {
		$labels = array(
			'active'   => __( 'Active', 'hotmart-memberpress-pro' ),
			'canceled' => __( 'Canceled', 'hotmart-memberpress-pro' ),
			'revoked'  => __( 'Revoked', 'hotmart-memberpress-pro' ),
		);
		return $labels[ $status ] ?? $status;
	}

	private function redirect( string $notice ): void {
		wp_safe_redirect( add_query_arg( array( 'page' => 'hotmart-memberpress-pro-activations', 'hmp_notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function notice(): void {
		if ( ! empty( $_GET['hmp_notice'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Activation action completed.', 'hotmart-memberpress-pro' ) . '</p></div>';
		}
	}
}

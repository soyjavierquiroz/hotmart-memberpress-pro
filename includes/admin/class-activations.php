<?php
namespace HMP\Admin;

use HMP\MemberPress\Revocation_Service;
use HMP\Repositories\Activation_Repository;
use HMP\Repositories\Event_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activations {
	private Activation_Repository $activations;
	private Revocation_Service $revocations;
	private Event_Repository $events;

	public function __construct( Activation_Repository $activations, Revocation_Service $revocations, Event_Repository $events ) {
		$this->activations = $activations;
		$this->revocations = $revocations;
		$this->events = $events;
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
			<form method="get"><input type="hidden" name="page" value="hotmart-memberpress-pro-activations"><select name="status"><option value=""><?php esc_html_e( 'All statuses', 'hotmart-memberpress-pro' ); ?></option><?php foreach ( array( 'active', 'payment_delayed', 'grace', 'refund_requested', 'review', 'canceled', 'revoked' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $this->status_label( $value ) ); ?></option><?php endforeach; ?></select> <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Email, transaction or subscription', 'hotmart-memberpress-pro' ); ?>"> <?php submit_button( __( 'Filter', 'hotmart-memberpress-pro' ), 'secondary', '', false ); ?></form>
			<p><?php echo esc_html( sprintf( __( '%d activations found.', 'hotmart-memberpress-pro' ), $total ) ); ?></p>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'User', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Membership', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Hotmart transaction', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'MemberPress transaction', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Subscription', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Status', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Start', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Expiration', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Grace until', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Last event', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Actions', 'hotmart-memberpress-pro' ); ?></th></tr></thead><tbody>
			<?php if ( empty( $rows ) ) : ?><tr><td colspan="11"><?php esc_html_e( 'No activations match the filters.', 'hotmart-memberpress-pro' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $rows as $row ) : $user = get_user_by( 'id', $row->user_id ); ?>
				<tr<?php echo 'PURCHASE_REFUND_REQUESTED' === $row->last_event ? ' style="background:#fff3cd"' : ''; ?>><td><?php echo esc_html( $user ? $user->user_email : '#' . $row->user_id ); ?></td><td><?php echo esc_html( get_the_title( (int) $row->membership_id ) ?: '#' . $row->membership_id ); ?></td><td><?php echo esc_html( $row->hotmart_transaction ); ?></td><td><?php echo esc_html( $row->memberpress_transaction_id ?: '—' ); ?></td><td><?php echo esc_html( $row->hotmart_subscription ?: '—' ); ?></td><td><?php echo esc_html( $this->status_label( $row->status ) ); ?></td><td><?php echo esc_html( $row->starts_at ?: '—' ); ?></td><td><?php echo esc_html( $row->expires_at ?: '—' ); ?></td><td><?php echo esc_html( $row->grace_until ?: '—' ); ?></td><td><?php echo esc_html( $row->last_event ?: '—' ); ?></td><td>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" onsubmit="return confirm('<?php echo esc_js(sprintf(__('Confirm action on activation #%d?','hotmart-memberpress-pro'),$row->id));?>')"><input type="hidden" name="action" value="<?php echo 'revoked' === $row->status?'hmp_reactivate_activation':'hmp_revoke_activation';?>"><input type="hidden" name="activation_id" value="<?php echo esc_attr($row->id);?>"><?php wp_nonce_field(('revoked'===$row->status?'hmp_reactivate_activation_':'hmp_revoke_activation_').$row->id);?><input name="reason" required placeholder="<?php esc_attr_e('Required reason','hotmart-memberpress-pro');?>"> <?php submit_button('revoked'===$row->status?__('Reactivate','hotmart-memberpress-pro'):__('Revoke','hotmart-memberpress-pro'),'small','submit',false);?></form>
				</td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php echo wp_kses_post( paginate_links( array( 'total' => max( 1, (int) ceil( $total / 50 ) ), 'current' => $paged, 'format' => '&paged=%#%' ) ) ); ?>
		</div>
		<?php
	}

	public function revoke(): void {
		$this->authorize();
		$id = absint( $_POST['activation_id'] ?? 0 );
		check_admin_referer( 'hmp_revoke_activation_' . $id );
		$reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ); if ( '' === $reason ) wp_die(esc_html__('A reason is required.','hotmart-memberpress-pro'));
		$result = $this->revocations->revoke_by_id( $id, 'manual:' . $reason );
		$this->record_manual_action( $id, 'revoke', $result );
		$this->audit($id,'revoke',$reason,$result);
		$this->redirect( is_wp_error( $result ) ? 'error' : 'revoked' );
	}

	public function reactivate(): void {
		$this->authorize();
		$id = absint( $_POST['activation_id'] ?? 0 );
		check_admin_referer( 'hmp_reactivate_activation_' . $id );
		$reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ); if ( '' === $reason ) wp_die(esc_html__('A reason is required.','hotmart-memberpress-pro'));
		$result = $this->revocations->reactivate_by_id( $id );
		$this->record_manual_action( $id, 'reactivate', $result );
		$this->audit($id,'reactivate',$reason,$result);
		$this->redirect( is_wp_error( $result ) ? 'error' : 'reactivated' );
	}
	private function audit(int $id,string $action,string $reason,$result): void { $activation=$this->activations->find_by_id($id); $this->events->record_audit($action,array('object_type'=>'activation','object_id'=>$id,'user_id'=>$activation->user_id??0,'reason'=>$reason,'result'=>is_wp_error($result)?$result->get_error_message():'completed')); }

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
			'grace'    => __( 'Grace', 'hotmart-memberpress-pro' ),
			'payment_delayed' => __( 'Payment delayed', 'hotmart-memberpress-pro' ),
			'refund_requested' => __( 'Refund requested', 'hotmart-memberpress-pro' ),
			'review' => __( 'Needs review', 'hotmart-memberpress-pro' ),
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

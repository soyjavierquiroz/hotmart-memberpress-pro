<?php
namespace HMP\Admin;

use HMP\Events\Event_Processor;
use HMP\Repositories\Event_Repository;
use HMP\Webhook\Payload_Normalizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Webhooks {
	private Event_Repository $events;
	private Event_Processor $processor;
	private Payload_Normalizer $normalizer;

	public function __construct( Event_Repository $events, Event_Processor $processor, Payload_Normalizer $normalizer ) {
		$this->events     = $events;
		$this->processor  = $processor;
		$this->normalizer = $normalizer;
	}

	public function register(): void {
		add_action( 'admin_post_hmp_reprocess_event', array( $this, 'reprocess' ) );
		add_action( 'admin_post_hmp_ignore_event', array( $this, 'ignore' ) );
	}

	public function render(): void {
		$this->authorize();
		if ( isset( $_GET['view_event'] ) ) {
			$this->render_payload( absint( $_GET['view_event'] ) );
			return;
		}
		$status = sanitize_key( $_GET['status'] ?? '' );
		$event  = sanitize_text_field( wp_unslash( $_GET['event'] ?? '' ) );
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$args   = array( 'status' => $status, 'event' => $event, 'search' => $search, 'limit' => 50, 'offset' => ( $paged - 1 ) * 50 );
		$rows   = $this->events->query( $args );
		$total  = $this->events->count( $args );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Received webhooks', 'hotmart-memberpress-pro' ); ?></h1>
			<?php $this->notice(); ?>
			<form method="get">
				<input type="hidden" name="page" value="hotmart-memberpress-pro-webhooks">
				<select name="status"><option value=""><?php esc_html_e( 'All statuses', 'hotmart-memberpress-pro' ); ?></option><?php foreach ( array( 'received', 'processed', 'failed', 'ignored' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $this->status_label( $value ) ); ?></option><?php endforeach; ?></select>
				<select name="event"><option value=""><?php esc_html_e( 'All events', 'hotmart-memberpress-pro' ); ?></option><?php foreach ( $this->events->event_names() as $name ) : ?><option value="<?php echo esc_attr( $name ); ?>" <?php selected( $event, $name ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Email or transaction', 'hotmart-memberpress-pro' ); ?>">
				<?php submit_button( __( 'Filter', 'hotmart-memberpress-pro' ), 'secondary', '', false ); ?>
			</form>
			<p><?php echo esc_html( sprintf( __( '%d events found.', 'hotmart-memberpress-pro' ), $total ) ); ?></p>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Date', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Event', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Transaction', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Subscription', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Email', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Status', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Attempts', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Message', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Actions', 'hotmart-memberpress-pro' ); ?></th></tr></thead><tbody>
			<?php if ( empty( $rows ) ) : ?><tr><td colspan="9"><?php esc_html_e( 'No webhook events match the filters.', 'hotmart-memberpress-pro' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
				<tr><td><?php echo esc_html( $row->received_at ); ?></td><td><?php echo esc_html( $row->hotmart_event ); ?></td><td><?php echo esc_html( $row->transaction_code ?: '—' ); ?></td><td><?php echo esc_html( $row->subscription_code ?: '—' ); ?></td><td><?php echo esc_html( $row->buyer_email ?: '—' ); ?></td><td><?php echo esc_html( $this->status_label( $row->status ) ); ?></td><td><?php echo esc_html( $row->attempts ); ?></td><td><?php echo esc_html( $row->result_message ?: '—' ); ?></td><td>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'hotmart-memberpress-pro-webhooks', 'view_event' => $row->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View payload', 'hotmart-memberpress-pro' ); ?></a>
					<?php if ( 'failed' === $row->status ) : ?> | <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'hmp_reprocess_event', 'event_id' => $row->id ), admin_url( 'admin-post.php' ) ), 'hmp_reprocess_event_' . $row->id ) ); ?>"><?php esc_html_e( 'Reprocess', 'hotmart-memberpress-pro' ); ?></a><?php endif; ?>
					<?php if ( 'ignored' !== $row->status ) : ?> | <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'hmp_ignore_event', 'event_id' => $row->id ), admin_url( 'admin-post.php' ) ), 'hmp_ignore_event_' . $row->id ) ); ?>"><?php esc_html_e( 'Mark ignored', 'hotmart-memberpress-pro' ); ?></a><?php endif; ?>
				</td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php echo wp_kses_post( paginate_links( array( 'total' => max( 1, (int) ceil( $total / 50 ) ), 'current' => $paged, 'format' => '&paged=%#%' ) ) ); ?>
		</div>
		<?php
	}

	public function reprocess(): void {
		$this->authorize();
		$id = absint( $_GET['event_id'] ?? 0 );
		check_admin_referer( 'hmp_reprocess_event_' . $id );
		$event = $this->events->find_by_id( $id );
		if ( ! $event || 'failed' !== $event->status ) {
			$this->redirect( 'invalid' );
		}
		$payload = json_decode( $event->payload, true );
		if ( ! is_array( $payload ) ) {
			$this->events->update_status( $id, 'failed', $this->manual_message( __( 'Stored payload is invalid JSON.', 'hotmart-memberpress-pro' ) ) );
			$this->redirect( 'error' );
		}
		$this->events->update( $id, array( 'status' => 'received', 'processed_at' => null, 'result_message' => $this->manual_message( __( 'Manual reprocessing started.', 'hotmart-memberpress-pro' ) ) ) );
		$this->processor->process( $id, $this->normalizer->normalize( $payload ) );
		$updated = $this->events->find_by_id( $id );
		if ( $updated ) {
			$this->events->update_status( $id, $updated->status, $this->manual_message( (string) $updated->result_message ) );
		}
		$this->redirect( 'reprocessed' );
	}

	public function ignore(): void {
		$this->authorize();
		$id = absint( $_GET['event_id'] ?? 0 );
		check_admin_referer( 'hmp_ignore_event_' . $id );
		$this->events->update_status( $id, 'ignored', $this->manual_message( __( 'Manually marked as ignored.', 'hotmart-memberpress-pro' ) ) );
		$this->redirect( 'ignored' );
	}

	private function render_payload( int $id ): void {
		$event = $this->events->find_by_id( $id );
		if ( ! $event ) {
			wp_die( esc_html__( 'Event not found.', 'hotmart-memberpress-pro' ) );
		}
		$decoded = json_decode( $event->payload, true );
		$pretty  = is_array( $decoded ) ? wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : $event->payload;
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Webhook payload', 'hotmart-memberpress-pro' ); ?></h1><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=hotmart-memberpress-pro-webhooks' ) ); ?>">&larr; <?php esc_html_e( 'Back to webhooks', 'hotmart-memberpress-pro' ); ?></a></p><pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #ccd0d4"><?php echo esc_html( $pretty ); ?></pre></div>
		<?php
	}

	private function manual_message( string $message ): string {
		return sprintf(
			/* translators: 1: result message, 2: user ID, 3: UTC date. */
			__( '%1$s Manual action by user #%2$d at %3$s UTC.', 'hotmart-memberpress-pro' ),
			$message,
			get_current_user_id(),
			current_time( 'mysql', true )
		);
	}

	private function status_label( string $status ): string {
		$labels = array(
			'received'  => __( 'Received', 'hotmart-memberpress-pro' ),
			'processed' => __( 'Processed', 'hotmart-memberpress-pro' ),
			'failed'    => __( 'Failed', 'hotmart-memberpress-pro' ),
			'ignored'   => __( 'Ignored', 'hotmart-memberpress-pro' ),
		);
		return $labels[ $status ] ?? $status;
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'hotmart-memberpress-pro' ) );
		}
	}

	private function redirect( string $notice ): void {
		wp_safe_redirect( add_query_arg( array( 'page' => 'hotmart-memberpress-pro-webhooks', 'hmp_notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function notice(): void {
		if ( ! empty( $_GET['hmp_notice'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Webhook action completed.', 'hotmart-memberpress-pro' ) . '</p></div>';
		}
	}
}

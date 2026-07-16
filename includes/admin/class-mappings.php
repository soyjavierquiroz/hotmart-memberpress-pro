<?php
namespace HMP\Admin;

use HMP\Repositories\Mapping_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mappings {
	private Mapping_Repository $repository;

	public function __construct( Mapping_Repository $repository ) {
		$this->repository = $repository;
	}

	public function register(): void {
		add_action( 'admin_post_hmp_save_mapping', array( $this, 'save' ) );
		add_action( 'admin_post_hmp_toggle_mapping', array( $this, 'toggle' ) );
		add_action( 'admin_post_hmp_delete_mapping', array( $this, 'delete' ) );
	}

	public function render(): void {
		$this->authorize();
		$edit = isset( $_GET['mapping_id'] ) ? $this->repository->find_by_id( absint( $_GET['mapping_id'] ) ) : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Hotmart mappings', 'hotmart-memberpress-pro' ); ?></h1>
			<?php $this->notice(); ?>
			<h2><?php echo $edit ? esc_html__( 'Edit mapping', 'hotmart-memberpress-pro' ) : esc_html__( 'Create mapping', 'hotmart-memberpress-pro' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hmp_save_mapping">
				<input type="hidden" name="mapping_id" value="<?php echo esc_attr( $edit->id ?? 0 ); ?>">
				<?php wp_nonce_field( 'hmp_save_mapping' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="hmp_name"><?php esc_html_e( 'Name', 'hotmart-memberpress-pro' ); ?></label></th><td><input required class="regular-text" id="hmp_name" name="name" value="<?php echo esc_attr( $edit->name ?? '' ); ?>"></td></tr>
					<tr><th><label for="hmp_product_id"><?php esc_html_e( 'Product ID', 'hotmart-memberpress-pro' ); ?></label></th><td><input class="regular-text" id="hmp_product_id" name="product_id" value="<?php echo esc_attr( $edit->product_id ?? '' ); ?>"></td></tr>
					<tr><th><label for="hmp_offer_code"><?php esc_html_e( 'Offer code', 'hotmart-memberpress-pro' ); ?></label></th><td><input class="regular-text" id="hmp_offer_code" name="offer_code" value="<?php echo esc_attr( $edit->offer_code ?? '' ); ?>"></td></tr>
					<tr><th><label for="hmp_plan_id"><?php esc_html_e( 'Plan ID', 'hotmart-memberpress-pro' ); ?></label></th><td><input class="regular-text" id="hmp_plan_id" name="plan_id" value="<?php echo esc_attr( $edit->plan_id ?? '' ); ?>"></td></tr>
					<tr><th><label for="hmp_membership_id"><?php esc_html_e( 'MemberPress membership', 'hotmart-memberpress-pro' ); ?></label></th><td><?php $this->membership_select( (int) ( $edit->membership_id ?? 0 ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Access duration', 'hotmart-memberpress-pro' ); ?></th><td><input type="number" min="0" name="access_duration_value" value="<?php echo esc_attr( $edit->access_duration_value ?? '' ); ?>"> <?php $this->select( 'access_duration_unit', array( '' => __( 'Membership default', 'hotmart-memberpress-pro' ), 'days' => __( 'Days', 'hotmart-memberpress-pro' ), 'weeks' => __( 'Weeks', 'hotmart-memberpress-pro' ), 'months' => __( 'Months', 'hotmart-memberpress-pro' ), 'years' => __( 'Years', 'hotmart-memberpress-pro' ) ), $edit->access_duration_unit ?? '' ); ?></td></tr>
					<tr><th><label for="hmp_grace"><?php esc_html_e( 'Grace period days', 'hotmart-memberpress-pro' ); ?></label></th><td><input type="number" min="0" id="hmp_grace" name="grace_period_days" value="<?php echo esc_attr( $edit->grace_period_days ?? 0 ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Cancellation policy', 'hotmart-memberpress-pro' ); ?></th><td><?php $this->select( 'cancellation_policy', array( 'period_end' => __( 'At period end', 'hotmart-memberpress-pro' ), 'immediate' => __( 'Immediately', 'hotmart-memberpress-pro' ) ), $edit->cancellation_policy ?? 'period_end' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Refund policy', 'hotmart-memberpress-pro' ); ?></th><td><?php $this->select( 'refund_policy', array( 'immediate' => __( 'Immediately', 'hotmart-memberpress-pro' ), 'period_end' => __( 'At period end', 'hotmart-memberpress-pro' ) ), $edit->refund_policy ?? 'immediate' ); ?></td></tr>
					<tr><th><label for="hmp_priority"><?php esc_html_e( 'Priority', 'hotmart-memberpress-pro' ); ?></label></th><td><input type="number" id="hmp_priority" name="priority" value="<?php echo esc_attr( $edit->priority ?? 10 ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Active', 'hotmart-memberpress-pro' ); ?></th><td><input type="hidden" name="active" value="0"><label><input type="checkbox" name="active" value="1" <?php checked( (int) ( $edit->active ?? 1 ), 1 ); ?>> <?php esc_html_e( 'Use this mapping', 'hotmart-memberpress-pro' ); ?></label></td></tr>
				</table>
				<?php submit_button( $edit ? __( 'Update mapping', 'hotmart-memberpress-pro' ) : __( 'Create mapping', 'hotmart-memberpress-pro' ) ); ?>
			</form>
			<h2><?php esc_html_e( 'Existing mappings', 'hotmart-memberpress-pro' ); ?></h2>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Product / offer / plan', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Membership', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Priority', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Status', 'hotmart-memberpress-pro' ); ?></th><th><?php esc_html_e( 'Actions', 'hotmart-memberpress-pro' ); ?></th></tr></thead><tbody>
			<?php foreach ( $this->repository->all() as $mapping ) : ?>
				<tr><td><?php echo esc_html( $mapping->name ); ?></td><td><?php echo esc_html( implode( ' / ', array_filter( array( $mapping->product_id, $mapping->offer_code, $mapping->plan_id ) ) ) ?: '—' ); ?></td><td><?php echo esc_html( get_the_title( (int) $mapping->membership_id ) ?: $mapping->membership_id ); ?></td><td><?php echo esc_html( $mapping->priority ); ?></td><td><?php echo $mapping->active ? esc_html__( 'Active', 'hotmart-memberpress-pro' ) : esc_html__( 'Inactive', 'hotmart-memberpress-pro' ); ?></td><td>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'hotmart-memberpress-pro-mappings', 'mapping_id' => $mapping->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'hotmart-memberpress-pro' ); ?></a> |
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'hmp_toggle_mapping', 'mapping_id' => $mapping->id ), admin_url( 'admin-post.php' ) ), 'hmp_toggle_mapping_' . $mapping->id ) ); ?>"><?php echo $mapping->active ? esc_html__( 'Deactivate', 'hotmart-memberpress-pro' ) : esc_html__( 'Activate', 'hotmart-memberpress-pro' ); ?></a> |
					<a onclick="return confirm('<?php echo esc_js( __( 'Delete this mapping?', 'hotmart-memberpress-pro' ) ); ?>')" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'hmp_delete_mapping', 'mapping_id' => $mapping->id ), admin_url( 'admin-post.php' ) ), 'hmp_delete_mapping_' . $mapping->id ) ); ?>"><?php esc_html_e( 'Delete', 'hotmart-memberpress-pro' ); ?></a>
				</td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php
	}

	public function save(): void {
		$this->authorize();
		check_admin_referer( 'hmp_save_mapping' );
		$id   = absint( $_POST['mapping_id'] ?? 0 );
		$data = array(
			'name'                  => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'product_id'            => $this->nullable_text( $_POST['product_id'] ?? '' ),
			'offer_code'            => $this->nullable_text( $_POST['offer_code'] ?? '' ),
			'plan_id'               => $this->nullable_text( $_POST['plan_id'] ?? '' ),
			'membership_id'         => absint( $_POST['membership_id'] ?? 0 ),
			'access_duration_value' => empty( $_POST['access_duration_value'] ) ? null : absint( $_POST['access_duration_value'] ),
			'access_duration_unit'  => in_array( $_POST['access_duration_unit'] ?? '', array( 'days', 'weeks', 'months', 'years' ), true ) ? sanitize_key( $_POST['access_duration_unit'] ) : null,
			'grace_period_days'     => absint( $_POST['grace_period_days'] ?? 0 ),
			'cancellation_policy'   => in_array( $_POST['cancellation_policy'] ?? '', array( 'period_end', 'immediate' ), true ) ? sanitize_key( $_POST['cancellation_policy'] ) : 'period_end',
			'refund_policy'         => in_array( $_POST['refund_policy'] ?? '', array( 'period_end', 'immediate' ), true ) ? sanitize_key( $_POST['refund_policy'] ) : 'immediate',
			'priority'              => (int) ( $_POST['priority'] ?? 10 ),
			'active'                => empty( $_POST['active'] ) ? 0 : 1,
		);
		if ( '' === $data['name'] || $data['membership_id'] <= 0 ) {
			$this->redirect( 'invalid' );
		}
		$result = $id ? $this->repository->update( $id, $data ) : $this->repository->insert( $data );
		$this->redirect( is_wp_error( $result ) ? 'error' : 'saved' );
	}

	public function toggle(): void {
		$this->authorize();
		$id = absint( $_GET['mapping_id'] ?? 0 );
		check_admin_referer( 'hmp_toggle_mapping_' . $id );
		$mapping = $this->repository->find_by_id( $id );
		if ( $mapping ) {
			$this->repository->update_status( $id, $mapping->active ? 'inactive' : 'active' );
		}
		$this->redirect( 'updated' );
	}

	public function delete(): void {
		$this->authorize();
		$id = absint( $_GET['mapping_id'] ?? 0 );
		check_admin_referer( 'hmp_delete_mapping_' . $id );
		$this->repository->delete( $id );
		$this->redirect( 'deleted' );
	}

	private function membership_select( int $selected ): void {
		$post_type = class_exists( 'MeprProduct' ) ? \MeprProduct::$cpt : 'memberpressproduct';
		$products  = get_posts( array( 'post_type' => $post_type, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		echo '<select required id="hmp_membership_id" name="membership_id"><option value="">' . esc_html__( 'Select a membership', 'hotmart-memberpress-pro' ) . '</option>';
		foreach ( $products as $product ) {
			printf( '<option value="%d" %s>%s (#%d)</option>', (int) $product->ID, selected( $selected, $product->ID, false ), esc_html( $product->post_title ), (int) $product->ID );
		}
		echo '</select>';
	}

	private function select( string $name, array $options, string $selected ): void {
		echo '<select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	private function nullable_text( $value ): ?string {
		$value = sanitize_text_field( wp_unslash( $value ) );
		return '' === $value ? null : $value;
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'hotmart-memberpress-pro' ) );
		}
	}

	private function redirect( string $notice ): void {
		wp_safe_redirect( add_query_arg( array( 'page' => 'hotmart-memberpress-pro-mappings', 'hmp_notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function notice(): void {
		if ( ! empty( $_GET['hmp_notice'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Mapping action completed.', 'hotmart-memberpress-pro' ) . '</p></div>';
		}
	}
}

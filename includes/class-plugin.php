<?php
namespace HMP;

use HMP\Admin\Admin;
use HMP\Events\Event_Processor;
use HMP\MemberPress\MemberPress_Service;
use HMP\MemberPress\Revocation_Service;
use HMP\Repositories\Activation_Repository;
use HMP\Repositories\Event_Repository;
use HMP\Repositories\Mapping_Repository;
use HMP\Rest\Webhook_Controller;
use HMP\Webhook\Authenticator;
use HMP\Webhook\Event_Key;
use HMP\Webhook\Payload_Normalizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static ?Plugin $instance = null;
	private bool $started = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function run(): void {
		if ( $this->started ) {
			return;
		}
		$this->started = true;

		Activator::maybe_upgrade();
		Settings::register();

		$events      = new Event_Repository();
		$activations = new Activation_Repository();
		$mappings    = new Mapping_Repository();
		$memberpress = new MemberPress_Service( $mappings, $activations );
		$revocations = new Revocation_Service( $activations, $mappings );
		$normalizer  = new Payload_Normalizer();
		$processor   = new Event_Processor( $events, $memberpress, $revocations );
		$controller  = new Webhook_Controller(
			$events,
			new Authenticator(),
			$normalizer,
			new Event_Key(),
			$processor
		);

		$controller->register();
		( new Admin( $events, $mappings, $activations, $processor, $normalizer, $revocations ) )->register();
		Cleanup::register();
	}
}

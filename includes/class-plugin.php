<?php
namespace HMP;

use HMP\Admin\Admin;
use HMP\Events\Event_Processor;
use HMP\MemberPress\MemberPress_Service;
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

		Settings::register();

		$events      = new Event_Repository();
		$activations = new Activation_Repository();
		$mappings    = new Mapping_Repository();
		$memberpress = new MemberPress_Service( $mappings, $activations );
		$processor   = new Event_Processor( $events, $memberpress );
		$controller  = new Webhook_Controller(
			$events,
			new Authenticator(),
			new Payload_Normalizer(),
			new Event_Key(),
			$processor
		);

		$controller->register();
		( new Admin( $events ) )->register();
		Cleanup::register();
	}
}

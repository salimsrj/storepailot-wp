<?php
/**
 * Action/filter registry.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Loader {

	/** @var array<int, array{hook:string,component:object,callback:string,priority:int,args:int}> */
	private array $actions = array();

	/** @var array<int, array{hook:string,component:object,callback:string,priority:int,args:int}> */
	private array $filters = array();

	public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $args = 1 ): void {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'args' );
	}

	public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $args = 1 ): void {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'args' );
	}

	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['args'] );
		}
		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['args'] );
		}
	}
}

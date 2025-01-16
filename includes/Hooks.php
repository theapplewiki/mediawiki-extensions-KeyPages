<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\KeyPages;

use MediaWiki\SpecialPage\Hook\WgQueryPagesHook;

class Hooks implements WgQueryPagesHook {
	/**
	 * This hook is called when initialising list of QueryPage subclasses. Use this
	 * hook to add new query pages to be updated with maintenance/updateSpecialPages.php.
	 *
	 * @since 1.35
	 *
	 * @param array[] &$qp List of QueryPages
	 *  Format: [ string $class, string $specialPageName, ?int $limit (optional) ].
	 *  Limit defaults to $wgQueryCacheLimit if not given.
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onWgQueryPages( &$queryPages ) {
		$queryPages[] = [ SpecialWantedKeys::class, 'WantedKeys' ];
	}
}

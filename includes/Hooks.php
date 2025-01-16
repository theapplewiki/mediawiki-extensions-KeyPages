<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\KeyPages;

use MediaWiki\Hook\RandomPageQueryHook;
use MediaWiki\Hook\WantedPages__getQueryInfoHook;
use MediaWiki\SpecialPage\Hook\WgQueryPagesHook;

class Hooks implements
	WgQueryPagesHook,
	RandomPageQueryHook,
	WantedPages__getQueryInfoHook {

	/**
	 * @inheritDoc
	 */
	public function onWgQueryPages( &$queryPages ) {
		$queryPages[] = [ SpecialWantedKeys::class, 'WantedKeys' ];
	}

	/**
	 * @inheritDoc
	 */
	public function onRandomPageQuery( &$tables, &$conds, &$joinConds ) {
		// Exclude key pages from Special:Random, except if specifically requested (Special:Random/Keys)
		if ($conds[ 'page_namespace' ] != [ NS_KEYS ]) {
			$conds[] = 'page_namespace != ' . NS_KEYS;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onWantedPages__getQueryInfo( $wantedPages, &$query ) {
		// Exclude key pages from Special:WantedPages
		$query[ 'conds' ][] = 'lt_namespace != ' . NS_KEYS;
	}

}

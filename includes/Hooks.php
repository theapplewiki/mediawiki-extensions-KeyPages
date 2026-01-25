<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\KeyPages;

use MediaWiki\Extension\CodeEditor\Hooks\CodeEditorGetPageLanguageHook;
use MediaWiki\Hook\RandomPageQueryHook;
use MediaWiki\Hook\WantedPages__getQueryInfoHook;
use MediaWiki\Revision\Hook\ContentHandlerDefaultModelForHook;
use MediaWiki\SpecialPage\Hook\WgQueryPagesHook;
use MediaWiki\Title\Title;

class Hooks implements
	WgQueryPagesHook,
	RandomPageQueryHook,
	WantedPages__getQueryInfoHook,
	ContentHandlerDefaultModelForHook,
	CodeEditorGetPageLanguageHook {

	public static function onRegistration() {
		define( 'CONTENT_MODEL_KEYS', 'keys' );
	}

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

	/**
	 * @inheritDoc
	 */
	public function onContentHandlerDefaultModelFor( $title, &$model ) {
		// Use our content model in the Keys namespace by default
		if ( $title->inNamespace( NS_KEYS ) ) {
			$model = CONTENT_MODEL_KEYS;
			return false;
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function onCodeEditorGetPageLanguage(
		Title $title,
		?string &$languageCode,
		string $model,
		string $format
	) {
		if ( $title->hasContentModel( CONTENT_MODEL_KEYS ) ) {
			$languageCode = 'json';
			return false;
		}

		return true;
	}

}

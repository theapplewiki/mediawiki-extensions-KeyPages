<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\KeyPages;

use MediaWiki\Content\JsonContent;

class KeysContent extends JsonContent {

	/**
	 * @param string $text
	 */
	public function __construct( $text ) {
		parent::__construct( $text, CONTENT_MODEL_KEYS );
	}

	/**
	 * @inheritDoc
	 */
	public function getWikitextForTransclusion() {
		return self::getText();
	}

	/**
	 * @inheritDoc
	 */
	public function isCountable( $hasLinks = null ) {
		// Don't count in {{NUMBEROFARTICLES}}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function isValid() {
		// TODO
		return true;
	}

}

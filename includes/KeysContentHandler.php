<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\KeyPages;

use Exception;
use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\ValidationParams;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\Parsoid\ParsoidParserFactory;
use MediaWiki\Title\Title;

class KeysContentHandler extends JsonContentHandler {

	private ParserFactory $parserFactory;
	private ParsoidParserFactory $parsoidParserFactory;

	public function __construct(
		string $modelId,
		ParserFactory $parserFactory,
		ParsoidParserFactory $parsoidParserFactory
	) {
		parent::__construct($modelId, [ CONTENT_FORMAT_JSON ]);
		$this->parserFactory = $parserFactory;
		$this->parsoidParserFactory = $parsoidParserFactory;
	}

	/**
	 * @inheritDoc
	 */
	protected function getContentClass() {
		return KeysContent::class;
	}

	/**
	 * @inheritDoc
	 */
	public function validateSave(
		Content $content,
		ValidationParams $validationParams
	) {
		// @phan-var KeysContent $content

		$status = parent::validateSave( $content, $validationParams );
		if ( !$status->isOK() ) {
			return $content->validate();
		}

		return $status;
	}

	/**
	 * @inheritDoc
	 */
	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$parserOutput
	) {
		// @phan-var KeysContent $content

		$parserOptions = $cpoParams->getParserOptions();
		$revId = $cpoParams->getRevId();

		// Get parser
		if ( $parserOptions->getUseParsoid() ) {
			$parser = $this->parsoidParserFactory->create();
			$extraArgs = [ $cpoParams->getPreviousOutput() ];
		} else {
			$parser = $this->parserFactory->getInstance();
			$extraArgs = [];
		}

		// If HTML was not requested, we don't need to do anything
		if ( !$cpoParams->getGenerateHtml() ) {
			$parserOutput->setRawText( null );
			return;
		}

		// Invoke our own validation. If not valid, don't render the page
		if ( !$content->isValid() ) {
			$error = wfMessage( 'invalid-json-data' )->parse();
			$parserOutput->setRawText( $error );
		}

		// Render a wikitext page containing the Scribunto invocation
		$parserOutput = $parser->parse(
			'{{int:keypages-page-content}}',
			$cpoParams->getPage(),
			$parserOptions,
			true,
			true,
			$revId,
			...$extraArgs
		);
	}

}

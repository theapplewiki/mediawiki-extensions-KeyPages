<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\KeyPages;

use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Linker\LinksMigration;
use MediaWiki\SpecialPage\WantedQueryPage;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IConnectionProvider;

if ( !defined( 'NS_KEYS' ) ) {
	define( 'NS_KEYS', 2304 );
}

class SpecialWantedKeys extends WantedQueryPage {

	private LinksMigration $linksMigration;
	private array $groupedResults = [];

	/**
	 * @param IConnectionProvider $dbProvider
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param LinksMigration $linksMigration
	 */
	public function __construct(
		IConnectionProvider $dbProvider,
		LinkBatchFactory $linkBatchFactory,
		LinksMigration $linksMigration
	) {
		parent::__construct( 'WantedKeys' );
		$this->setDatabaseProvider( $dbProvider );
		$this->setLinkBatchFactory( $linkBatchFactory );
		$this->linksMigration = $linksMigration;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$transcluded = $this->including();

		if ( $transcluded ) {
			$this->limit = (int)$par;
			$this->offset = 0;
		} else {
			$out = $this->getOutput();
			$out->addWikiMsg( 'wantedkeys-intro' );
		}

		$this->shownavigation = !$transcluded;
		parent::execute( $par );
	}

	/**
	 * @inheritDoc
	 */
	protected function outputResults( $out, $skin, $dbr, $res, $num, $offset ) {
		if ( $num == 0 ) {
			return;
		}

		$html = [];
		$html[] = '<div class="mw-parser-output">';
		$html[] = '<table class="wikitable sortable hlist">';
		$html[] = '<tr class="citizen-overflow-sticky-header">';
		$html[] = '<th>' . $this->msg( 'wantedkeys-build' )->escaped() . '</th>';
		$html[] = '<th>' . $this->msg( 'wantedkeys-devices' )->escaped() . '</th>';
		$html[] = '</tr>';

		foreach ( $this->groupedResults as $item ) {
			$html[] = $this->formatResult( $skin, $item );
		}

		$html[] = '</table>';
		$html[] = '</div>';

		$out->addWikiTextAsInterface( '<templatestyles src="Hlist/styles.css" />' );
		$out->addHTML( implode( '', $html ) );
	}

	/**
	 * @inheritDoc
	 */
	public function formatResult( $skin, $result ) {
		$linkRenderer = $this->getLinkRenderer();

		$html = [];
		$html[] = '<tr>';

		$title = Title::makeTitleSafe( $result['namespace'], $result['title'] );
		if ( $title instanceof Title ) {
			$html[] = '<th>' . htmlspecialchars( $title->getText() ) . '</th>';
			$html[] = '<td><ul>';

			foreach ( $result['devices'] as $device ) {
				// Reconstruct the title
				$deviceTitle = Title::makeTitleSafe( $result['namespace'], $title->getText() . '_(' . $device . ')');

				// Has the page been created since the query was run?
				// I don't like that this has to show the created page at all, it should just be skipped...
				// but this is the convention Special:WantedPages uses.
				if ( ($this->isCached() || $this->forceExistenceCheck() ) && $this->existenceCheck( $deviceTitle ) ) {
					$link = '<del>' . $linkRenderer->makeLink( $deviceTitle, htmlspecialchars( $device ) ) . '</del>';
				} else {
					$link = $linkRenderer->makeBrokenLink( $deviceTitle, htmlspecialchars( $device ) );
				}

				$html[] = '<li>' . $link . '</li>';
			}

			$html[] = '</ul></td>';
		} else {
			$html[] = '<td colspan="2">' . $this->msg( 'wantedpages-badtitle', $result['title'] )->escaped() . '</td>';
		}

		return implode( '', $html );
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		$dbr = $this->getDatabaseProvider()->getReplicaDatabase();
		[ $blNamespace, $blTitle ] = $this->linksMigration->getTitleFields( 'pagelinks' );
		$queryInfo = $this->linksMigration->getQueryInfo( 'pagelinks', 'pagelinks' );
		$query = [
			'tables' => array_merge( $queryInfo['tables'], [
				'page'
			] ),
			'fields' => [
				'namespace' => $blNamespace,
				'title' => $blTitle
			],
			'conds' => [
				'page.page_namespace' => null,
				$dbr->expr( $blNamespace, '=', NS_KEYS )
			],
			'options' => [
				'GROUP BY' => [ $blTitle ]
			],
			'join_conds' => array_merge( [
				'page' => [
					'LEFT JOIN', [
						'page.page_namespace = ' . $blNamespace,
						'page.page_title = ' . $blTitle
					]
				]
			], $queryInfo['joins'] )
		];

		return $query;
	}

	/**
	 * @inheritDoc
	 */
	protected function getOrderFields() {
		return [ 'title' ];
	}

	/**
	 * @inheritDoc
	 */
	protected function preprocessResults( $db, $res ) {
		parent::preprocessResults( $db, $res );

		// Split into groups
		$this->groupedResults = [];

		foreach ( $res as $row ) {
			$matches = [];
			preg_match( '/^(.*)_\((.*)\)$/', $row->title, $matches );
			if ( !$matches || count( $matches ) !== 3 ) {
				continue;
			}

			$baseTitle = $matches[1];
			$device = $matches[2];

			$this->groupedResults[$baseTitle] ??= [
				'namespace' => $row->namespace,
				'title' => $baseTitle,
				'devices' => []
			];

			$this->groupedResults[$baseTitle]['devices'][] = $device;
		}

		// Rewind result pointer
		$res->rewind();
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'maintenance';
	}
}

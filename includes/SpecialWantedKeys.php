<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\WantedKeys;

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
	 * This is the actual workhorse. It does everything needed to make a
	 * real, honest-to-gosh query page.
	 * @stable to override
	 * @param string|null $par
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
	 * Format and output report results using the given information plus
	 * OutputPage
	 *
	 * @stable to override
	 *
	 * @param OutputPage $out OutputPage to print to
	 * @param Skin $skin User skin to use
	 * @param IReadableDatabase $dbr Database (read) connection to use
	 * @param IResultWrapper $res Result pointer
	 * @param int $num Number of available result rows
	 * @param int $offset Paging offset
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
	 * Format an individual result
	 *
	 * @stable to override
	 *
	 * @param Skin $skin Skin to use for UI elements
	 * @param stdClass $result Result row
	 * @return string
	 */
	public function formatResult( $skin, $result ) {
		$linkRenderer = $this->getLinkRenderer();
		$lang = $this->getLanguage();

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
	 * Subclasses return an SQL query here, formatted as an array with the
	 * following keys:
	 *    tables => Table(s) for passing to Database::select()
	 *    fields => Field(s) for passing to Database::select(), may be *
	 *    conds => WHERE conditions
	 *    options => options
	 *    join_conds => JOIN conditions
	 *
	 * Note that the query itself should return the following three columns:
	 * 'namespace', 'title', and 'value'. 'value' is used for sorting.
	 *
	 * These may be stored in the querycache table for expensive queries,
	 * and that cached data will be returned sometimes, so the presence of
	 * extra fields can't be relied upon. The cached 'value' column will be
	 * an integer; non-numeric values are useful only for sorting the
	 * initial query (except if they're timestamps, see usesTimestamps()).
	 *
	 * Don't include an ORDER or LIMIT clause, they will be added.
	 *
	 * @return array
	 * @since 1.18, abstract since 1.43
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
	 * Order by title for pages with the same number of links to them
	 *
	 * @stable to override
	 * @return array
	 * @since 1.29
	 */
	protected function getOrderFields() {
		return [ 'title' ];
	}

	/**
	 * Cache page existence for performance
	 * @stable to override
	 * @param IDatabase $db
	 * @param IResultWrapper $res
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
	 * Under which header this special page is listed in Special:SpecialPages
	 * See messages 'specialpages-group-*' for valid names
	 * This method defaults to group 'other'
	 *
	 * @stable to override
	 *
	 * @return string
	 * @since 1.21
	 */
	protected function getGroupName() {
		return 'maintenance';
	}
}

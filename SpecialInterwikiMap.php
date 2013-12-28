<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is a MediaWiki extension. It is not a valid entry point' );
}

class SpecialInterwikiMap extends SpecialPage {
	function __construct() {
		parent::__construct( 'InterwikiMap', 'interwikimap' );
	}

	public function userCanExecute( User $user ) {
		return true;
	}

	function execute( $par ) {
                global $wgInterwikiMapMinimumSeconds, $wgInterwikiMapBackupUser,
                    $wgInterwikiMapWhitelistPage, $wgInterwikiMapBlacklistPage;
		$user = $this->getUser();
		if ( !$user->isAllowed( 'interwikimap' ) ) {
                        throw new PermissionsError( null, array( array(
                                'interwikimap-notallowed' ) ) );
		}
		$this->setHeaders();
		$output = $this->getOutput();
                $context = $this->getContext();
                $contribsPage = "[[Special:Contributions/$wgInterwikiMapBackupUser]]";
                $isNotExempt = !$user->isAllowed( 'interwikimapnoratelimit' );
                // Don't roll dice or check time
		$result = InterwikiMap::updateInterwikiTable ( false, $isNotExempt, $context );
                if ( is_array ( $result ) ) {
                        if ( $result['status'] == INTERWIKIMAP_NORETRIEVE ) {
                                $output->addWikiMsg ( 'interwikimap-trigger-noretrieve',
                                        $result['url']);
                        }
                        if ( $result['status'] == INTERWIKIMAP_NODECODE ) {
                                $output->addWikiMsg ( 'interwikimap-trigger-nodecode',
                                        $result['url']);
                        }
                } else switch ( $result ) {
                        case INTERWIKIMAP_SUCCESS:
                                $output->addWikiMsg ( 'interwikimap-trigger-success', $contribsPage,
                                        $wgInterwikiMapBackupUser );
                                break;
                        case INTERWIKIMAP_THROTTLED:
                                throw new PermissionsError( null, array( array(
                                'interwikimap-trigger-throttled',
                                        $wgInterwikiMapMinimumSeconds ) ) );
                                break;
                        case INTERWIKIMAP_NOUPDATES:
                                $output->addWikiMsg ( 'interwikimap-trigger-noupdates', $contribsPage,
                                        $wgInterwikiMapBackupUser, $wgInterwikiMapWhitelistPage,
                                        $wgInterwikiMapBlacklistPage );
                                break;
		}
	}
}

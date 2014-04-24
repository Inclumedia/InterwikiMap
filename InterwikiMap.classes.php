<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is a MediaWiki extension. It is not a valid entry point' );
}

class InterwikiMap {
	public static function onArticleSaveComplete ( &$article, &$user, $text, $summary,
		$minoredit, $watchthis, $sectionanchor, &$flags, $revision, &$status, $baseRevId ) {
		global $wgInterwikiMapBackupPage, $wgInterwikiMapWhitelistPage,
			$wgInterwikiMapBlacklistPage;
		// Making sure not to run recursive updates when the script is editing the backup
		// page
		if ( $article->getTitle()->getFullText() == $wgInterwikiMapBackupPage ) {
			return true;
		}
		$realArticle = Article::newFromID ( $article->getId() );
		$context = $realArticle->getContext();
		// Don't roll dice or check timestamp if the whitelist or blacklist was revised;
		// just update the table.
		if ( $article->getTitle()->getFullText() == $wgInterwikiMapWhitelistPage
			|| $article->getTitle()->getFullText() == $wgInterwikiMapBlacklistPage ) {
			InterwikiMap::updateInterwikiTable ( false, false, $context );
		} else {
			InterwikiMap::updateInterwikiTable ( true, false, $context );
		}
		return true;
	}

	public static function updateInterwikiTable ( $rollDice, $checkMinimumTime, $context ) {
		global $wgInterwikiMapSeconds, $wgInterwikiMapOdds, $wgInterwikiMapWhitelistPage,
			$wgInterwikiMapBlacklistPage, $wgInterwikiMapRemoteWikiUrls,
			$wgInterwikiMapApiArgs, $wgInterwikiMapUserAgent,
			$wgInterwikiMapMinimumSeconds, $wgInterwikiMapBackupSummary,
			$wgContLang, $wgVersion, $wgInterwikiMapAttributes;
		// Roll the dice to decide whether to check whether it's time for an update. This
		// will not be done if the update is being done because of a revision to the
		// whitelist or blacklist.
		if ( $rollDice ) {
			if ( rand ( 0, 360 ) > $wgInterwikiMapOdds ) {
				return INTERWIKIMAP_SKIPPED;
			}
		}
		$dbr = wfGetDB( DB_SLAVE );
		$dbw = wfGetDB( DB_MASTER );
		// Timestamp stored in the user_properties table.
		$result = $dbr->selectrow( 'user_properties', 'up_value', array (
			'up_user' => '1',
			'up_property' => 'interwikimaptimestamp'
		) );
		$currentTime = wfTimestampNow();
		// If no timestamp is in the table, store one now and create a MediaWiki: page
		// for the backup
		if ( !$result ) {
			$dbw->insert ( 'user_properties', array (
				'up_user' => '1',
				'up_property' => 'interwikimaptimestamp',
				'up_value' => $currentTime
			) );
			// This extension has presumably just been installed, so store the current
			// interwiki table in the backup page
			InterwikiMap::updateBackupPage(
				'Robot: Updating interwiki map backup page' );
		} else {
			// If it hasn't been a long enough time, don't do the update yet. Don't
			// worry about that, though, if the update is being done because the
			// whitelist or blacklisted was revised.
			if ( $rollDice && $currentTime - $result->up_value
				< $wgInterwikiMapSeconds ) {
				return INTERWIKIMAP_SKIPPED;
			}
			if ( $checkMinimumTime && $currentTime - $result->up_value
				< $wgInterwikiMapMinimumSeconds ) {
				return INTERWIKIMAP_THROTTLED;
			}
		}
		// Retrieve these attributes from the API or the whitelist
		$attributes = array (
			1 => 'url',
			2 => 'iw_local', // Not for API retrieval; API value is "local"
			3 => 'iw_trans', // Not for API retrieval; API value is not exposed
			4 => 'api',
			5 => 'wikiid'
		);
		// Default database values
		$defaultAttributes = array (
			1 => '',
			2 => 0,
			3 => 0,
			4 => '',
			5 => ''
		);
		// Poll the APIs
		$apiPulls = array();
		if ( !is_array ( $wgInterwikiMapRemoteWikiUrls ) ) {
			$wgInterwikiMapRemoteWikiUrls = array ( $wgInterwikiMapRemoteWikiUrls );
		}
		foreach ( $wgInterwikiMapRemoteWikiUrls as $url ) {
			$opts = array(
				'http'=>array(
					'method' => "GET",
					'header' => $wgInterwikiMapUserAgent
				)
			);
			$url .= $wgInterwikiMapApiArgs;
			$streamContext = stream_context_create( $opts );
			$contents = file_get_contents ( $url, false, $streamContext );
			if ( !$contents ) {
				wfDebugLog( 'InterwikiMap', "Retrieval from $url failed\n" );
				return array ( 'url' => $url, 'status' =>
				        INTERWIKIMAP_NORETRIEVE ); // Abort update
			}
			$apiPull = json_decode ( $contents, true );
			if ( !$apiPull ) {
				wfDebugLog( 'InterwikiMap', "json decode of $url failed\n" );
				return array ( 'url' => $url, 'status' =>
					INTERWIKIMAP_NODECODE ); // Abort update
			}
			$apiPull = $apiPull['query']['interwikimap'];
			foreach ( $apiPull as $apiPullElement ) {
				$apiResult[$apiPullElement["prefix"]] = array ();
				foreach ( $attributes as $attributeKey => $attributeValue ) {
					if ( isset ( $apiPullElement[$attributeValue] ) ) {
						$apiResult[$apiPullElement["prefix"]][$attributeValue]
							= $apiPullElement[$attributeValue];
					} else {
						// If that field is not in there, use the default
						$apiResult[$apiPullElement["prefix"]][$attributeValue]
							= $defaultAttributes[$attributeKey];
					}
				}
			}
			$apiPulls = array_merge ( $apiPulls, $apiResult );
		}
		// Update the timestamp, because the APIs have been polled
		$dbw->update ( 'user_properties', array (
				'up_value' => $currentTime
			), array (
				'up_user' => 1,
				'up_property' => 'interwikimaptimestamp',
		) );
		// Go through whitelist page
		$whitelist = array();
		$title = Title::newFromText( $wgInterwikiMapWhitelistPage );
		if ( $title->exists() ) {
			$article = Article::newFromTitle( $title, $context );
			$page = $article->getPage();
			if ( version_compare( $wgVersion, '1.21', '<' ) ) {
				$contents = $page->getRevision()->getRawText();
			} else {
				$content = $page->getRevision()->getContent( Revision::RAW );
				$contents = ContentHandler::getContentText( $content );
			}
			if ( $contents ) {
				$contentsArr = explode ( "\n", $contents );
				foreach ( $contentsArr as $line ) {
					// Ignore lines that don't start with | or have only |
					if ( substr ( $line, 0, 1 ) == '|' && trim ( $line ) !=
						'|' ) {
						// Chop off that |
						$line = substr ( $line, 1, strlen( $line ) - 1);
						// Prefix divided from url by ||
						$lineArr = explode ( '||', $line );
						$value = array();
						// Sometimes certain fields aren't specified. If
						// it's a blank URL, leave it unset. Later, we'll
						// see if the URL can be found elsewhere.
						foreach ( $attributes as $key => $attribute ) {
							if ( isset ( $lineArr[$key] ) && ( trim
								( $lineArr[$key] ) != ''
								|| $attribute != 'url' ) ) {
								if ( is_numeric (trim
									    ( $lineArr[$key] ) ) ) {
									$value[$attribute] =
										intval ( trim
											( $lineArr[$key] ) );
								} else {
									// If iw_local or iw_trans is blank
									// or otherwise non-numeric, then stick
									// with the value that is already in
									// the database.
									if ( $attribute == 'iw_local' ||
										$attribute == 'iw_trans' ) {
										unset ( $value[$attribute] );
									} else {
										$value[$attribute] =
											trim ( $lineArr[$key] );
									}
								}
							}
						}
						$whitelist[trim ( $lineArr[0] )] = $value;
					}
				}
			}
		}
		// Go through blacklist page
		$blacklist = array();
		$title = Title::newFromText( $wgInterwikiMapBlacklistPage );
		if ( $title->exists() ) {
			$article = Article::newFromTitle( $title, $context );
			$page = $article->getPage();
			if ( version_compare( $wgVersion, '1.21', '<' ) ) {
				$contents = $page->getRevision()->getRawText();
			} else {
			        $content = $page->getRevision()->getContent( Revision::RAW );
				$contents = ContentHandler::getContentText( $content );
			}
			if ( $contents ) {
				$contentsArr = explode ( "\n", $contents );
				foreach ( $contentsArr as $line ) {
					// Ignore lines that don't start with |
					if ( substr ( $line, 0, 1 ) == '|' ) {
						// Chop off that |
						$line = substr ( $line, 1, strlen( $line ) - 1);
						$key = trim ( $line );
						// Whitelist overrides blacklist
						if ( !isset( $whitelist[$key] ) ) {
							$blacklist[] = $key;
						}
					}
				}
			}
		}
		// Read the interwiki table. What it says is only relevant if there's a whitelist
		// entry without a url (or one of those other attributes)
		$oldInterwiki = array();
		$res = $dbr->select( 'interwiki', array( '*' ) );
		foreach( $res as $row ) {
			$oldInterwiki[$row->iw_prefix] = array (
				'url' => $row->iw_url,
				'iw_local' => $row->iw_local,
				'iw_trans' => $row->iw_trans,
				'api' => $row->iw_api,
				'wikiid' => $row->iw_wikiid,
			);
		}
		// Remove blacklisted items
		$removedItems = array();
		foreach ( $blacklist as $blacklistElement ) {
			if ( isset ( $apiPulls[$blacklistElement ] ) ) {
				$removedItems[] = $apiPulls[$blacklistElement ];
				unset ( $apiPulls[$blacklistElement ] );
			}
		}
		// Add (or add back, in the case of those that were blacklisted) whitelisted items,
		// if either they specify a URL or the URL can be obtained from the old interwiki
		// list
		foreach ( $whitelist as $whitelistKey => $whitelistValue ) {
			// If whitelist specified attribute, use that
			foreach ( $attributes as $key => $attribute ) {
				if ( isset ( $whitelistValue[$attribute] ) ) {
					$apiPulls[$whitelistKey][$attribute] =
						$whitelistValue[$attribute];
					// If whitelist didn't specify attribute, try to get it from the
					// removed items list.
				} elseif ( isset ( $removedItems[$whitelistKey][$attribute] ) ) {
					$apiPulls[$whitelistKey][$attribute] =
						$removedItems[$whitelistKey][$attribute];
					// Try to get attribute from the old interwiki list.
				} elseif ( isset ( $oldInterwiki[$whitelistKey] ) ) {
					$apiPulls[$whitelistKey][$attribute] =
						$oldInterwiki[$whitelistKey][$attribute];
					// Resort to default, except in case of URL
				} elseif ( $attribute != 'url' ) {
					$apiPulls[$whitelistKey][$attribute] = $defaultAttributes[$key];
				}
			}
			// If URL not found in whitelist, removed items list, or old interwiki list, or if
			// it's blank, that whitelisted entry won't be added.
			if ( !isset ( $apiPulls[$whitelistKey]['url'] ) ||
				trim ( $apiPulls[$whitelistKey]['url'] ) == '' ) {
				unset ( $apiPulls[$whitelistKey] );
			}
		}
		// Figure out what needs to be added, deleted, or modified
		$add = array_diff_key ( $apiPulls, $oldInterwiki );
		$delete = array_diff_key ( $oldInterwiki, $apiPulls );
		$modify = array();
		foreach ( $apiPulls as $apiPullsKey => $apiPullsValue ) {
			if ( isset ( $oldInterwiki[$apiPullsKey] ) ) {
				if ( $oldInterwiki[$apiPullsKey] != $apiPullsValue ) {
					$modify[$apiPullsKey] = $apiPullsValue;
				}
			}
		}
		// Don't add interwiki prefixes that conflict with namespaces
		$lowerNamespaces = array();
		foreach ( $wgContLang->getFormattedNamespaces() as $namespace ) {
			$lowerNamespaces[] = strtolower ( $namespace );
		}
		$dbAdd = array();
		$first = true;
		$addSummary = '';
		foreach ( $add as $addKey => $addValue ) {
			if ( !in_array ( strtolower ( $addKey ), $lowerNamespaces ) ) {
				$dbAdd[] = array (
					'iw_prefix' => $addKey,
					'iw_url' => $addValue['url'],
					'iw_local' => $addValue['iw_local'],
					'iw_trans' => $addValue['iw_trans'],
					'iw_api' => $addValue['api'],
					'iw_wikiid' => $addValue['wikiid']
				);
				if ( !$first ) {
					$addSummary .= ', ';
				}
				$first = false;
				$addSummary .= $addKey;
			}
		}
		if ( !$add && !$delete && !$modify ) {
			return INTERWIKIMAP_NOUPDATES;
		}
		if ( $delete ) {
		    $dbDelete = array ( 'iw_prefix' => array_keys ( $delete ) );
		    $dbw->delete ( 'interwiki', $dbDelete );
		}
		$dbw->insert ( 'interwiki', $dbAdd );
		$first = true;
		$modifySummary = '';
		foreach ( $modify as $modifyKey => $modifyValue ) {
			$dbw->update ( 'interwiki', array (
				'iw_url' => $modifyValue['url'],
				'iw_local' => $modifyValue['iw_local'],
				'iw_trans' => $modifyValue['iw_trans'],
				'iw_api' => $modifyValue['api'],
				'iw_wikiid' => $modifyValue['wikiid']
			), array (
				'iw_prefix' => $modifyKey
			) );
			if ( !$first ) {
			    	$modifySummary .= ', ';
			}
			$first = false;
			$modifySummary .= $modifyKey;
		}
		// Create summary
		$summary = '';
		$deleteSummary = '';
		if ( $delete ) {
			$first = true;
			foreach ( $delete as $deleteKey => $deleteValue ) {
				if ( !$first ) {
					$deleteSummary .= ', ';
				}
				$first = false;
				$deleteSummary .= $deleteKey;
			}
		}
		if ( $addSummary ) {
			$summary = wfMessage ( 'interwikimap-add' ) . ': ' . $addSummary;
		}
		if ( $deleteSummary ) {
			if ( $summary ) {
				$summary .= '; ';
			}
			$summary .= wfMessage ( 'interwikimap-delete' ) . ': ' . $deleteSummary;
		}
		if ( $modifySummary ) {
			if ( $summary ) {
				$summary .= '; ';
			}
			$summary .= wfMessage ( 'interwikimap-modify' ) . ': ' .  $modifySummary;
		}
		$summary = str_replace ( '$1', $summary, $wgInterwikiMapBackupSummary );
		InterwikiMap::updateBackupPage ( $summary );
		return INTERWIKIMAP_SUCCESS;
	}

	// Update the wiki page whose history contains old (and current) versions of the
	// interwiki table
	public static function updateBackupPage ( $summary ) {
		wfRunHooks( 'InterwikiMapUpdateBackupPage', array( &$summary ) );
		global $wgInterwikiMapBackupPage, $wgInterwikiMapBackupText,
			$wgInterwikiMapBackupUser, $wgInterwikiMapBackupDescPage;
		if ( !$wgInterwikiMapBackupText ) {
			$wgInterwikiMapBackupText =
				'<!--' . wfMessage ( 'interwikimap-backup-text' )
				. "\n". '-->{{' . $wgInterwikiMapBackupDescPage . '}}'
				. "\n\n==" . wfMessage ( 'interwikimap-current-map' ) . "==\n\n"
				. '{| class="plainlinks"'
				. "\n!" . wfMessage ( 'interwikimap-prefix' )
				. "\n!" . wfMessage ( 'interwikimap-url' )
				. "\n!" . wfMessage ( 'interwikimap-forward' )
				. "\n!" . wfMessage ( 'interwikimap-transclude' )
				. "\n!" . wfMessage ( 'interwikimap-apiurl' )
				. "\n!" . wfMessage ( 'interwikimap-wikiid' ) . "\n|-\n";
		}
		if ( !$wgInterwikiMapBackupPage ) {
			return false;
		}
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'interwiki', array(
			'iw_prefix',
			'iw_url',
			'iw_local',
			'iw_trans',
			'iw_api',
			'iw_wikiid'
		) );
		$text = $wgInterwikiMapBackupText;
		foreach( $res as $row ) {
			$text .= '| '
				. $row->iw_prefix . ' || '
				. $row->iw_url . ' || '
				. $row->iw_local . ' || '
				. $row->iw_trans . ' || '
				. $row->iw_api . ' || '
				. $row->iw_wikiid
				. "\n" . '|-' . "\n";
		}
		$text .= '|}';
		$title = Title::newFromText( $wgInterwikiMapBackupPage );
		$page = WikiPage::factory( $title );
		$flags = EDIT_FORCE_BOT;
		$user = User::newFromName ( $wgInterwikiMapBackupUser );
		$page->doEdit( $text, $summary, $flags, false, $user );
		return true;
	}
}

<?php
/**
 * InterwikiMap MediaWiki extension.
 *
 * This extension retrieves an interwiki map from a remote wiki and updates the local interwiki
 * map accordingly.
 *
 * Written by Leucosticte
 * https://www.mediawiki.org/wiki/User:Leucosticte
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Extensions
 */

if( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( 1 );
}

$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'InterwikiMap',
	'author' => '[https://mediawiki.org/User:Leucosticte Leucosticte]',
	'url' => 'https://mediawiki.org/wiki/Extension:InterwikiMap',
	'descriptionmsg' => 'interwikimap-desc',
	'version' => '1.0.6'
);
$dir = dirname(__FILE__) . '/';
$wgAvailableRights[] = 'interwikimap';
$wgAutoloadClasses['InterwikiMap'] = $dir . 'InterwikiMap.classes.php';
$wgAutoloadClasses['SpecialInterwikiMap'] = $dir . 'SpecialInterwikiMap.php';
$wgExtensionMessagesFiles['InterwikiMap'] = $dir . 'InterwikiMap.i18n.php';
$wgExtensionMessagesFiles['InterwikiMapAlias'] = $dir . 'InterwikiMap.alias.php';
$wgSpecialPages['InterwikiMap'] = 'SpecialInterwikiMap';
$wgSpecialPageGroups['InterwikiMap'] = 'other';
$wgHooks['ArticleSaveComplete'][] = 'InterwikiMap::onArticleSaveComplete';
define( 'INTERWIKIMAP_SUCCESS', 1 );
define( 'INTERWIKIMAP_NOUPDATES', 2 );
define( 'INTERWIKIMAP_THROTTLED', 3 );
define( 'INTERWIKIMAP_NODECODE', 4 );
define( 'INTERWIKIMAP_NORETRIEVE', 5 );
define( 'INTERWIKIMAP_SKIPPED', 6 );

// Update the interwiki map every 86400 seconds (24 hours). Be kind to the remote wiki's servers
// by not putting too low a value (e.g. less than 900 seconds) for this.
$wgInterwikiMapSeconds = 86400;
// Absolute minimum seconds between refreshes triggered by special page; 'interwikimapnoratelimit'
// users are exempt
$wgInterwikiMapMinimumSeconds = 60;
// x in 360 chance, upon an edit being saved, of checking to see whether it's time to update the
// interwiki map; if your wiki gets a lot of edits (e.g. more than one every 24 hours), set this
// to less than 360 to reduce database load. E.g. 36 would be a 10% chance. Change to 0 if you
// want to make it never check; e.g. if you only want to manually trigger updates.
$wgInterwikiMapOdds = 360;
// Prefixes on this list will be added to the interwiki list and will not be removed from it.
// However, the URLs will be overridden by any URLs provided by the remote wiki, unless a URL is
// provided in this table.
$wgInterwikiMapWhitelistPage = 'MediaWiki:Interwiki-whitelist';
// Prefixes on this list will be removed from the interwiki list and will not be added to it.
// However, the whitelist overrides the blacklist where there is any conflict.
$wgInterwikiMapBlacklistPage = 'MediaWiki:Interwiki-blacklist';
// This is the URL, or array or URLs, from which to obtain interwiki maps. If one is inaccessible,
// then the table will not be updated this time around, and a notice will be issued. Otherwise, the
// maps will be merged, with wikis listed later in the array overwriting values on maps of wikis
// listed earlier in the array.
$wgInterwikiMapRemoteWikiUrls = 'https://meta.wikimedia.org/w/api.php';
// Arguments to use when accessing the API
$wgInterwikiMapApiArgs = '?action=query&meta=siteinfo&siprop=interwikimap&format=json';
// The user-agent to use in requests to the wikis from which the interwiki maps are obtained
$wgInterwikiMapUserAgent = "User-Agent: $wgSitename's InterwikiMap. Contact info: URL: "
        . $wgServer . $wgScriptPath . " Email: $wgEmergencyContact";
// MediaWiki: page in which to store copies of the interwiki table; set to false to disable
$wgInterwikiMapBackupPage = 'MediaWiki:InterwikiMapBackup';
// MediaWiki: description page for the backup page
$wgInterwikiMapBackupDescPage = 'MediaWiki:interwikimap-backup-desc';
// Text with which to begin the backup page; if blank, will use a default
$wgInterwikiMapBackupText = '';
// Username of user who edits the backup page
$wgInterwikiMapBackupUser = 'Maintenance script';
// Edit summary for editing the backup page
$wgInterwikiMapBackupSummary = 'Robot: $1';
// Sysops can manually trigger the update
$wgGroupPermissions['sysop']['interwikimap'] = true;
// Bureaucrats can manually trigger the update without being throttled
$wgGroupPermissions['bureaucrat']['interwikimapnoratelimit'] = true;

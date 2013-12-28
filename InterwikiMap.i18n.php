<?php
/**
 * Internationalisation for InterwikiMap
 *
 * @file
 * @ingroup Extensions
 */
$messages = array();

/** English
 * @author Leucosticte
 */
$messages['en'] = array(
	'interwikimap' => 'Trigger interwiki table update',
	'interwikimap-desc' => 'Updates the local interwiki table with the contents of an interwiki map retrieved from another wiki.',
	'interwikimap-trigger-success' => "The interwiki table was successfully updated! See $1 for a list of edits by $2.",
        'interwikimap-trigger-noupdates' => "The remote wikis were polled and the interwiki [[$3|whitelist]] and [[$4|blacklist]] were checked, but there were no updates to be made to the
interwiki table. See $1 for a list of edits by $2.",
        'interwikimap-trigger-throttled' => 'You do not have permission to manually trigger an update of the interwiki table after it has already been triggered within the last $1 seconds.',
	'interwikimap-trigger-noretrieve' => "An error retrieving data from the remote wiki prevented the interwiki table from being updated. The url was:
$1",
        'interwikimap-trigger-nodecode' => "An error decoding data from the remote wiki prevented the interwiki table from being updated. The url was:
$1",
        'interwikimap-notallowed' => 'Your account does not have permission to trigger interwiki table updates.',
        'interwikimap-add' => 'add',
        'interwikimap-delete' => 'delete',
        'interwikimap-modify' => 'modify',
        'interwikimap-backup-text' => 'Do not edit this page. It will be automatically revised by the maintenance script when the interwiki table is updated.',
        'interwikimap-backup-desc' => 'Do not edit this page. It will be automatically revised by the maintenance script when the interwiki table is updated.',
        'interwikimap-current-map' => 'Current interwiki map',
        'interwikimap-prefix' => 'Prefix',
        'interwikimap-url' => 'URL',
        'interwikimap-forward' => 'Forward',
        'interwikimap-transclude' => 'Transclude',
        'interwikimap-apiurl' => 'API URL',
        'interwikimap-wikiid' => 'Wiki ID',
);

/** Message documentation
 * @author Leucosticte
 */
$messages['qqq'] = array(
	'interwikimap-desc' => '{{desc}}',
	'interwikimap-trigger-success' => 'This is the message the user gets after he triggers a successful interwiki table update. $1 is the contributions page for the maintenance script
and $2 is the name of the maintenance script.',
        'interwikimap-trigger-noupdates' => 'This is the message the user sees when there were no updates to be made to the interwiki table. $1 is the contributions page for the
maintenance script, $2 is the name of the maintenance script, $3 is the title of the whitelist page, and $4 is the title of the blacklist page.',
        'interwikimap-trigger-throttled' => "This is the message the user gets when he tries to update the interwiki table too soon after the last triggering.",
	'interwikimap-trigger-noretrieve' => 'This is the message the user gets if an error retrieving data from the remote wiki prevents the interwiki table from being updated. The
parameter is the URL of the remote wiki.',
        'interwikimap-trigger-nodecode' => 'This is the message the user gets if an error decoding data from the remote wiki prevents the interwiki table from being updated. The
parameter is the URL of the remote wiki.',
        'interwikimap-notallowed' => 'This is the message the user gets when he tries to trigger interwiki table updates from an account without the interwiki right.',
        'interwikimap-add' => 'This appears in RecentChanges for entries added to the interwiki table.',
        'interwikimap-delete' => 'This appears in RecentChanges for entries deleted from the interwiki table.',
        'interwikimap-modify' => 'This appears in RecentChanges for entries modified in the interwiki table.',
        'interwikimap-backup-text' => 'Some text that appears as a commented-out warning in MediaWiki:InterwikiMapBackup to users not to edit the backup page directly.',
        'interwikimap-backup-desc' => 'Some text that appears as a non-commented-out warning in MediaWiki:InterwikiMapBackup to users not to edit the backup page directly.',
        'interwikimap-current-map' => 'The heading for the table in MediaWiki:InterwikiMapBackup',
        'interwikimap-prefix' => 'A table heading for interwiki prefix (interwiki.iw_prefix) See interwiki_prefix in interwiki.18n.php',
        'interwikimap-url' => 'A table heading for interwiki url (interwiki.iw_url) See interwiki_url in interwiki.18n.php',
        'interwikimap-forward' => 'A table heading for interwiki forwarding (interwiki.iw_local). See interwiki_local in interwiki.18n.php',
        'interwikimap-transclude' => 'A table heading for interwiki transclusion (interwiki.iw_trans) See interwiki_trans in interwiki.18n.php',
        'interwikimap-apiurl' => 'A table heading for interwiki API URL (interwiki.iw_api)',
        'interwikimap-wikiid' => 'A table heading for interwiki wiki ID (interwiki.iw_wikiid)',
);

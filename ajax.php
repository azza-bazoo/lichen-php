<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
ajax.php - handler script for requests from the client-side JavaScript
--------------------------------------------------------------------------------

This file is part of Lichen. Lichen is free software; you can redistribute it
and/or modify it under the terms of the GNU General Public License as published
by the Free Software Foundation; either version 3 of the License, or (at your
option) any later version.

Lichen is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.

*/

include( "functions.inc.php" );

session_start();

if ( !isset( $_SESSION['pass'] ) || !isset( $_SESSION['user'] ) ) {
	die( remoteRequestFailure( 'AUTH', _("Sorry, you're not logged in!") ));
}

if ( isset( $_POST['mailbox'] ) && !empty( $_POST['mailbox'] ) ) {
	connectToServer( $_SESSION['user'], $_SESSION['pass'], $_POST['mailbox'] );
} else {
	connectToServer( $_SESSION['user'], $_SESSION['pass'] );
}

// Load the user settings.
$USER_SETTINGS = getUserSettings();

if ( version_compare( PHP_VERSION, '5.1.0', '>=' ) ) {
	date_default_timezone_set( $USER_SETTINGS['timezone'] );
}

// ------------------------------------------------------------------------
//
// Fetch message counts for the current mailbox.
//
function request_getMailboxList() {
	$mailboxes = getMailboxList();

	// TODO: Find, generate, and return a meaningful validity value.
	// TODO: This is a hack to generate the validity - it's expensive in server
	// CPU time and memory. What we're doing is creating a string representation
	// of the data stored in $mailboxes, and then SHA1 that to create a text key.
	// Oh, it's also bad because the server ALWAYS generates the list...
	$validity = sha1( serialize( $mailboxes ) );

	if ($mailboxes == NULL) {
		echo remoteRequestFailure( 'IMAP', _('Unable to fetch mailbox list.') );
	} else {
		if ( $_POST['validity'] == $validity ) {
			// The client already has valid data.
			// So return them no data, with the same key - they will know
			// that this is the valid data.
			// The only thing this saves is sending the data to the client
			// more than once.
			$mailboxes = null;
		}
		echo remoteRequestSuccess( array( 'validity' => $validity, 'data' => $mailboxes ) );
	}
}

// ------------------------------------------------------------------------
//
// Get the users settings.
//
function request_getUserSettings() {
	global $USER_SETTINGS;
	echo remoteRequestSuccess( array( 'settings' => $USER_SETTINGS ) );
}

// ------------------------------------------------------------------------
//
// Save the users settings.
// (Not used in current version of settings panel implementation.)
//
// function request_saveUserSettings() {
//
// 	if (!empty($_POST['settings'])) {
// 		// JSON decode and save.
// 		// (Yes, we json_encode it when saving... but this way, funky stuff
// 		// shouldn't get saved...)
// 		// TODO: Probably want to do more sanity checking on all this.
// 		$settings = json_decode_assoc( $_POST['settings'] );
//
// 		if (saveUserSettings( $settings )) {
// 			echo remoteRequestSuccess();
// 		} else {
// 			echo remoteRequestFailure( 'SETTINGS', _('Failed to save settings.') );
// 		}
// 	} else {
// 		echo remoteRequestFailure( 'SETTINGS', _('No settings sent from the client.') );
// 	}
// }

// ------------------------------------------------------------------------
//
// List the contents of a mailbox, using search criteria and page numbers.
//
function request_mailboxContentsList() {
	global $mbox, $IMAP_CONNECT, $mailbox;
	global $USER_SETTINGS;

	$searchQuery = "";
	$displayPage = 0;
	$sortMessages = "";
	$validityKey = "";

	$validSortTypes = array( "date", "date_r",
				"from", "from_r",
				"subject", "subject_r",
				"to", "to_r",
				"cc", "cc_r",
				"size", "size_r" );

	if ( isset( $_POST['search'] ) ) {
		$searchQuery = $_POST['search'];
	}

	if ( isset( $_POST['page'] ) && is_numeric( $_POST['page'] ) ) {
		$displayPage = $_POST['page'];
	}

	if ( isset( $_POST['sort'] ) && array_search( $_POST['sort'], $validSortTypes ) !== false ) {
		$sortMessages = $_POST['sort'];

		if ( $_POST['sort'] != $USER_SETTINGS['list_sortmode'] ) {
			// If the sort order has changed from the last one we saved,
			// modify and save the user's preferences again.
			$USER_SETTINGS['list_sortmode'] = $_POST['sort'];
			saveUserSettings( $USER_SETTINGS );
		}
	}

	if ( isset( $_POST['validity'] ) ) {
		$validityKey = $_POST['validity'];
	}

	// See if the mailbox has changed. If not, no need to send back all that data.
	$mailboxData = imap_status( $mbox, $IMAP_CONNECT . $mailbox, SA_ALL );
	$newValidityKey = "{$mailboxData->messages},{$mailboxData->recent},{$mailboxData->unseen}";

	$listData = null;

	if ( $mailboxData->recent > 0 || $newValidityKey != $validityKey ) {
		// Ok, there are new messages OR the number of messages in the box
		// don't match what the client has. Fetch a list.
		// TODO: This doesn't catch changes like message flag changes.
		$validityKey = $newValidityKey;
		$listData = listMailboxContents( $searchQuery, $sortMessages, $displayPage );
	} else {
		// Grab the meta data only - ie, do everything EXCEPT load the messages
		// for their data. TODO: This is really a hack.
		$listData = listMailboxContents( $searchQuery, $sortMessages, $displayPage, true );
	}

	echo remoteRequestSuccess( array( "validityKey" => $validityKey, "data" => $listData, "cacheonly" => $_POST['cacheonly'] ) );
}

// ------------------------------------------------------------------------
//
// Move a message between folders.
//
function request_moveMessage() {
	global $mbox, $SPECIAL_FOLDERS, $mailbox;
	// Move a message.
	// The mailbox= param is the SOURCE mailbox.

	$destinationBox = "";
	if ( isset( $_POST['destbox'] ) && !empty( $_POST['destbox'] ) ) {
		$destinationBox = $_POST['destbox'];
	}
	$messages = array();
	if ( isset( $_POST['uid'] ) && !empty( $_POST['uid'] ) ) {
		// TODO: Assumes "," doesn't appear in uids.
		$messages = explode( ",", $_POST['uid'] );
	}

	if ( count( $messages ) == 0 ) {
		echo remoteRequestFailure( 'MOVE', _("You haven&#8217;t selected any messages to move.") );
	} elseif ( $destinationBox == "" ) {
		echo remoteRequestFailure( 'MOVE', _("Error: no destination mailbox provided") );
	} else {

		$failedCount = moveMessages( $destinationBox, $messages );

		if ( $failedCount == 0 ) {
			$msg = sprintf( _("Moved %d message(s) to %s"), count( $messages ), $destinationBox );
			echo remoteRequestSuccess( array( 'message' => $msg ) );
		} else {
			$msg = sprintf( _("Unable to move %d messages(s): "), $failedCount );
			echo remoteRequestFailure( 'MOVE', $msg . imap_last_error() );
		}
	}
}

// ------------------------------------------------------------------------
//
// Move a message to the Trash, or (TODO) delete if it's already in Trash
//
function request_deleteMessage() {
	global $mbox, $SPECIAL_FOLDERS, $mailbox;
	// Move a message to Trash.
	// The mailbox= param is the SOURCE mailbox.
	$destinationBox = $SPECIAL_FOLDERS['trash'];
	if ( !imapCheckMailboxExistence( $SPECIAL_FOLDERS['trash'] ) ) {
		die( remoteRequestFailure( 'MOVE', _('Error: cannot create trash mailbox') ) );
	}

	$messages = array();
	if ( isset( $_POST['uid'] ) && !empty( $_POST['uid'] ) ) {
		// TODO: Assumes "," doesn't appear in uids.
		$messages = explode( ",", $_POST['uid'] );
	}

	if ( $mailbox == $SPECIAL_FOLDERS['trash'] ) {
		// TODO: if the source mailbox was the trash folder, then really delete.
	}

	if ( count( $messages ) == 0 ) {
		echo remoteRequestFailure( 'MOVE', _("You haven&#8217;t selected any messages to move.") );
	} elseif ( $destinationBox == "" ) {
		echo remoteRequestFailure( 'MOVE', _("Error: no destination mailbox provided") );
	} else {

		$failedCount = moveMessages( $destinationBox, $messages );

		if ( $failedCount == 0 ) {
			$msg = sprintf( _("Moved %d message(s) to %s"), count( $messages ), $destinationBox );
			echo remoteRequestSuccess( array( 'message' => $msg ) );
		} else {
			$msg = sprintf( _("Unable to move %d messages(s): "), $failedCount );
			echo remoteRequestFailure( 'MOVE', $msg . imap_last_error() );
		}
	}
}

// ------------------------------------------------------------------------
//
// Build the mailbox manager pane.
//

function request_mailboxManager() {

	// TODO: this is the second AJAX callback that does the same thing...
	// Obviously the mailbox manager needs to be able to grab this list
	// some other way, common with the normal mailbox list.
	$mailboxes = getMailboxList();

	echo remoteRequestSuccess( array( 'mailboxes' => $mailboxes ) );
}

// ------------------------------------------------------------------------
//
// Mailbox management.
//

function request_mailboxAction() {
	global $mbox, $IMAP_CONNECT;

	$action = "";
	$mailbox1 = "";
	$mailbox2 = "";

	if ( isset( $_POST['action'] ) && !empty( $_POST['action'] ) ) {
		$action = $_POST['action'];
	}
	if ( isset( $_POST['mailbox1'] ) && !empty( $_POST['mailbox1'] ) ) {
		$mailbox1 = $_POST['mailbox1'];
	}
	if ( isset( $_POST['mailbox2'] ) && !empty( $_POST['mailbox2'] ) ) {
		$mailbox2 = $_POST['mailbox2'];
	}

	$result = false;
	$resultMessage = "";
	$resultMailbox = "";
	switch ( $action ) {
		case 'delete':
			// Delete the mailbox.
			// TODO: Delete sub mailboxes?
			$result = imap_deletemailbox( $mbox, $IMAP_CONNECT . $mailbox1 );
			if ( !$result ) {
				$resultMessage = _("Unable to delete mailbox: ") . imap_last_error();
			}
			break;
		case 'rename':
			// Rename the mailbox.
			$result = imap_renamemailbox( $mbox, $IMAP_CONNECT . $mailbox1, $IMAP_CONNECT . $mailbox2 );
			if ( !$result ) {
				$resultMessage = _("Unable to rename mailbox: ") . imap_last_error();
			}
			break;
		case 'create':
			// New mailbox
			$newname = "";
			$mailboxes = getMailboxList();
			$delimiter = $mailboxes[0]['delimiter'];
			if ( $mailbox1 == "" ) {
				$newname = $mailbox2;
			} else {
				$newname = "{$mailbox1}{$delimiter}{$mailbox2}";
			}
			$result = imap_createmailbox( $mbox, $IMAP_CONNECT . $newname );
			if ( !$result ) {
				$resultMessage = _("Unable to create mailbox: ") . imap_last_error();
			}
			break;
		case 'move':
			// Move mailbox.
			$result = imapMoveMailbox( $mailbox1, $mailbox2 );
			if ( $result == null ) {
				$result = true;
			} else {
				$resultMessage = $result;
				$result = false;
			}
			break;
		default:
			$resultMessage = _("Unknown request.");
			break;
	}

	if ( $result ) {
		echo remoteRequestSuccess( array( "action" => $action, "mailbox1" => $mailbox1, "mailbox2" => $mailbox2, "mailboxes" => getMailboxList() ) );
	} else {
		echo remoteRequestFailure( 'MAILBOX', $resultMessage );
	}
}

// ------------------------------------------------------------------------
//
// Draw a message in full
// (Not used in current message display implementation,
//  but might be resurrected in future.)
// function request_drawMessage() {
// 	global $mbox, $DATE_FORMAT_LONG, $mailbox;
//
// 	include( 'libs/HTMLPurifier.auto.php' );
//
// 	// For the retrieveFullMessage function, we need the number,
// 	// not the UID, of the message we're after.
// 	$msgUid = $_POST['msg'];
// 	$msgNo = imap_msgno( $mbox, $msgUid );
//
// 	// TODO: Sanitise the UID input, and handle if the message doesn't occur!
// 	// Fetch the structure and text parts of this message.
// 	$msgArray = retrieveMessage( $msgNo, false );
//
// 	if ( $msgArray == null ) {
// 		// Unable to get that message.
// 		die( remoteRequestFailure( 'MESSAGE', _("Unable to retrieve that message: non existant message.") ) );
// 	}
//
// 	// Fetch and parse its headers.
// 	$headerObj = imap_headerinfo( $mbox, $msgNo );
//
// 	$from = filterHeader( formatIMAPAddress( $headerObj->from ), false );
//
// 	$subject = _("(no subject)");
// 	if ( isset( $headerObj->subject ) ) {
// 		// TODO: use filterHeader instead
// 		$subject = filterHeader( $headerObj->subject, false );
// 	}
//
// 	$localDate = processDate( $headerObj->date, $DATE_FORMAT_LONG );
//
// 	//-----------------------
// 	// Figure out the UIDs of the next and previous messages.
// 	$thisMessagePosition = array_search( $_POST['msg'], $_SESSION['boxcache'] );
//
// 	$previousUID = null;
// 	$previousData = null;
// 	$nextUID = null;
// 	$nextData = null;
// 	$totalMessages = count( $_SESSION['boxcache'] );
// 	if ( $thisMessagePosition === false ) {
// 		// Not found.
// 	} else {
// 		if ( isset( $_SESSION['boxcache'][ $thisMessagePosition - 1 ] ) ) {
// 			$previousUID = $_SESSION['boxcache'][ $thisMessagePosition - 1 ];
// 			$previousData = fetchMessages( array( $previousUID ) );
// 			$previousData = $previousData[0];
// 		}
// 		if ( isset( $_SESSION['boxcache'][ $thisMessagePosition + 1 ] ) ) {
// 			$nextUID = $_SESSION['boxcache'][ $thisMessagePosition + 1 ];
// 			$nextData = fetchMessages( array( $nextUID ) );
// 			$nextData = $nextData[0];
// 		}
// 	}
// 	//-----------------------
//
// 	// Capture the output.
// 	ob_start();
//
// 	// Show the next/previous message links.
// 	// TODO: When going "return to list" return to correct page.
// 	// This works with the client-side message display code, will require some fun to make
// 	// it work for the HTML version.
// 	echo "<p>";
// 	if ( $previousUID != null ) {
// 		echo "<a href=\"#\" onclick=\"showMsg(listCurrentMailbox, '{$previousUID}'); return false\">Previous Message</a> &nbsp;";
// 	}
// 	echo "Viewing ". ($thisMessagePosition + 1) . " of " . $totalMessages;
// 	if ( $nextUID != null ) {
// 		echo "&nbsp; <a href=\"#\" onclick=\"showMsg(listCurrentMailbox, '{$nextUID}'); return false\">Next Message</a>";
// 	}
// 	echo "</p>";
//
// 	//-----------------------
//
// 	// Show the simplified headers (date, sender, subject)
// 	echo "<p class=\"msg-head\"><span class=\"msg-head-label\">From</span> <span class=\"msg-head-sender\">", $from, "</span> <span class=\"msg-head-label\">at</span> <span class=\"msg-head-date\">", $localDate, "</span><br /><span class=\"msg-head-subject\">", $subject, "</span></p>";
//
// 	if (count($msgArray['text/html']) > 0) {
// 		// Display HTML in preference.
// 		foreach ( $msgArray['text/html'] as $htmlPart ) {
// 			echo "<div class=\"html-message\">";
// 			$msgExtraFlags = array();
// 			echo processMsgMarkup( $htmlPart, 'text/html', $mailbox, $msgUid, $msgExtraFlags );
// 			echo "</div>";
// 		}
// 	} else {
// 		// Display text.
// 		foreach ( $msgArray['text/plain'] as $textPart ) {
// 			echo "<div class=\"plain-message\">";
// 			$msgExtraFlags = array();
// 			echo processMsgMarkup( $textPart, 'text/plain', $mailbox, $msgUid, $msgExtraFlags );
// 			echo "</div>";
// 		}
// 	}
//
// 	// List attachments.
// 	if (count($msgArray['attachments']) > 0) {
// 		echo _("Attachments") . ": <ul class=\"attachments\">";
//
// 		foreach ($msgArray['attachments'] as $attach) {
// 			if ( $attach['filename'] == "" ) continue; // Skip attachments that are inline-only.
// 			$attachurl = "message.php?mailbox=" . urlencode( $mailbox ) .
// 				"&uid=" . urlencode( $_POST['msg'] ) .
// 				"&filename=" . urlencode( $attach['filename'] );
// 			echo "<li>";
// 			echo "<a href=\"$attachurl\" onclick=\"return if_newWin('$attachurl')\">";
// 			echo $attach['filename'], "</a> (", _("type"), $attach['type'], ", ", _("size"), " ~", formatNumberBytes($attach['size']), ")";
//
// 			if ( substr( $attach['type'], 0, 5 ) == "image" ) {
// 				echo "<br />";
// 				echo "<img src=\"$attachurl\" alt=\"{$attach['filename']}\" />";
// 			}
// 			echo "</li>";
// 		}
// 		echo "</ul>";
// 	}
//
// 	echo remoteRequestSuccess( array( 'htmlFragment' => ob_get_clean(), 'uid' => $_POST['msg'] ) );
// }

// ------------------------------------------------------------------------
//
// Prepare JSON data for displaying a message
//
function request_getMessage() {
	global $mbox, $DATE_FORMAT_LONG, $mailbox;

	include( 'libs/HTMLPurifier.auto.php' );

	// For the retrieveFullMessage function, we need the number,
	// not the UID, of the message we're after.
	$msgUid = $_POST['msg'];
	$msgNo = imap_msgno( $mbox, $msgUid );

	// TODO: Sanitise the UID input.
	// Fetch the structure and text parts of this message.
	// The name of the following function that we call is ambiguous: all it does is
	// fetch the content of the message.
	$msgArray = retrieveMessage( $msgNo, false );

	if ( $msgArray == null ) {
		die( remoteRequestFailure( 'MESSAGE', _("Unable to retrieve that message: non existant message.") ) );
	}

	// Array to store all the data about this message.
	$msgData = array();

	// Fetch and parse its headers.
	$headerObj = imap_headerinfo( $mbox, $msgNo );

	if ( isset( $headerObj->from ) ) {
		$msgData['from']    = filterHeader( formatIMAPAddress( $headerObj->from ), false );
	}
	if ( isset( $headerObj->to ) ) {
		$msgData['to']      = filterHeader( formatIMAPAddress( $headerObj->to ), false );
	}
	if ( isset( $headerObj->cc ) ) {
		$msgData['cc']      = filterHeader( formatIMAPAddress( $headerObj->cc ), false );
	}
	if ( isset( $headerObj->bcc ) ) {
		$msgData['bcc']     = filterHeader( formatIMAPAddress( $headerObj->bcc ), false );
	}
	if ( isset( $headerObj->replyto ) ) {
		$msgData['replyto'] = filterHeader( formatIMAPAddress( $headerObj->reply_to ), false );
	}
	if ( isset( $headerObj->sender ) ) {
		$msgData['sender']  = filterHeader( formatIMAPAddress( $headerObj->sender ), false );
	}
	$msgData['mailbox'] = $mailbox;
	$msgData['uid']     = $_POST['msg'];

	$subject = "(no subject)";
	if ( isset( $headerObj->subject ) ) {
		$subject = filterHeader( $headerObj->subject, false );
	}

	$msgData['subject'] = $subject;
	if ( isset( $headerObj->date ) ) {
		$msgData['localdate'] = processDate( $headerObj->date, $DATE_FORMAT_LONG );
	}

	// Arrays to store the HTML and plain text parts.
	$msgData['texthtml']  = array();
	$msgData['textplain'] = array();

	// Process each HTML and text part, ready for the client to use directly.
	// (Don't want the client trying to do all this processing)
	foreach ( $msgArray['text/html'] as $htmlPart ) {
		$msgExtraFlags = array();
		$msgData['texthtml'][] = processMsgMarkup( $htmlPart, 'text/html', $mailbox, $msgUid, $msgExtraFlags );

		// msgExtraFlags will have a key "htmlhasremoteimages" if the html section in question has
		// remote images. Merge this with the msgdata, as the client can use it.
		$msgData = array_merge( $msgData, $msgExtraFlags );
	}
	foreach ( $msgArray['text/plain'] as $textPart ) {
		$msgExtraFlags = array();
		$msgData['textplain'][] = processMsgMarkup( $textPart, 'text/plain', $mailbox, $msgUid, $msgExtraFlags );
	}

	if ( count( $msgData['texthtml'] ) > 0 ) {
		$msgData['texthtmlpresent'] = true;
	} else {
		$msgData['texthtmlpresent'] = false;
	}
	if ( count( $msgData['textplain'] ) > 0 ) {
		$msgData['textplainpresent'] = true;
	} else {
		$msgData['textplainpresent'] = false;
	}

	// Prune off data that the client doesn't need.
	// TODO: don't fetch it in the first place
	switch ( $_POST['mode'] ) {
		case 'html':
			if ( count( $msgData['texthtml'] ) > 0 ) {
				$msgData['textplain'] = array();
			}
			break;
		case 'text':
			if ( count( $msgData['textplain'] ) > 0 ) {
				$msgData['texthtml'] = array();
			}
			break;
		case 'all':
			// Don't prune anything
			break;
		default:
			// If HTML data exists, prune off the text version.
			// Other than that, don't touch it.
			if ( count( $msgData['texthtml'] ) > 0 ) {
				$msgData['textplain'] = array();
			}
			break;
	}

	// Just store the list of attachments.
	$msgData['attachments'] = $msgArray['attachments'];

	echo remoteRequestSuccess( array( 'validity' => null, 'data' => $msgData ) );
}

// ------------------------------------------------------------------------
//
// Create the HTML for the composer.
//
function request_createComposer() {
	$composePanel = generateMessageCompose();
	echo remoteRequestSuccess( array( 'htmlFragment' => $composePanel ) );
}

// ------------------------------------------------------------------------
//
// Upload an attachment.
//
function request_uploadAttachment() {

	if ( isset( $_FILES['comp-attachfile'] ) ) {
		if ( $_FILES['comp-attachfile']['error'] != 0 ) {
			echo remoteRequestFailure( 'UPLOAD', _('PHP Upload error ') . $_FILES['comp-attachfile']['error'] );
		} else {
			$destinationDirectory = getUserDirectory() . "/attachments";
			$serverFilename = genUID( $_FILES['comp-attachfile']['name'] );
			if (move_uploaded_file( $_FILES['comp-attachfile']['tmp_name'], "{$destinationDirectory}/$serverFilename" ) ) {
				echo remoteRequestSuccess( array(
					"filename" => $_FILES['comp-attachfile']['name'],
					"type" => $_FILES['comp-attachfile']['type'],
					"size" => $_FILES['comp-attachfile']['size']
				));
			} else {
				echo remoteRequestFailure( 'UPLOAD', _("Unable to move uploaded file - probably server permissions problem.") );
			}
		}
	} else {
		// Wrong upload.
		echo remoteRequestFailure( 'UPLOAD', _('Failed to specify a file to upload.') );
	}
}

// ------------------------------------------------------------------------
//
// Remove an attachment from a message (for the composer).
//
function request_removeAttachment() {

	// TODO: Still a little unsafe.
	$userDir = getUserDirectory();
	if ( isset( $_POST['filename'] ) && !empty( $_POST['filename'] ) ) {
		$serverFilename = genUID( $_POST['filename'] );
		$serverFilename = "{$userDir}/attachments/{$serverFilename}";

		if ( file_exists( $serverFilename ) ) {
			unlink( $serverFilename );
			echo remoteRequestSuccess();
		} else {
			echo remoteRequestFailure( 'ATTACH', _('Unable to find attachment to remove') );
		}
	} else {
		echo remoteRequestFailure( "ATTACH", _("Invalid attachment to remove.") );
	}
}

// ------------------------------------------------------------------------
//
// Set/Clear/Toggle a IMAP flag on a message.
//
function request_setFlag() {
	global $mbox;

	$flag = "\\Seen";
	$state = "toggle";
	$messageUID = "";

	if ( isset( $_POST['uid'] ) && !empty( $_POST['uid'] ) ) {
		$messageUID = $_POST['uid'];
	}
	if ( isset( $_POST['state'] ) && !empty( $_POST['state'] ) ) {
		$state = $_POST['state'];

		if ( $state != "true" && $state != "false" && $state != "toggle" ) {
			// Invalid state.
			$state = "toggle";
		}
	}
	if ( isset( $_POST['flag'] ) && !empty( $_POST['flag'] ) ) {
		$flag = $_POST['flag'];
	}

	$flagState = imapTwiddleFlag( $messageUID, $flag, $state );

	if ( $flagState['success'] ) {
		echo remoteRequestSuccess( array( "flag" => $flag, "state" => $flagState['flagstate'], "uid" => $messageUID ) );
	} else {
		echo remoteRequestFailure( 'FLAGS', _('Unable to set flag on the message: ') . $flagState['errormessage'] );
	}
}

// ------------------------------------------------------------------------
//
// Send a message, or save a draft.
//
function request_sendMessage() {
	global $mbox, $IMAP_CONNECT, $mailbox;
	global $IMAP_PORT, $IMAP_SERVER, $IS_SSL;
	global $LICHEN_VERSION, $SPECIAL_FOLDERS;
	global $SMTP_SERVER, $SMTP_PORT, $SMTP_USE_SSL, $SMTP_AUTH;

	include ( 'libs/Swift.php' );
	include ( 'libs/Swift/Connection/SMTP.php' );
	include ( 'libs/streamattach.php' );

	$draftMode = FALSE;
	$oldDraftUid = "";
	if ( isset( $_POST['draft'] ) ) {
		$draftMode = TRUE;

		if ( isset( $_POST['comp-draftuid'] ) ) {
			$oldDraftUid = $_POST['comp-draftuid'];
		}
	}

	// Set up Swift to cache on disk - allows attachments larger than
	// the memory limit.
	Swift_CacheFactory::setClassName("Swift_Cache_Disk");
	Swift_Cache_Disk::setSavePath( getUserDirectory() );

	// Create the message.
	$mimeMessage =& new Swift_Message( $_POST['comp-subj'] );
	$mimeMessage->headers->set( 'User-Agent', 'Lichen ' . $LICHEN_VERSION );

	// If this is a reply/forward of another message, get some details about that.
	// Specifically, for generating an "In-Reply-To" header.
	if ( isset( $_POST['comp-mode'] ) && ( $_POST['comp-mode'] == "reply" || substr( $_POST['comp-mode'], 0, 7 ) == "forward" ) ) {
		$oldMailbox = $mailbox;
		changeMailbox( $_POST['comp-quotemailbox'] );
		$replyData = imap_headerinfo( $mbox, imap_msgno( $mbox, $_POST['comp-quoteuid'] ) );
		changeMailbox( $oldMailbox );
		$mimeMessage->headers->set( 'In-Reply-To', $replyData->message_id );
	}

	// Set the "from" address based on the identity.
	$userIdentity = getUserIdentity( $_POST['comp-identity'] );
	if ( $userIdentity == NULL ) {
		// Couldn't find an identity.
		die( remoteRequestFailure( 'COMPOSE', _('Unable to find an identity to send this email for.') ));
	}
	$FROM_EMAIL = new Swift_Address( $userIdentity['address'], $userIdentity['name'] );

	$mimeMessage->setFrom( $FROM_EMAIL );

	//die( print_r( parseRecipientList( $_POST['comp-to'] ) ) );

	// Set the Various recipient addresses.
	$messageRecipients =& new Swift_RecipientList();
	// TO:
	$toRecipients = parseRecipientList( $_POST['comp-to'] );

	if ( count( $toRecipients ) == 0 ) {
		die( remoteRequestFailure( 'SEND', _('No valid to addresses given.') ) );
	}

	foreach ( $toRecipients as $recipient ) {
		$messageRecipients->addTo( $recipient['address'], $recipient['name'] );
	}

	// CC:
	$ccRecipients = parseRecipientList( $_POST['comp-cc'] );

	if ( count( $ccRecipients ) != 0 ) {
		foreach ( $ccRecipients as $recipient ) {
			$messageRecipients->addCc( $recipient['address'], $recipient['name'] );
		}
	}

	// BCC:
	$bccRecipients = parseRecipientList( $_POST['comp-bcc'] );

	if ( count( $bccRecipients ) != 0 ) {
		foreach ( $bccRecipients as $recipient ) {
			$messageRecipients->addBcc( $recipient['address'], $recipient['name'] );
		}
	}

	//die( var_dump( $messageRecipients ) );

	// The Swift docs say that these don't need to be set to send a message,
	// but it does seem to be harmless - if we don't set them, then when we save
	// a draft we have no idea what these addresses are.
	$mimeMessage->setTo( $messageRecipients->getTo() );
	$mimeMessage->setCc( $messageRecipients->getCc() );
	$mimeMessage->setBcc( $messageRecipients->getBcc() );

	// Add attachments.
	if ( isset( $_POST['comp-attach'] ) && count( $_POST['comp-attach'] ) > 0 ) {
		// Set the body, as a seperate part.
		// TODO: Only handles plain text.
		$mimeMessage->attach( new Swift_Message_Part( $_POST['comp-msg'] ) );

		// Filenames to add...
		$uploadDir = getUserDirectory() . "/attachments";
		foreach ( $_POST['comp-attach'] as $attachmentFile ) {
			$serverFilename = genUID( $attachmentFile );
			$mimeType = mime_content_type( "{$uploadDir}/{$serverFilename}" );
			if ( substr( $mimeType, 0, 7 ) == "message" ) {
				// For certain types, don't base64 encode.
				$mimeMessage->attach( new Swift_Message_Attachment(
					new Swift_File( "{$uploadDir}/{$serverFilename}" ),
					$attachmentFile,
					$mimeType, "7bit" ) );
			} else {
				$mimeMessage->attach( new Swift_Message_Attachment(
					new Swift_File( "{$uploadDir}/{$serverFilename}" ),
					$attachmentFile,
					$mimeType ) );
			}
		}
	} else {
		// Set the body.
		// TODO: Only handles plain text.
		$mimeMessage->setBody( $_POST['comp-msg'] );
	}

	// Pregenerate the message ID.
	// (We use this in a hack when saving drafts)
	$messageID = $mimeMessage->generateId();

	// Convert the message into a string to save it.
	$rawMessage = $mimeMessage->build();

	// Yet another hack: figure out the size of the message.
	// This is not really efficient, as it reads everything into memory,
	// in segments.
	$messageLength = 0;
	$maxCounter = 0;
	while ( $maxCounter < 1000000 ) {
		$data = $rawMessage->read( 8192 );

		if ( $data === false ) break;

		$messageLength += strlen($data);
		$maxCounter++;
	}
	//die ( "Length: $mLength, took $maxCounter loops." );

	$rawMessage = $mimeMessage->build();

	if ( $draftMode ) {
		// In draft mode? Just save the message.

		if ( !imapCheckMailboxExistence( $SPECIAL_FOLDERS['drafts'] ) ) {
			die( remoteRequestFailure( 'SENT', _("Unable to create draft folder - mail not sent: ") . imap_last_error() ) );
		}

		// Stream the message to the IMAP server, so that emails of any size can be saved.
		$res = streamSaveMessage( $IMAP_SERVER, $IMAP_PORT, $IS_SSL, $_SESSION['user'], $_SESSION['pass'],
				$SPECIAL_FOLDERS['drafts'], $rawMessage, $messageLength, "\\Draft" );

		// This is a hack: search for the message with that ID. This is how
		// we figure out the draft messages' UID. This is a hack.
		changeMailbox( $SPECIAL_FOLDERS['drafts'] );
		$uids = imap_search( $mbox, "TEXT \"{$messageID}\"", SE_UID );

		$draftUID = "";
		if ( $uids === false || count( $uids ) != 1 ) {
			// Unable to find the UID. Oh well.
		} else {
			// Found it.
			$draftUID = $uids[0];
		}

		if ( $res != null ) {
			echo remoteRequestFailure( 'DRAFT', _('Unable to save draft message: ') . $res );
		} else {
			// Delete the old draft. ONLY if we have a new AND old UID.
			if ( $oldDraftUid != "" && $draftUID != "" ) {
				imap_delete( $mbox, $oldDraftUid, FT_UID );
				imap_expunge( $mbox );
			}
			echo remoteRequestSuccess( array( 'draftMode' => true, 'message' => _('Draft saved'), 'draftUid' => $draftUID ) );
		}
	} else {
		// Time to send the message.
		// Save it into Sent first.
		if ( !imapCheckMailboxExistence( $SPECIAL_FOLDERS['sent'] ) ) {
			die( remoteRequestFailure( 'SENT', _("Unable to create sent folder - mail not sent: ") . imap_last_error() ) );
		}
		$res = streamSaveMessage( $IMAP_SERVER, $IMAP_PORT, $IS_SSL, $_SESSION['user'], $_SESSION['pass'],
			$SPECIAL_FOLDERS['sent'], $rawMessage, $messageLength, "");
		if ( $res != null ) {
			$msg = sprintf( _("Unable to save sent message: %s - message NOT sent."), $res );
			die( remoteRequestFailure( 'SENT', $msg ) );
		}

		// Create the connection to the remote SMTP server.
		// TODO: Add TLS support.
		$smtpConnection = null;
		if ( $SMTP_USE_SSL ) {
			$smtpConnection =& new Swift_Connection_SMTP( $SMTP_SERVER, $SMTP_PORT, SWIFT_SMTP_ENC_SSL );
		} else {
			$smtpConnection =& new Swift_Connection_SMTP( $SMTP_SERVER, $SMTP_PORT, SWIFT_SMTP_ENC_OFF );
		}

		if ( $SMTP_AUTH ) {
			// Use authenticated SMTP with the same credentials as for IMAP.
			$smtpConnection->setUsername( $_SESSION['user'] );
			$smtpConnection->setPassword( $_SESSION['pass'] );
		}

		// Send the message.
		$messageSender =& new Swift( $smtpConnection );
		$messageSender->log->enable();
		$numberRecipients = $messageSender->send( $mimeMessage, $messageRecipients, $FROM_EMAIL );

		if ( $numberRecipients == 0 ) {
			ob_start();
			$messageSender->log->dump();
			$msg = sprintf( _("Unable to send message: %s"), ob_get_clean() );
			echo remoteRequestFailure( 'SMTP', $msg );
		} else {
			$msg = sprintf( _("Sent message to %d recipient(s)"), $numberRecipients );
			echo remoteRequestSuccess( array( 'message' => $msg ) );

			// Delete attachments - TODO: still a little unsafe.
			if ( isset( $_POST['comp-attach'] ) && count( $_POST['comp-attach'] ) > 0 ) {
				// Filenames to add...
				$uploadDir = getUserDirectory() . "/attachments";
				foreach ( $_POST['comp-attach'] as $attachmentFile ) {
					$serverFilename = genUID( $attachmentFile );
					unlink( "{$uploadDir}/{$serverFilename}" );
				}
			}

			// Remove the draft that this message was based on. ?? Actually, not yet.

			// Mark the original message as answered, in the case of Replies.
			if ( isset( $_POST['comp-mode'] ) && ( $_POST['comp-mode'] == "reply" || $_POST['comp-mode'] == "replyall" ) ) {
				if ( isset( $_POST['comp-quoteuid'] ) && !empty( $_POST['comp-quoteuid'] ) &&
					isset( $_POST['comp-quotemailbox'] ) && !empty( $_POST['comp-quotemailbox'] ) ) {

					// TODO: Limited error checking here.
					imapTwiddleFlag( $_POST['comp-quoteuid'], "\\Answered", TRUE, $_POST['comp-quotemailbox'] );
				}
			}
		}
	}
}

// ------------------------------------------------------------------------
//
// Return HTML for settings panel
//
function request_settingsPanel() {
	$settingsPanel = generateOptionsPanel();
	echo remoteRequestSuccess( $settingsPanel );
}

// ------------------------------------------------------------------------
//
// Save input from the settings panel
//
function request_settingsPanelSave() {
	global $USER_SETTINGS;

	$errors = parseUserSettings();

	// Save the settings.
	saveUserSettings( $USER_SETTINGS );

	// Return the current settings.
	if ( count( $errors ) > 0 ) {
		echo remoteRequestSuccess( array( 'errors' => $errors, 'settings' => $USER_SETTINGS ) );
	} else {
		echo remoteRequestSuccess( array( 'settings' => $USER_SETTINGS ) );
	}
}

// ------------------------------------------------------------------------
//
// Handle a request within the identity editor (in the settings panel)
//
function request_identityEditor() {
	global $USER_SETTINGS;

	$action = $_POST['action'];
	if ( isset( $_POST['idname'] ) )  $idname = $_POST['idname'];
	if ( isset( $_POST['idemail'] ) ) $idemail = $_POST['idemail'];
	if ( isset( $_POST['oldid'] ) )   $workingid = $_POST['oldid'];

	switch ( $action ) {
		case "add":
			// Add a new identity.
			// TODO: check for conflicts with existing identity names
			array_push( $USER_SETTINGS['identities'], array(
				"name" => $idname,
				"address" => $idemail,
				"isdefault" => false
			) );
			break;
		case "delete":
			// Delete an identity.
			// TODO: assign new default if need be
			$delIndex = -1;
			for ( $i = 0; $i < count( $USER_SETTINGS['identities'] ); $i++ ) {
				if ( $USER_SETTINGS['identities'][$i]['address'] == $workingid ) {
					$delIndex = $i;
					break;
				}
			}
			if ( $delIndex != -1 ) {
				unset( $USER_SETTINGS['identities'][$delIndex] );
			}
			break;
		case "edit";
			// Edit an identity.
			// Find oldid, and then edit.
			for ( $i = 0; $i < count( $USER_SETTINGS['identities'] ); $i++ ) {
				if ( $USER_SETTINGS['identities'][$i]['address'] == $workingid ) {
					$USER_SETTINGS['identities'][$i]['address'] = $idemail;
					$USER_SETTINGS['identities'][$i]['name'] = $idname;
					break;
				}
			}
			break;
		case "setdefault";
			// Find it and then make it default. Everything else becomes not-default.
			for ( $i = 0; $i < count( $USER_SETTINGS['identities'] ); $i++ ) {
				if ( $USER_SETTINGS['identities'][$i]['address'] == $workingid ) {
					$USER_SETTINGS['identities'][$i]['isdefault'] = true;
				} else {
					$USER_SETTINGS['identities'][$i]['isdefault'] = false;
				}
			}
			break;
	}

	saveUserSettings( $USER_SETTINGS );

	echo remoteRequestSuccess( array( "identities" => $USER_SETTINGS['identities'] ) );
}

// ------------------------------------------------------------------------
//
// AJAX Request Dispatcher
//
// TODO: This is for ajax requests; will need another one to handle
// form-style (non-JavaScript) requests.
if ( isset( $_POST['request'] ) && !empty( $_POST['request'] ) ) {
	$functionName = "request_{$_POST['request']}";

	if ( function_exists( $functionName ) ) {
		$functionName();
	}

	// If the function doesn't exist, fail silently.
}

imap_close($mbox);
exit;

?>

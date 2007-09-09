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
	if ( isHtmlSession() ) {
		// TODO: Do something here. Preferably call our dispatcher, below.
	} else {
		die( remoteRequestFailure( 'AUTH', _("Sorry, you're not logged in!") ));
	}
}

// TODO: Read the default below from the SPECIAL_FOLDERS array.
connectToServer( $_SESSION['user'], $_SESSION['pass'], _GETORPOST( 'mailbox', 'INBOX' ) );

// Load the user settings.
$USER_SETTINGS = getUserSettings();

if ( version_compare( PHP_VERSION, '5.1.0', '>=' ) ) {
	date_default_timezone_set( $USER_SETTINGS['timezone'] );
}

// ------------------------------------------------------------------------
//
// REQUEST HANDLING GUIDE
//
// These requests are meant to be multipurpose, and able to be used for both
// the AJAX and non-AJAX versions of Lichen.
//
// This is how they work.
//
// - Each function name must start with "request_". This "puts them in another
//   namespace", and tries to reduce the ability for random hackers to call
//   random Lichen internal functions.
//
// - The request function must return an associative array. It must have the
//   following keys:
//      success - boolean, true if all ok, false if not ok.
//
//   If success is false, then the array must have two other keys:
//      errorCode - the error code, eg "AUTH".
//      errorString - a string describing the error, eg, "Login expired".
//
//   The request should return other keys as appropriate, which will get either
//   JSON encoded if the request is an AJAX request, or "otherwise passed
//   around" if it is a non-ajax request. (But the request code should not be
//   written to expect one or the other, although some will be AJAX only).
//
// - Requests must not call die(), remoteRequestSuccess(), or remoteRequestFailure().


// ------------------------------------------------------------------------
//
// Fetch message counts for the current mailbox.
//
// AJAX only.
function request_getMailboxList() {
	$mailboxes = getMailboxList();

	$result = array();

	// TODO: Find, generate, and return a meaningful validity value.
	// TODO: This is a hack to generate the validity - it's expensive in server
	// CPU time and memory. What we're doing is creating a string representation
	// of the data stored in $mailboxes, and then SHA1 that to create a text key.
	// Oh, it's also bad because the server ALWAYS generates the list...
	$validity = sha1( serialize( $mailboxes ) );

	if ($mailboxes == NULL) {
		$result['success']     = false;
		$result['errorCode']   = 'IMAP';
		$result['errorString'] = _('Unable to fetch mailbox list.');
	} else {
		if ( isset( $_POST['validity'] ) && $_POST['validity'] == $validity ) {
			// The client already has valid data.
			// So return them no data, with the same key - they will know
			// that this is the valid data.
			// The only thing this saves is sending the data to the client
			// more than once.
			$mailboxes = null;
		}
		$result['success']  = true;
		$result['validity'] = $validity;
		$result['data']     = $mailboxes;
	}

	return $result;
}

// ------------------------------------------------------------------------
//
// Get the users settings.
//
// AJAX Only.
function request_getUserSettings() {
	global $USER_SETTINGS;

	$result = array();
	$result['success'] = true;
	$result['settings'] = $USER_SETTINGS;
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
// AJAX/HTML versions.
function request_mailboxContentsList() {
	global $mailbox;
	global $USER_SETTINGS;

	$searchQuery  = _GETORPOST( 'search' );
	$displayPage  = _GETORPOST( 'page' );
	$sortMessages = _GETORPOST( 'sort' );
	$validityKey  = _GETORPOST( 'validity' );

	$validSortTypes = array( "date", "date_r",
				"from", "from_r",
				"subject", "subject_r",
				"to", "to_r",
				"cc", "cc_r",
				"size", "size_r" );

	if ( empty( $sortMessages ) || array_search( $sortMessages, $validSortTypes ) === false ) {
		$sortMessages = $USER_SETTINGS['list_sortmode'];
	}

	if ( $sortMessages != $USER_SETTINGS['list_sortmode'] ) {
		// If the sort order has changed from the last one we saved,
		// modify and save the user's preferences again.
		$USER_SETTINGS['list_sortmode'] = $sortMessages;
		saveUserSettings( $USER_SETTINGS );
	}

	// See if the mailbox has changed. If not, no need to send back all that data.
	$mailboxData = imapMailboxStatus( $mailbox );
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

	$result = array();
	$result['success']     = true;
	$result['validityKey'] = $validityKey;
	$result['data']        = $listData;
	$result['cacheonly']   = _GETORPOST( 'cacheonly', false );
	$result['mailbox']     = $mailbox;

	return $result;
}

// Testing code.
function request_getThreadedList() {
	global $mbox;

	ob_start();
	// Based on the example at http://au3.php.net/manual/en/function.imap-thread.php
	$threads = imap_thread( $mbox );
	foreach ($threads as $key => $val) {
		$tree = explode('.', $key);
		if ($tree[1] == 'num') {
			if ( $val == 0 ) {
				echo "<ul>\n\t<li>Invalid Message??\n";
			} else {
				$header = imap_headerinfo($mbox, $val);
				echo "<ul>\n\t<li>" . $header->fromaddress . "\n";
			}
		} elseif ($tree[1] == 'branch') {
			echo "\t</li>\n</ul>\n";
		}
	}

	$result = array(
		"success" => true,
		"htmlFragment" => ob_get_clean()
	);
	return $result;
}

// ------------------------------------------------------------------------
//
// Move a message between folders.
//
// AJAX/HTML versions.
function request_moveMessage() {
	global $SPECIAL_FOLDERS, $mailbox;
	// Move a message.
	// The mailbox= param is the SOURCE mailbox.
	
	$result = array();
	$result['success'] = false;

	$destinationBox = _GETORPOST( 'destbox' );
	$messages = array();
	$clientMessages = _GETORPOST( 'uid' );
	if ( !empty( $clientMessages ) ) {
		// TODO: Assumes "," doesn't appear in uids.
		$messages = explode( ",", $clientMessages );
	}

	if ( count( $messages ) == 0 ) {
		$result['success']     = false;
		$result['errorCode']   = 'MOVE';
		$result['errorString'] = _("You haven&#8217;t selected any messages to move.");
	} elseif ( $destinationBox == "" ) {
		$result['success']     = true;
		$result['errorCode']   = 'MOVE';
		$result['errorString'] = _("Error: no destination mailbox provided"); 
	} else {
		$failedCount = moveMessages( $destinationBox, $messages );

		if ( $failedCount == 0 ) {
			$msg = sprintf( _("Moved %d message(s) to %s"), count( $messages ), $destinationBox );
			$result['success'] = true;
			$result['message'] = $msg;
		} else {
			$msg = sprintf( _("Unable to move %d message(s): "), $failedCount );
			$result['success']     = false;
			$result['errorCode']   = 'MOVE';
			$result['errorString'] = $msg . imap_last_error();
		}
	}

	return $result;
}

// ------------------------------------------------------------------------
//
// Move a message to the Trash, or (TODO) delete if it's already in Trash
//
// AJAX/HTML Versions.
function request_deleteMessage() {
	global $SPECIAL_FOLDERS, $mailbox;

	$result = array();
	$result['success'] = false;

	// Move a message to Trash.
	// The mailbox= param is the SOURCE mailbox.
	$destinationBox = $SPECIAL_FOLDERS['trash'];
	if ( !imapCheckMailboxExistence( $SPECIAL_FOLDERS['trash'] ) ) {
		$result['success']     = false;
		$result['errorCode']   = 'MOVE';
		$result['errorString'] = _('Error: cannot create trash mailbox');
	} else {

		$messages = array();
		$clientMessages = _GETORPOST( 'uid' );
		if ( !empty( $clientMessages ) ) { // TODO: What if the UID is zero? This won't work properly.
			// TODO: Assumes "," doesn't appear in uids.
			$messages = explode( ",", $clientMessages );
		}

		if ( $mailbox == $SPECIAL_FOLDERS['trash'] ) {
			// TODO: if the source mailbox was the trash folder, then really delete.
		}

		if ( count( $messages ) == 0 ) {
			$result['success'] = false;
			$result['errorCode'] = 'MOVE';
			$result['errorString'] = _("You haven&#8217;t selected any messages to delete.");
		} else {
	
			$failedCount = moveMessages( $destinationBox, $messages );

			if ( $failedCount == 0 ) {
				$msg = sprintf( _("Moved %d message(s) to %s"), count( $messages ), $destinationBox );
				$result['success'] = true;
				$result['message'] = $msg;
			} else {
				$msg = sprintf( _("Unable to move %d message(s): "), $failedCount );
				$result['success']     = false;
				$result['errorCode']   = 'MOVE';
				$result['errorString'] = $msg . imap_last_error();
			}
		}
	}

	return $result;
}

// ------------------------------------------------------------------------
//
// Build the mailbox manager pane.
//
// AJAX only.
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
// AJAX/HTML versions.
function request_mailboxAction() {
	global $mbox, $IMAP_CONNECT;

	$action   = _GETORPOST( 'action' );
	$mailbox1 = _GETORPOST( 'mailbox1' );
	$mailbox2 = _GETORPOST( 'mailbox2' );

	$result = array();
	$resultMessage = "";
	$resultMailbox = "";
	switch ( $action ) {
		case 'delete':
			// Delete the mailbox.
			// TODO: Delete sub mailboxes?
			$result['success'] = imap_deletemailbox( $mbox, $IMAP_CONNECT . $mailbox1 );
			if ( !$result['success'] ) {
				$result['errorCode']   = 'MAILBOX';
				$result['errorString'] = _("Unable to delete mailbox: ") . imap_last_error();
			}
			break;
		case 'rename':
			// Rename the mailbox.
			$result['success'] = imap_renamemailbox( $mbox, $IMAP_CONNECT . $mailbox1, $IMAP_CONNECT . $mailbox2 );
			if ( !$result['success'] ) {
				$result['errorCode']   = 'MAILBOX';
				$result['errorString'] = _("Unable to rename mailbox: ") . imap_last_error();
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
			$result['success'] = imap_createmailbox( $mbox, $IMAP_CONNECT . $newname );
			if ( !$result['success'] ) {
				$result['errorCode']   = 'MAILBOX';
				$result['errorString'] = _("Unable to create mailbox: ") . imap_last_error();
			}
			break;
		case 'move':
			// Move mailbox.
			$mmResult = imapMoveMailbox( $mailbox1, $mailbox2 );
			if ( $mmResult == null ) {
				$result['success'] = true;
			} else {
				$result['success']     = false;
				$result['errorCode']   = 'MAILBOX';
				$result['errorString'] = $mmResult;
			}
			break;
		default:
			$result['success']     = false;
			$result['errorCode']   = 'MAILBOX';
			$result['errorString'] = _("Unknown request.");
			break;
	}

	if ( $result['success'] ) {
		$result['action']    = $action;
		$result['mailbox1']  = $mailbox1;
		$result['mailbox2']  = $mailbox2;
		$result['mailboxes'] = getMailboxList();
	}

	return $result;
}

// ------------------------------------------------------------------------
//
// Prepare JSON data for displaying a message
//
function request_getMessage() {
	global $mbox, $DATE_FORMAT_LONG, $mailbox;

	include( 'libs/HTMLPurifier.auto.php' );

	$result = array();

	$msgUid = _GETORPOST( 'msg' );

	// TODO: Sanitise the UID input.
	$msgArray = retrieveMessage( $msgUid, false );

	if ( $msgArray == null ) {
		$result['success'] = false;
		$result['errorCode'] = 'MESSAGE';
		$result['errorString'] = _("Unable to retrieve that message: non existant message.");

		return $result; // TODO: Don't return in the middle of a function!
	}

	$markedupContent = array();
	$markedupContent['texthtml'] = array();
	$markedupContent['textplain'] = array();

	// Process each HTML and text part, ready for the client to use directly.
	// (Don't want the client trying to do all this processing)
	foreach ( $msgArray['texthtml'] as $htmlPart ) {
		$msgExtraFlags = array();
		$markedupContent['texthtml'][] = processMsgMarkup( $htmlPart, 'text/html', $mailbox, $msgUid, $msgExtraFlags );

		// msgExtraFlags will have a key "htmlhasremoteimages" if the html section in question has
		// remote images. Merge this with the msgdata, as the client can use it.
		$msgArray = array_merge( $msgArray, $msgExtraFlags );
	}
	foreach ( $msgArray['textplain'] as $textPart ) {
		$msgExtraFlags = array();
		$markedupContent['textplain'][] = processMsgMarkup( $textPart, 'text/plain', $mailbox, $msgUid, $msgExtraFlags );
	}

	$msgArray['texthtml'] = $markedupContent['texthtml'];
	$msgArray['textplain'] = $markedupContent['textplain'];

	// Prune off data that the client doesn't need.
	// TODO: don't fetch it in the first place
	switch ( _GETORPOST( 'mode' ) ) {
		case 'html':
			if ( $msgArray['texthtmlpresent'] ) {
				$msgArray['textplain'] = array();
			}
			break;
		case 'text':
			if ( $msgArray['textplainpresent'] ) {
				$msgArray['texthtml'] = array();
			}
			break;
		case 'all':
			// Don't prune anything
		case 'auto':
			// For the HTML version: prune nothing.
			break;
		default:
			// If HTML data exists, prune off the text version.
			// Other than that, don't touch it.
			if ( $msgArray['texthtmlpresent'] ) {
				$msgArray['textplain'] = array();
			}
			break;
	}

	$result['success']  = true;
	$result['validity'] = null;
	$result['data']     = $msgArray;
	$result['mode']     = _GETORPOST( 'mode' );

	return $result;
}

// ------------------------------------------------------------------------
//
// Get data for the composer, so the client can build a composer.
//
function request_getComposeData() {
	$mode   = _GETORPOST( 'mode' );
	$uid    = _GETORPOST( 'uid' );
	$mailto = _GETORPOST( 'mailto' );

	$composeData = generateComposerData( $mode, $uid, $mailto );

	$result = array(
		"success" => true,
		"composedata" => $composeData
	);

	return $result;
}

// ------------------------------------------------------------------------
//
// Upload an attachment.
//
function request_uploadAttachment() {

	$result = array();

	if ( isset( $_FILES['comp_attachfile'] ) ) {
		if ( $_FILES['comp_attachfile']['error'] != 0 ) {
			$errorMessage = _("Unknown upload error.");
			switch ( $_FILES['comp_attachfile']['error'] ) {
				case UPLOAD_ERR_INI_SIZE:
					$errorMessage = _("Uploaded file exceeds what is allowed by the server.");
					break;
				case UPLOAD_ERR_FORM_SIZE:
					$errorMessage = _("Uploaded file exceeds what is allowed by Lichen.");
					break;
				case UPLOAD_ERR_PARTIAL:
					$errorMessage = _("The file was only partially uploaded.");
					break;
				case UPLOAD_ERR_NO_FILE:
					$errorMessage = _("No file was uploaded.");
					break;
				case UPLOAD_ERR_NO_TMP_DIR:
					$errorMessage = _("Server misconfiguration: no temporary directory to upload file to.");
					break;
				case UPLOAD_ERR_CANT_WRITE:
					$errorMessage = _("Server error: unable to save the uploaded file on the server.");
					break;
				// TODO: Test below on PHP4!
				case UPLOAD_ERR_EXTENSION:
					$errorMessage = _("File upload disabled by file extension.");
					break;
			}
			$result['success']      = false;
			$result['errorCode']    = 'UPLOAD';
			$result['errorMessage'] = $errorMessage;
		} else {
			$destinationDirectory = getUserDirectory() . "/attachments";
			$serverFilename = hashifyFilename( $_FILES['comp_attachfile']['name'] );
			if (move_uploaded_file( $_FILES['comp_attachfile']['tmp_name'], "{$destinationDirectory}/$serverFilename" ) ) {
				$result['success']  = true;
				$result['filename'] = $_FILES['comp_attachfile']['name'];
				$result['type']     = $_FILES['comp_attachfile']['type'];
				$result['size']     = $_FILES['comp_attachfile']['size'];
			} else {
				$result['success']     = false;
				$result['errorCode']   = 'UPLOAD';
				$result['errorString'] = _("Unable to move uploaded file - probably server permissions problem.");
			}
		}
	} else {
		// Wrong upload.
		$result['success']     = false;
		$result['errorCode']   = 'UPLOAD';
		$result['errorString'] = _('Failed to specify a file to upload.');
	}

	return $result;
}

// Helper function to rengenerate attachment data.
// This function should not be here, but doesn't seem to fit anywhere else.
// Input is an array of attachments that should be uploaded into the users attachment
// directory.
function regenerateAttachmentData( $fileList ) {
	$result = array();

	$attachDir = getUserDirectory() . "/attachments/";

	foreach ( $fileList as $file ) {
		var_dump( $file );
		if ( is_string( $file ) ) {
			$serverFilename = $attachDir . hashifyFilename( $file );
			if ( file_exists( $serverFilename ) ) {
				$result[] = array(
					"filename" => $file,
					"type" => mime_content_type( $serverFilename ),
					"size" => filesize( $serverFilename ),
					"isforwardedmessage" => false
				);
			}
		} else {
			// Just append the array, it's already ready.
			$result[] = $file;
		}
	}

	return $result;
}

// ------------------------------------------------------------------------
//
// Remove an attachment from a message (for the composer).
//
// AJAX/HTML versions.
function request_removeAttachment() {

	$result = array();

	// TODO: Still a little unsafe.
	$userDir = getUserDirectory();
	if ( isset( $_POST['filename'] ) && !empty( $_POST['filename'] ) ) {
		$serverFilename = hashifyFilename( $_POST['filename'] );
		$serverFilename = "{$userDir}/attachments/{$serverFilename}";

		if ( file_exists( $serverFilename ) ) {
			unlink( $serverFilename );
			$result['success'] = true;
		} else {
			$result['success']     = false;
			$result['errorCode']   = 'ATTACH';
			$result['errorString'] = _('Unable to find attachment to remove');
		}
	} else {
		$result['success']     = false;
		$result['errorCode']   = 'ATTACH';
		$result['errorString'] = _("Invalid attachment to remove.");
	}

	return $result;
}

// ------------------------------------------------------------------------
//
// Set/Clear/Toggle a IMAP flag on a message.
//
function request_setFlag() {
	global $mbox;

	$result = array();

	$flag       = _GETORPOST( 'flag', '\\Seen' );
	$state      = _GETORPOST( 'state' );
	$messageUID = _GETORPOST( 'uid' );

	if ( empty( $state ) || ( $state != "true" && $state != "false" && $state != "toggle" ) ) {
		// Invalid state.
		$state = "toggle";
	}

	$flagState = imapTwiddleFlag( $messageUID, $flag, $state );

	if ( $flagState['success'] ) {
		$result['success'] = true;
		$result['flag']    = $flag;
		$result['state']   = $flagState['flagstate'];
		$result['uid']     = $messageUID;
	} else {
		$result['success']     = false;
		$result['errorCode']   = 'FLAGS';
		$result['errorString'] = _('Unable to set flag on the message: ') . $flagState['errormessage'];
	}

	return $result;
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
	include_once ( 'libs/streamattach.php' );

	$result = array();
	$result['success'] = false;

	$draftMode = FALSE;
	$oldDraftUid = "";
	if ( isset( $_POST['draft'] ) ) {
		$draftMode = TRUE;

		if ( isset( $_POST['comp_draftuid'] ) ) {
			$oldDraftUid = $_POST['comp_draftuid'];
		}
	}

	// Set up Swift to cache on disk - allows attachments larger than
	// the memory limit.
	Swift_CacheFactory::setClassName("Swift_Cache_Disk");
	Swift_Cache_Disk::setSavePath( getUserDirectory() );

	// Create the message.
	$mimeMessage =& new Swift_Message( $_POST['comp_subj'] );
	$mimeMessage->headers->set( 'User-Agent', 'Lichen ' . $LICHEN_VERSION );

	// If this is a reply/forward of another message, get some details about that.
	// Specifically, for generating an "In-Reply-To" header.
	if ( isset( $_POST['comp_mode'] ) && ( $_POST['comp_mode'] == "reply" || substr( $_POST['comp_mode'], 0, 7 ) == "forward" ) ) {
		$oldMailbox = $mailbox;
		changeMailbox( $_POST['comp_quotemailbox'] );
		$replyData = imap_headerinfo( $mbox, imap_msgno( $mbox, $_POST['comp_quoteuid'] ) );
		changeMailbox( $oldMailbox );
		$mimeMessage->headers->set( 'In-Reply-To', $replyData->message_id );
	}

	// Set the "from" address based on the identity.
	$userIdentity = getUserIdentity( $_POST['comp_identity'] );
	if ( $userIdentity == NULL ) {
		// Couldn't find an identity.
		$result['success'] = false;
		$result['errorCode'] = 'COMPOSE';
		$result['errorString'] = _('Unable to find an identity to send this email for.');

		return $result; // TODO: Returning in the middle of a function... don't do it!
	}
	$FROM_EMAIL = new Swift_Address( $userIdentity['address'], $userIdentity['name'] );

	$mimeMessage->setFrom( $FROM_EMAIL );

	//die( print_r( parseRecipientList( $_POST['comp_to'] ) ) );

	// Set the Various recipient addresses.
	/* Long diatribe about why we do the following twice, to end up with $messageRecipients and $mimeMessageRecipients.
	   PHP5: Creating a single $messageRecipients and using it for creating the appropriate headers in
	   the message (with $mimeMessage->setTo( $messageRecipients->getTo() ) works fine, and then we use
	   the same $messageRecipients object for the Swift::send() call, to let it know where to send the email.
	   PHP4: This doesn't work, Swift::send() dies with a fatal error. Cause: when we call
	   $mimeMessage->setTo( $messageRecipients->getTo() ) what happens is that $messageRecipients->getTo() returns
	   a reference to the internal array of Swift_Address objects that $messageRecipients stores. $mimeMessage->setTo()
	   then proceeds to iterate over the array, replacing each key in the array with the results of calling the build()
	   function of each element of the array (which returns a string representation of the email address stored by the
	   Swift_Address object). Because getTo() returns a reference, setTo()'s actions do this in place, changing 
	   $messageRecipients internal state. And then when it comes time to call Swift:send(), it's expecting
	   $messageRecipients to have an array of Swift_Address objects, not strings. I hope that's confusing enough.
	*/
	$messageRecipients =& new Swift_RecipientList();
	$mimeMessageRecipients =& new Swift_RecipientList();
	// TO:
	$toRecipients = parseRecipientList( $_POST['comp_to'] );

	if ( count( $toRecipients ) == 0 ) {
		$result['success']     = false;
		$result['errorCode']   = 'SEND';
		$result['errorString'] = _('No valid to addresses given.');

		return $result; // TODO: Returning in the middle of a function... don't do it!
	}

	foreach ( $toRecipients as $recipient ) {
		$messageRecipients->addTo( $recipient['address'], $recipient['name'] );
		$mimeMessageRecipients->addTo( $recipient['address'], $recipient['name'] );
	}

	// CC:
	$ccRecipients = parseRecipientList( $_POST['comp_cc'] );

	if ( count( $ccRecipients ) != 0 ) {
		foreach ( $ccRecipients as $recipient ) {
			$messageRecipients->addCc( $recipient['address'], $recipient['name'] );
			$mimeMessageRecipients->addCc( $recipient['address'], $recipient['name'] );
		}
	}

	// BCC:
	$bccRecipients = parseRecipientList( $_POST['comp_bcc'] );

	if ( count( $bccRecipients ) != 0 ) {
		foreach ( $bccRecipients as $recipient ) {
			$messageRecipients->addBcc( $recipient['address'], $recipient['name'] );
			$mimeMessageRecipients->addBcc( $recipient['address'], $recipient['name'] );
		}
	}

	//die( var_dump( $messageRecipients ) );

	// The Swift docs say that these don't need to be set to send a message,
	// but it does seem to be harmless - if we don't set them, then when we save
	// a draft we have no idea what these addresses are.
	$mimeMessage->setTo( $mimeMessageRecipients->getTo() );
	$mimeMessage->setCc( $mimeMessageRecipients->getCc() );
	$mimeMessage->setBcc( $mimeMessageRecipients->getBcc() );

	// Do the body of the email.
	// TODO: The body coming back should be UTF-8... so set this as the encoding...
	if ( $_POST['format'] == "text/plain" ) {
		// Just a plain text email.
		$mimeMessage->attach( new Swift_Message_Part( $_POST['comp_msg'], "text/plain" ) );
	} else {
		// It's a HTML email. This is fun!
		// First part of the message is the HTML version of the email.
		// The HTML we get from the client is just the body of the HTML, add our headers to it!
		$source = $_POST['comp_msg'];

		$htmlVersion  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$htmlVersion .= "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n";
		$htmlVersion .= "<html><head>\n";
		$htmlVersion .= "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n";
		$htmlVersion .= "<meta http-equiv=\"Generator\" content=\"Lichen Webmail / TinyMCE\" />\n";
		$htmlVersion .= "<title>{$_POST['comp_subj']}</title>\n";
		$htmlVersion .= "</head>\n";
		$htmlVersion .= "<body>\n";
		$htmlVersion .= $source;
		$htmlVersion .= "\n</body>\n";
		$htmlVersion .= "</html>\n";

		// Now generate a text version. TODO: This is crude.
		$textVersion = strip_tags( $source );
		
		$mimeMessage->attach( new Swift_Message_Part( $htmlVersion, "text/html" ) );
		$mimeMessage->attach( new Swift_Message_Part( $textVersion, "text/plain" ) );
	}

	// Add attachments.
	if ( isset( $_POST['comp_attach'] ) && count( $_POST['comp_attach'] ) > 0 ) {
		// Filenames to add...
		$uploadDir = getUserDirectory() . "/attachments";
		foreach ( $_POST['comp_attach'] as $attachmentFile ) {
			$serverFilename = hashifyFilename( $attachmentFile );
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
			$result['success'] = false;
			$result['errorCode'] = 'SENT';
			$result['errorString'] = _("Unable to create draft folder - mail not sent: ") . imap_last_error();
			
			return $result; // TODO: Returning in the middle of a function... don't do it!
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
			$result['success']     = false;
			$result['errorCode']   = 'DRAFT';
			$result['errorString'] = _('Unable to save draft message: ') . $res;
		} else {
			// Delete the old draft. ONLY if we have a new AND old UID.
			if ( $oldDraftUid != "" && $draftUID != "" ) {
				imap_delete( $mbox, $oldDraftUid, FT_UID );
				imap_expunge( $mbox );
			}
			$result['success']   = true;
			$result['draftMode'] = true;
			$result['message']   = _('Draft saved');
			$result['draftUid']  = $draftUID;
		}
	} else {
		// Time to send the message.
		// Save it into Sent first.
		if ( !imapCheckMailboxExistence( $SPECIAL_FOLDERS['sent'] ) ) {
			$result['success']     = false;
			$result['errorCode']   = 'SENT';
			$result['errorString'] = _("Unable to create sent folder - mail not sent: ") . imap_last_error();
			
			return $result; // TODO: Returning in the middle of a function... don't do it!
		}
		$res = streamSaveMessage( $IMAP_SERVER, $IMAP_PORT, $IS_SSL, $_SESSION['user'], $_SESSION['pass'],
			$SPECIAL_FOLDERS['sent'], $rawMessage, $messageLength, "");
		if ( $res != null ) {
			$msg = sprintf( _("Unable to save sent message: %s - message NOT sent."), $res );
			
			$result['success']     = false;
			$result['errorCode']   = 'SENT';
			$result['errorString'] = $msg;
			
			return $result; // TODO: Returning in the middle of a function... don't do it!
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

			$result['success']     = false;
			$result['errorCode']   = 'SMTP';
			$result['errorString'] = $msg;
		} else {
			$msg = sprintf( _("Sent message to %d recipient(s)"), $numberRecipients );

			$result['success'] = true;
			$result['message'] = $msg;

			// Delete attachments - TODO: still a little unsafe.
			if ( isset( $_POST['comp_attach'] ) && count( $_POST['comp_attach'] ) > 0 ) {
				// Filenames to add...
				$uploadDir = getUserDirectory() . "/attachments";
				foreach ( $_POST['comp_attach'] as $attachmentFile ) {
					$serverFilename = hashifyFilename( $attachmentFile );
					unlink( "{$uploadDir}/{$serverFilename}" );
				}
			}

			// Remove the draft that this message was based on. ?? Actually, not yet.

			// Mark the original message as answered, in the case of Replies.
			if ( isset( $_POST['comp_mode'] ) && ( $_POST['comp_mode'] == "reply" || $_POST['comp_mode'] == "replyall" ) ) {
				if ( isset( $_POST['comp_quoteuid'] ) && !empty( $_POST['comp_quoteuid'] ) &&
					isset( $_POST['comp_quotemailbox'] ) && !empty( $_POST['comp_quotemailbox'] ) ) {

					// TODO: Limited error checking here.
					imapTwiddleFlag( $_POST['comp_quoteuid'], "\\Answered", TRUE, $_POST['comp_quotemailbox'] );
				}
			}
		}
	}

	return $result;
}

// ------------------------------------------------------------------------
//
// Return HTML for settings panel
//
// AJAX/HTML versions.
function request_settingsPanel() {
	$settingsPanel = generateOptionsPanel();

	$result = array(
		"success" => true
	);

	$result = array_merge( $result, $settingsPanel );
	return $result;
}

// ------------------------------------------------------------------------
//
// Save input from the settings panel
//
// AJAX/HTML versions.
function request_settingsPanelSave() {
	global $USER_SETTINGS;

	$result = array();

	$errors = parseUserSettings();

	// Save the settings.
	saveUserSettings( $USER_SETTINGS );

	// Return the current settings.
	$result['success'] = true;
	$result['settings'] = $USER_SETTINGS;
	if ( count( $errors ) > 0 ) {
		$result['errors']   = $errors;
	}

	return $result;
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
			// TODO: Check for blank/invalid data!
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

	$result = array();
	$result['success']    = true;
	$result['identities'] = $USER_SETTINGS['identities'];

	return $result;
}

if ( !isHtmlSession() ) {
	// ------------------------------------------------------------------------
	//
	// AJAX Request Dispatcher
	//
	if ( isset( $_POST['request'] ) && !empty( $_POST['request'] ) ) {
		$functionName = "request_{$_POST['request']}";

		$result = array(
			"success" => false,
			"errorCode" => "BADREQ",
			"errorString" => "Invalid Request."
		);

		if ( function_exists( $functionName ) ) {
			$result = $functionName();
		} else {
			// If the function doesn't exist, fail silently. (ish)
		}

		if ( $result['success'] ) {
			// The call succeeded.
			unset( $result['errorCode'] );
			unset( $result['errorString'] );
			unset( $result['success'] );
			echo remoteRequestSuccess( $result );
		} else {
			echo remoteRequestFailure( $result['errorCode'], $result['errorString'] );
		}
	}
} else {
	// ------------------------------------------------------------------------
	//
	// Non-AJAX Request Dispatcher
	//
	
	include( "include/htmlrender.php" );

	$sequence = _GETORPOST( 'sequence', 'list' ); // Sequence of actions to perform.

	$mailboxList = getMailboxList();

	function request_wrapper( $requestName ) {
		global $mailboxList; // This is a hack... saves running getMailboxList() multiple times.

		$result = array(
			"success" => false,
			"errorCode" => "BADREQ",
			"errorString" => "Invalid Request."
		);
		$functionName = "request_{$requestName}";
		if ( function_exists( $functionName ) ) {
			$result = $functionName();
		}

		$result['mailboxList'] = &$mailboxList;

		return $result;
	}
	function request_failed( $requestResult ) {
		$result = true;

		if ( $requestResult['success'] ) {
			$result = false;
		} else {
			// TODO: Display the error.	
		}

		return $result;
	}

	// Prepare core variables.
	$requestParams = array();
	$requestParams['mailbox'] = _GETORPOST( 'mailbox', "INBOX" );
	$requestParams['search']  = _GETORPOST( 'search' );
	$requestParams['sort']    = _GETORPOST( 'sort' );
	$requestParams['page']    = _GETORPOST( 'page', 0 );

	// Step 1: basic page layout.
	printPageHeader();
	drawToolbar( 'corner-bar', true );

	// Step 2: Mailbox list (always visible)
	echo "<ul id=\"mailboxes\">\n";
	echo render_mailboxList( array( "mailboxList" => $mailboxList ), $requestParams );
	echo "</ul>\n";

	// Step 3: Run through the sequence of actions we need to do.
	// This will involve running some request, and then running
	// some sort of HTML renderer.
	switch ( $sequence ) {
		default:
		case "list":
			// ---------------------
			// Display message list.
			//
			// Prep any extra request params.
			$requestParams['sequence'] = 'list';
			$requestParams['selector'] = _GETORPOST( 'selector', 'none' );

			// Perform any actions if we need to.
			$subaction = _GETORPOST( 'listaction' );
			if ( !empty( $subaction ) ) {
				// Build a list of UIDs that we will work on.
				$uids = array();
				foreach ( $_POST as $postvar => $uid ) {
					if ( substr( $postvar, 0, 2 ) == 's-' ) {
						$uids[] = $uid;
					}
				}
				foreach ( $_GET as $postvar => $uid ) {
					if ( substr( $postvar, 0, 2 ) == 's-' ) {
						$uids[] = $uid;
					}
				}
				$_POST['uid'] = implode( ",", $uids );

				// Determine what action to take and make it happen!
				switch ( $subaction ) {
					case 'move':
						// Move messages to a given mailbox.
						$_POST['destbox'] = substr( _GETORPOST( 'movemessage' ), 5 );

						$saResult = request_moveMessage();
						break;
					case 'delete':
						$saResult = request_deleteMessage();
						break;
					case 'flag':
						$_POST['flag'] = 'flagged';
						$_POST['state'] = "true";

						$saResult = request_setFlag();
						break;
					case 'flagtoggle':
						$_POST['flag'] = 'flagged';
						$_POST['state'] = "toggle";

						$saResult = request_setFlag();
						break;
					case 'mark read':
						$_POST['flag'] = "seen";
						$_POST['state'] = "true";

						$saResult = request_setFlag();
						break;
				}

				// TODO: Error checking.
			}
			
			// Show the toolbar.
			drawToolbar( 'list-bar', true, $requestParams );

			// Get the list of messages.
			$result = request_wrapper( 'mailboxContentsList' );

			if ( !request_failed( $result ) ) {
				// Hack... the data we need is in the key 'data' in request,
				// but we want it up one level.
				$result['sort'] = $requestParams['sort'];
				$result = array_merge( $result, $result['data'] );

				// List mode - list toolbar and message listing.
				echo "<div id=\"list-wrapper\">";
				echo render_messageList( $result, $requestParams );
				echo "</div>";
			}
			
			break;
		case "comp":
			// ---------------------
			// Display the composer
			//
			$requestParams['sequence'] = 'comp';

			// Show the toolbar.
			drawToolbar( 'comp-bar', true, $requestParams );

			$displayComposer = true;
			$mergeBack = false;
			$result = array();
			
			// Strip attachments that are no longer present.
			$outputAttachments = array();
			if ( isset( $_POST['comp_attach'] ) ) {
				foreach ( $_POST['comp_attach'] as $key => $attach ) {
					if ( isset( $_POST['comp_keepattach'][$key] ) ) {
						// Keep this attachment.
						$outputAttachments[] = $attach;
					}
				}
				$_POST['comp_attach'] = $outputAttachments;
			}

			$compAction = "new";
			if ( isset( $_POST['compaction'] ) ) {
				$compAction = $_POST['compaction'];
			}

			switch ( $compAction ) {
				// Force the mode of the forward. This is a hack...
				case "forward inline":
					$_POST['mode'] = "forwardinline";
				case "forward message as attachment":
					if ( !isset( $_POST['mode'] ) || $_POST['mode'] != "forwardinline" ) {
						$_POST['mode'] = "forwardasattach";
					}
					$_POST['uid'] = $_POST['comp_quoteuid'];
					// Fall through and act like this is a normal new composer.
				default:
				case "new":
					// Generate data for the composer.
					$result = request_wrapper( 'getComposeData' );
					$result = array_merge( $result, $result['composedata'] );
					break;
				case "Send Message":
					$result = request_wrapper( 'sendMessage' );

					if ( !request_failed( $result ) ) {
						// Success! Return to the inbox...
						$displayComposer = false;
					} else {
						// Restore the data that the user posted.
						$mergeBack = true;
					}
					break;
				case "Save Draft":
					// Force request_sendMessage to save a draft instead.
					$_POST['draft'] = true;

					$result = request_wrapper( 'sendMessage' );
					
					// Restore the data that the user posted.
					$mergeBack = true;

					if ( !request_failed( $result ) ) {
						// Save the draft UID - we need it later.
						$result['comp_draftuid'] = $result['draftUid'];
					}
					break;
				case "upload file":
					// Uploaded a new file...

					$result = request_wrapper( 'uploadAttachment' );

					if ( !request_failed( $result ) ) {
						$result['comp_attach'] = array( array(
							"filename" => $result['filename'],
							"type" => $result['type'],
							"size" => $result['size'],
							"isforwardedmessage" => false
						) );
					} else {
						// TODO: handle upload errors.
						print_r($result);
					}

					$mergeBack = true;
					break;
			}

			if ( $displayComposer ) {
				if ( $mergeBack ) {
					// Assemble some data that we'll need.
					$result = array_merge( $_POST, $result );
					$result['identities'] = $USER_SETTINGS['identities'];
					$result['action']     = $result['comp_mode'];
					// Wee! Attachments are fun.
					// What we're trying to accomplish here:
					// If the request gave us new attachments, merge them with the list
					// of attachments posted back. The rengenerateAttachmentData, below,
					// will fix up the ones that don't have all the data they need.
					if ( !isset( $result['comp_attach'] ) ) {
						$result['comp_attach'] = array();
					}
					if ( isset( $_POST['comp_attach'] ) ) {
						// PHP doesn't seem to have a way to glue two arrays together simply.
						// array_merge doesn't do what I want, and the + operator doesn't either
						// (read up on that one, it can be useful, but not here!)
						foreach ( $_POST['comp_attach'] as $att ) {
							$result['comp_attach'][] = $att;
						}
					}
				}

				// Regenerate data on the attachments...
				$result['comp_attach'] = regenerateAttachmentData( $result['comp_attach'] );

				$result['maxattachmentsize'] = $UPLOAD_ATTACHMENT_MAX;

				// TODO: We have to force display with inline style below,
				// because it is display: none by default in layout.css.
				echo "<div id=\"comp-wrapper\" style=\"display: block;\">";
				echo render_composer( $result, $requestParams );
				echo "</div>";
			} else {
				// TODO: Not so hackish!
				echo "<div id=\"comp-wrapper\" style=\"display: block;\">";
				echo $result['message'], " <a href=\"ajax.php\">Return to Inbox</a>.";
				echo "</div>";
			}
			break;
		case "disp":
			// ---------------------
			// Display a message
			//
			// Fetch the message data.
			$result = request_getMessage();

			// Organise a few other parameters.
			$result['sort'] = $requestParams['sort'];
			$requestParams['mode'] = _GETORPOST( 'mode', 'auto' );
			$requestParams['sequence'] = 'disp';
			$requestParams['msg'] = $result['data']['uid'];
			
			// Draw the toolbar.
			// This toolbar is depenant on the request parameters.
			drawToolbar( 'msg-bar', true, $requestParams );

			if ( !request_failed( $result ) ) {
				// Load data on the next/previous messages.
				// TODO: Make it increment/decrement the page counter when it goes
				// over a page? That will be tricky.
				$currentIndex = array_search( $result['data']['uid'], $_SESSION['boxcache'] );
				$previousMessage = null;
				$nextMessage = null;
				if ( $currentIndex !== false ) {
					if ( $currentIndex != 0 ) {
						$previousMessage = $currentIndex - 1;
						$previousMessage = fetchMessages( array( $_SESSION['boxcache'][$previousMessage] ) );
						$previousMessage = $previousMessage[0];
					}
					if ( $currentIndex < count( $_SESSION['boxcache'] ) - 1 ) {
						$nextMessage = $currentIndex + 1;
						$nextMessage = fetchMessages( array( $_SESSION['boxcache'][$nextMessage] ) );
						$nextMessage = $nextMessage[0];
					}
				}
				$result['previousmessage'] = $previousMessage;
				$result['nextmessage'] = $nextMessage;

				// TODO: We have to force display with inline style below,
				// because it is display: none by default in layout.css.
				echo "<div id=\"msg-wrapper\" style=\"display: block;\">\n";
				echo render_displayMessage( $result, $requestParams );
				echo "\n</div>";
			}
			break;
		case "settings":
			// Settings toolbar.
			// Perform an action if we need to.
			$setaction = _GETORPOST( 'setaction', 'nothing' );

			$result = array();

			// Temporary hack for identities; split up the incoming opts-identity-list value.
			$selectedIdentity = "";
			if ( isset( $_POST['opts-identity-list'] ) ) {
				$selectedIdentity = explode( ",", $_POST['opts-identity-list'] );
				$selectedIdentity = $selectedIdentity[0];
			}

			switch ( $setaction ) {
				// Ordinary settings - save them.
				case 'save changes':
					$result = request_wrapper( 'settingsPanelSave' );
					break;

				// Identity editor actions.
				// opts-identity-list
				// opts-identity-working
				// opts-identity-name
				// opts-identity-address
				case 'add identity':
					// Just set the workingident flag, and the form will do the rest!
					$result['workingident'] = "";
					break;
				case 'edit identity':
					// Display an identity...
					// All we have to do here is say which one to show.
					$result['workingident'] = $selectedIdentity;
					break;
				case 'set as default':
					$_POST['action'] = "setdefault";
					$_POST['oldid'] = $selectedIdentity;

					$result = request_wrapper( 'identityEditor' );
					break;
				case 'remove identity':
					$_POST['action'] = "delete";
					$_POST['oldid'] = $selectedIdentity;

					$result = request_wrapper( 'identityEditor' );
					break;
				case 'save identity':
					if ( empty( $_POST['opts-identity-working'] ) ) {
						// New identity!
						$_POST['action'] = "add";
					} else {
						// Edit identity.
						$_POST['action'] = "edit";
						$_POST['oldid'] = $_POST['opts-identity-working'];
					}
					$_POST['idname'] = $_POST['opts-identity-name'];
					$_POST['idemail'] = $_POST['opts-identity-address'];

					$result = request_wrapper( 'identityEditor' );
					break;

				// Mailbox manager actions.

				// The do-nothing handler...
				default:
				case 'nothing':
					break;
			}

			// TODO: If a request failed, display its errors!

			$requestParams['sequence'] = 'settings';
			$requestParams['tab']      = _GETORPOST( 'tab', 'settings' );

			$result = generateOptionsPanel( true, $result, $requestParams );

			// Render the settings panel.
			// TODO: We have to force display with inline style below,
			// because it is display: none by default in layout.css.
			echo "<div id=\"opts-wrapper\" style=\"display: block;\">\n";
			echo $result['htmlFragment'];
			echo "\n</div>";
			break;
		case "error";
			break;
		default:
			// Do nothing.
			break;
	}

	// Step 4. Display page footers.
	echo "</body></html>";
}

imap_errors();
@imap_close($mbox);
exit;

?>

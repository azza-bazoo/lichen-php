<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
include/imap.inc.php - functions for communicating with the IMAP server
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

// Connect to the IMAP server. By default, connects to INBOX.
function connectToServer( $user, $pass, $mailbox_to_open = "INBOX" ) {
	global $mbox, $mailbox, $IMAP_CONNECT;

	$mbox = @imap_open( $IMAP_CONNECT . $mailbox_to_open, $user, $pass );
	if ( $mbox === false ) {
		return false;
	}
	$mailbox = $mailbox_to_open;

	return true;
}


// Changes the current mailbox, if we need to.
function changeMailbox( $mailbox_to_use ) {
	global $mbox, $mailbox, $IMAP_CONNECT;

	if ( !@imap_reopen( $mbox, $IMAP_CONNECT . $mailbox_to_use ) ) {
		return false;
	}
	$mailbox = $mailbox_to_use;

	return true;
}

// Helper sorting function used by getMailboxList().
function mailboxSort( $a, $b ) {
	/* Ok, someone is going to ask why we do some str_replace() foo in the comparison below.
	 * Alright, take this example.
	 * You have this folder list:
	 * toplevel
	 * toplevel.subfolder
	 * toplevel.subfolder2
	 * toplevel-folder
	 * toplevel-folder.subfolder
	 * The original sort just strcmp()'d the whole folder name. However,
	 * what would happen is that you would end up with:
	 * toplevel
	 * toplevel-folder
	 * toplevel-folder.subfolder
	 * toplevel.subfolder
	 * toplevel.subfolder2
	 * Which is not what we want.
	 * The reason this occurs is that "-" comes before "." in terms of ASCII sort order.
	 * So what I do here is replace the delimiter (as it might not be a dot) with a space
	 * which forces things with the delimiter to sort earlier.
	 * However, then we had the case where you have the same thing, but a folder with a space
	 * in its name. Thus, we put 5 spaces in it, to force it to sort ealier.
	 * It appears to work well enough.
	 */
	return strcmp( str_replace( $a['delimiter'], "     ", $a['fullboxname'] ), str_replace( $b['delimiter'], "     ", $b['fullboxname'] ) );
}

// Returns a list of mailboxes for this user - a complete list with
// names, numbers of messages, etc.
// Returns NULL on failure.
function getMailboxList() {
	global $mbox, $mailbox, $IMAP_CONNECT, $SPECIAL_FOLDERS;

	$list = imap_getmailboxes( $mbox, $IMAP_CONNECT, "*" );

	if ( $list === false ) return null;

	$mailboxList = array();
	if ( is_array( $list ) ) {
		$connectlength = strlen( $IMAP_CONNECT );

		foreach ( $list as $val ) {
			$mailboxFullName = imap_utf7_decode( $val->name );

			// The names are returned in the format "{localhost/options}Mailbox.Name"
			// We have to remove the leading "{server...}" bit.
			$mailboxFullName = substr( $mailboxFullName, $connectlength );

			// Query out the status of this mailbox
			$statusObj = imap_status( $mbox, $IMAP_CONNECT . $mailboxFullName, SA_ALL );

			// Determine this mailbox's position in the tree
			$mailboxParent = "";
			$mailboxName = $mailboxFullName;
			if ( isset( $val->delimiter ) && $val->delimiter != '' ) {
				$boxBits = explode( $val->delimiter, $mailboxFullName );
			} else {
				// TODO: this may not be a safe assumption to make?
				$boxBits = array( $mailboxFullName );
			}
			if ( count( $boxBits ) > 1 ) {
				// This is a subfolder of a folder.
				$mailboxParent = implode( $val->delimiter, array_slice( $boxBits, 0, count( $boxBits ) - 1 ) );
				$mailboxName = $boxBits[ count( $boxBits ) - 1 ];
			}

			$mailboxList[] = array(
				"fullboxname" => $mailboxFullName,
				"mailbox" => $mailboxName,
				"parent" => $mailboxParent,
				"delimiter" => $val->delimiter,
				"folderdepth" => count($boxBits) - 1,      // 0 = Top level
				"messages" => $statusObj->messages,
				"recent" => $statusObj->recent,
				"unseen" => $statusObj->unseen,
				"selectable" => ($val->attributes & LATT_NOSELECT) ? false : true,
				"haschildren" => ($val->attributes & LATT_HASCHILDREN) ? true : false,
				"uidConst" => $statusObj->uidvalidity
			);
		}

		// Sort the list on fullboxname.
		// (Which sorts the child folders correctly, too)
		usort( $mailboxList, "mailboxSort" );
	}

	return $mailboxList;
}


// This function is called before trying to access a special mailbox.
// If the mailbox doesn't exist, it is created.
function imapCheckMailboxExistence( $boxname ) {
	global $mbox, $IMAP_CONNECT;

	$exists = @imap_status( $mbox, $IMAP_CONNECT . $boxname, SA_ALL );

	if ( $exists === false ) {
		$result = imap_createmailbox( $mbox, $IMAP_CONNECT . $boxname );
		if ( $result == false ) {
			return false;
		}
	}

	return true;
}

// Adjust a flag on a given message.
// State can be either TRUE, FALSE, or TOGGLE (as a string, or boolean).
// uid can be a comma seperated list of flags - if so, toggle is not valid.
// Return value is an associative array.
// keys are 'success', 'errormessage', and 'flagstate'.
function imapTwiddleFlag( $uid, $flag, $state, $thisMailbox = "" ) {
	global $mbox, $mailbox;
	$oldMailbox = "";

	// Change mailbox if we need to.
	if ( $thisMailbox != "" && $thisMailbox != $mailbox ) {
		$oldMailbox = $mailbox;
		changeMailbox( $thisMailbox );
	}

	$uidList = explode( ",", $uid );
	$singleUid = true;
	if ( count( $uidList ) > 1 ) {
		$singleUid = false;
	}

	// Determine the state that we want to set the flag to.
	$computedState = false;
	if ( strcasecmp( $state, "true" ) == 0 || $state === true ) {
		$computedState = true;
	} else if ( strcasecmp( $state, "false" ) == 0 || $state === false ) {
		$computedState = false;
	} else {
		if ( !$singleUid ) {
			// Failure: can't toggle more than one message.
			// TODO: No reason why we can't do a toggle on a set of messages.
			return array( 'success' => false, 'errormessage' => _("Can't toggle multiple messages."), 'flagstate' => false );
		}
		// All other cases: toggle.
		$headerInfo = imap_headerinfo( $mbox, imap_msgno( $mbox, $uid ) );

		if ( $headerInfo === false ) {
			// TODO: Don't return here.
			return array( 'success' => false, 'errormessage' => _("Attempting to set flag on non-existant message."), 'flagstate' => false );
		}

		$headerInfo = get_object_vars( $headerInfo );

		$flagName = ucfirst( str_replace( '\\', '', strtolower( $flag ) ) );
		//print_r($headerInfo);

		if ( $flagName == "Seen" ) {
			// The single special case.
			if ( $headerInfo['Unseen'] == 'U' ) {
				// NOT seen, so set it to seen.
				$computedState = true;
			} else {
				// Seen, so set it to not seen.
				$computedState = false;
			}
		} else {
			// NOTE: The documentation is unclear: if a flag is clear,
			// the value is a SPACE character, NOT an EMPTY STRING as the
			// documentation would imply!
			if ( trim( $headerInfo[$flagName] ) == '' ) {
				$computedState = true;
			} else {
				$computedState = false;
			}
		}
	}

	// Make sure flag has a preceeding \\.
	// This also means that the callee doesn't have to supply that.
	$flag = str_replace( '\\', '', $flag );
	$flag = "\\{$flag}";

	$success = false;
	if ( $computedState ) {
		// Set the flag.
		foreach ( $uidList as $workingUid ) {
			$workingUid = trim($workingUid);
			$success = imap_setflag_full( $mbox, $workingUid, $flag, ST_UID );
		}
	} else {
		foreach ( $uidList as $workingUid ) {
			$workingUid = trim($workingUid);
			$success = imap_clearflag_full( $mbox, $workingUid, $flag, ST_UID );
		}
	}

	// Reset the mailbox.
	if ( $thisMailbox != "" && $thisMailbox != $oldMailbox ) {
		changeMailbox( $oldMailbox );
	}

	return array( 'success' => $success, 'errormessage' => imap_last_error(), 'flagstate' => $computedState );
}


// Move the message UIDs in $uidArray to the mailbox in $destination
// and return the number of messages for which the operation failed.
function moveMessages( $destination, $uidArray ) {
	global $mbox;

	$failureCounter = 0;

	foreach ( $uidArray as $message ) {
		$message = trim( $message );
		if ( $message == "" ) continue;

		$result = imap_mail_move( $mbox, $message, $destination, CP_UID );

		if ( !$result ) $failureCounter++;
	}

	imap_expunge( $mbox ); // TODO: applies to all mailboxes, not just the one we worked on. ??

	return $failureCounter;
}

// Delete the message UIDs in $uidArray and return the number of
// messages for which the operation failed.
function deleteMessages( $uidArray ) {
	global $mbox;

	$failureCounter = 0;

	foreach ( $uidArray as $message ) {
		$message = trim( $message );
		if ( $message == "" ) continue;

		$result = imap_delete( $mbox, $message, FT_UID );

		if ( !$result ) $failureCounter++;
	}

	imap_expunge( $mbox ); // TODO: applies to all mailboxes, not just the one we worked on. ??

	return $failureCounter;
}


// TODO: this code is the first to throw an error if the PHP IMAP library
// isn't installed; there should be a check before this code is parsed.
// Also, the array should move into the function below if not needed outside?
$sortParameters = array(
	"date" => SORTARRIVAL, // Faster than SORTDATE (apparently) but doesn't work in reverse (apparently)?
	"from" => SORTFROM,
	"subject" => SORTSUBJECT,
	"to" => SORTTO,
	"cc" => SORTCC,
	"size" => SORTSIZE
);

// This function lists messages from a mailbox, with several criteria.
// The criteria are a search parameter, a sort parameter, and a page
// parameter.
// Page starts at "0".
function listMailboxContents( $searchq, $sort, $page, $metadataOnly = false ) {
	global $mbox, $mailbox, $IMAP_CONNECT, $sortParameters, $USER_SETTINGS;

	$thisMailbox = imap_status( $mbox, $IMAP_CONNECT . $mailbox, SA_ALL );
	$mailboxTotalCount = $thisMailbox->messages;
	$msgCount = 0;

	// Figure out our sort parameter.
	// Use the user's default if not given.
	if ( $sort == "" ) {
		if ( isset( $USER_SETTINGS['list_sortmode'] ) ) {
			$sort = $USER_SETTINGS['list_sortmode'];
		}
	}

	// Incoming sort is something like "date" or "date_r", the latter
	// being the reverse invocation.
	$sortParameter = SORTARRIVAL;
	$sortDirection = 0; // Ascending.
	$sortBits = explode( "_", $sort );
	if ( isset( $sortParameters[ $sortBits[0] ] ) ) {
		// Ok, found that search parameter.
		$sortParameter = $sortParameters[ $sortBits[0] ];
		if ( count( $sortBits ) > 1 ) {
			// Must have had an "_r" on the end, so sort reverse.
			$sortDirection = 1;
		}
	}

	// Strip out anything that might give the imap C library pause for thought.
	// The imap C library tries to be clever and reescape the input, leading to mangled queries.
	// So we just pretend that none of these characters exist...
	// TODO: Make this work. This is a workaround at the moment.
	$searchq = str_replace( array( "\r", "\n", "\0", "\t", "\"" ),
				"", //array( "\\r", "\\n", "\\0", "\\t" ),
				$searchq );

	$msgNumsToShow = array();

	if ( $searchq != "" ) {
		// There is very little (or no) documentation about what is safe to pass as a search parameter.
		// Our mucking around with a packet sniffer showed some interesting results:
		// Search Param: 'TEXT "hello"'           ->    'TEXT hello'
		// Search Param: 'TEXT "hello world"'     ->    'TEXT "hello world"'
		// Basically, the C library rewrites the query somewhat. Our temporary fix is to
		// strip any dubious characters. (Oh, adding \n or \r makes the search query makes the
		// whole thing fail, without giving error messages.)
		$msgNumsToShow = imap_sort( $mbox, $sortParameter, $sortDirection, SE_NOPREFETCH | SE_UID, "TEXT \"$searchq\"" );
		if ($msgNumsToShow === false) {
			$msgNumsToShow = array();
		}
		$msgCount = count( $msgNumsToShow );
	} else {
		// Maybe use imap_check instead?
		if ( !$metadataOnly ) {
			// Load the list of messages...
			$msgNumsToShow = imap_sort( $mbox, $sortParameter, $sortDirection, SE_NOPREFETCH | SE_UID, "ALL" );
			if ($msgNumsToShow === false) {
				$msgNumsToShow = array();
			}
			$msgCount = count( $msgNumsToShow );
		} else {
			// We only want metadata, and imap_sort is expensive in time.
			// The only detail we need if we're after metadata is the number of messages.
			// TODO: Figure out how to do this for the version with the search parameter, above.
			$msgCount = $thisMailbox->messages;
		}
	}

	// Determine, based on the number of messages and the selected
	// page, how many messages to actually query.
	$pageSize = $USER_SETTINGS['list_pagesize'];
	$msgStart = 0;
	$numberPages = ceil( ( $msgCount / $pageSize ) );
	if ( $numberPages == 0 ) {
		$numberPages = 1; // Always have one page, even if it's blank.
	}
	$thisPage = 0;

	if ( $page < 0 || ( $page * $pageSize ) > $msgCount ) {
		// Either, there is less messages than one page size, or
		// the page parameter passed is invalid.
		// Return a blank list. This is so the client knows that
		// this page was invalid.
		$msgNumsToShow = array();
		$thisPage = -1;
	} else {
		// Calculate where we are.
		$msgStart = $page * $pageSize;
		$thisPage = $page;
	}

	// HACK: Cache the full set in $_SESSION so that later
	// we can figure out previous/next messages for display.
	if ( !$metadataOnly ) {
		$_SESSION['boxcache'] = $msgNumsToShow;
	}

	// Slice out those messages that we are showing.
	$msgNumsToShow = array_slice( $msgNumsToShow, $msgStart, $pageSize );

	$messageArray = array();

	if ( !$metadataOnly ) {
		$messageArray = fetchMessages( $msgNumsToShow );
	}
	return array(
			"mailbox" => $mailbox,
			"messages" => $messageArray,
			"pagesize" => (integer)$pageSize,
			"numberpages" => (integer)$numberPages,
			"thispage" => (integer)$thisPage,
			"numbermessages" => (integer)$msgCount,
			"mailboxmessages" => (integer)$mailboxTotalCount,
			"search" => stripslashes( $searchq ),
			"sort" => $sort
		);
}

// Takes an array of message numbers, and returns an array of
// arrays of list-display data for each of those messages.
function fetchMessages( $messageUids ) {
	global $mbox, $mailbox, $IMAP_CONNECT, $USER_SETTINGS;

	$newArray = array();

	foreach ( $messageUids as $uid ) {

		$msgNo = imap_msgno( $mbox, $uid );

		// Maybe use imap_fetch_overview instead
		$headers = @imap_headerinfo( $mbox, $msgNo );

		if ( $headers === false ) {
			// This message doesn't actually exist. Skip it!
			continue;
		}

		$fromname = "";
		$fromaddr = "";
		if ( isset( $headers->from ) ) {
			$from = $headers->from;

			$senderObj = array_shift( $from );
			if ( isset( $senderObj->personal ) ) {
				$fromname = filterHeader( $senderObj->personal );
			}
			$fromaddr = filterHeader( $senderObj->mailbox ) . "@" . filterHeader( $senderObj->host );
		} else {
			// TODO: should this throw an error message?
			$fromaddr = _("(unknown sender)");
		}

		$thisMessage = array();

		// Is the message we're displaying unread?
		if ( $headers->Unseen == "U" || $headers->Recent == "N" ) {
			$thisMessage['readStatus'] = "U";
		} else {
			$thisMessage['readStatus'] = "R";
		}

		if ( $headers->Flagged == 'F' ) {
			$thisMessage['flagged'] = true;
		} else {
			$thisMessage['flagged'] = false;
		}
		if ( $headers->Answered == 'A' ) {
			$thisMessage['answered'] = true;
		} else {
			$thisMessage['answered'] = false;
		}
		if ( $headers->Draft == 'X' ) {
			$thisMessage['draft'] = true;
		} else {
			$thisMessage['draft'] = false;
		}

		$thisMessage['uid'] = $uid;
		$thisMessage['fromAddr'] = $fromaddr;
		$thisMessage['fromName'] = $fromname;

		$subject = _("(no subject)");
		if ( isset ( $headers->subject ) ) {
			$subject = filterHeader( $headers->subject );
		}
		$thisMessage['subject'] = $subject;
		
		// Make all the fields be HTML entity encoded.
		// (We do this before we process the date, below, because
		// this doesn't need to be HTML encoded, and in fact causes
		// odd results when it is HTML encoded.
		foreach ( $thisMessage as $key => $value ) {
			if ( is_string( $value ) ) {
				$thisMessage[$key] = htmlentities( $value );
			}
		}

		if ( isset( $headers->date ) ) {
			$localDate = processDate( $headers->date );
		} else if ( isset( $headers->MailDate ) ) {
			$localDate = processDate( $headers->MailDate );
		}
		$thisMessage['dateString'] = $localDate;


		// Produce a message preview to show in the list.
		// (We do the substr after filtering because it improves display
		// for HTML mail, but there's one case where that method fails.)
		$previewString = "";
		$fetchedMsg = retrieveMessage( $uid, true );

		// If plain text parts are available, use them for the message preview
		// TODO: this code used to call filterHeader(), but the text has already
		// been converted to UTF-8 so that's unnecessary. There is a risk that
		// the substr below will cut an HTML entity in half..
		if ( $fetchedMsg['textplainpresent'] ) {
			$previewString = htmlspecialchars( implode( '', $fetchedMsg['textplain'] ) );
		} else {
			$previewString = htmlspecialchars( strip_tags( implode( '', $fetchedMsg['texthtml'] ) ) );
		}

		// In the preview, reduce strings of non-alpha characters to just a few.
		// The idea is to reduce headers in emails like "============".
		$previewString = preg_replace( '/([\W]{2})[\W]+/', '$1', $previewString );

		// Note that this returns 150 bytes, not 150 characters,
		// unless mbstring function overloading is on.
		$previewString = substr( $previewString, 0, 150 );

		$thisMessage['preview'] = $previewString;

		$thisMessage['size'] = formatNumberBytes( $headers->Size );

		array_push( $newArray, $thisMessage );

	}

	return $newArray;
}


// Move a mailbox to be a child of another mailbox.
// This also affects all children mailboxes.
// To implement this, it is just a rename of the mailbox(es) in question.
function imapMoveMailbox( $sourceMailbox, $newParent ) {
	global $mbox, $IMAP_CONNECT;

	$sourceExists    = imap_status( $mbox, "{$IMAP_CONNECT}{$sourceMailbox}", SA_ALL );
	if ( !$sourceExists ) {
		return _("Unable to move a mailbox that doesn't exist.");
	}

	if ( $newParent != "" ) {
		$newParentExists = imap_status( $mbox, "{$IMAP_CONNECT}{$newParent}", SA_ALL );
		if ( !$newParentExists ) return _("Unable to move folders to a place that doesn't exist.");
	}

	$mailboxes = getMailboxList();

	// TODO: Assuming same delimiter for all mailboxes.
	$delimiter = $mailboxes[0]['delimiter'];

	// Figure out the "name" part of the source mailbox.
	$boxBits = explode( $delimiter, $sourceMailbox );
	$mailboxName = $boxBits[ count( $boxBits ) - 1 ];

	// Now, build a list of children mailboxes.
	$childBoxes = array();

	// Figure out the children of the mailbox to be moved.
	foreach ( $mailboxes as $mailbox ) {
		$mailbox = $mailbox['fullboxname'];
		if ( substr( $mailbox, 0, strlen( $sourceMailbox ) ) == $sourceMailbox ) {
			// This is a child mailbox of sourceMailbox.
			// Store the full source mailbox name, and the "child name".
			$childBoxes[] = array( "oldname" => $mailbox,
				"boxname" => substr( $mailbox, strlen( $sourceMailbox ) - strlen( $mailboxName ) ) );
		}
	}

	// Process them in reverse order - stops the IMAP server giving us "Mailbox exists" errors.
	$childBoxes = array_reverse( $childBoxes );

	// Loop over all the boxes we have to rename.
	// Figure out the new final name, and then rename it.
	$result = false;
	foreach ( $childBoxes as $child ) {
		$destName = "";
		if ( $newParent == "" ) {
			// We're moving it to be a top level mailbox.
			$destName = $child['boxname'];
		} else {
			// This is to be a subfolder of another folder.
			$destName = "{$newParent}{$delimiter}{$child['boxname']}";
			// Can't move a mailbox to be a child of itself... it works, but very unintuitive!
			if ( strpos( $newParent, $child['boxname'] ) !== FALSE ) {
				// Found it. This means we're trying to make it a child of itself.
				return _("Can not make a mailbox a child of itself.");
			}
		}

		// Now do the rename.
		$result = imap_renamemailbox( $mbox, $IMAP_CONNECT . $child['oldname'], $IMAP_CONNECT . $destName );

		if ( $result == false ) {
			// Stop!
			break;
		}
	}

	if ( $result ) {
		return NULL;
	} else {
		return _("Unable to move mailbox: ") . imap_last_error();
	}
}

// Wrapper to fetch the mailbox status.
function imapMailboxStatus( $mailboxToCheck ) {
	global $mbox, $IMAP_CONNECT;

	$mailboxData = imap_status( $mbox, $IMAP_CONNECT . $mailboxToCheck, SA_ALL );

	// TODO: What the hell does imap_status return on an invalid mailbox?
	// The docs don't say!
	if ( $mailboxData == null ) {
		return null;
	} else {
		return $mailboxData;
	}
}

?>

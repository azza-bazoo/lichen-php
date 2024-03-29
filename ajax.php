<?php
/**

Lichen - AJAX IMAP client
version 0.4 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
ajax.php - handler script to dispatch Lichen requests
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
include( "include/requests.inc.php" );

session_start();

if ( !isset( $_SESSION['pass'] ) || !isset( $_SESSION['user'] ) ) {
	if ( isHtmlSession() ) {
		// Redirect the user back to the login page.
		header( "Location: index.php?autologout" );
	} else {
		die( remoteRequestFailure( 'AUTH', _("Sorry, you're not logged in!") ));
	}
}

$start_time = microtime(TRUE);

$result = connectToServer( $_SESSION['user'], $_SESSION['pass'], _GETORPOST( 'mailbox', $SPECIAL_FOLDERS['inbox'] ) );

$connect_time = microtime(TRUE);

if ( !$result ) {
	if ( isHtmlSession() ) {
		printPageHeader();
		echo "<div>", _("Unable to connect to the IMAP server: "), imap_last_error(), "</div>";
		echo "</body></html>";
		die();
	} else {
		die( remoteRequestFailure( 'IMAP', _("Unable to connect to the IMAP server: ") . imap_last_error() ));
	}
}

// Load the user settings.
$USER_SETTINGS = getUserSettings();

date_default_timezone_set( $USER_SETTINGS['timezone'] );

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

// Grubby, ill-placed hack to handle "select all messages in mailbox".
// If allmailbox and allsearch are set, replace the post variable uid
// with all the UIDs matching the given mailbox and search.
if ( isset( $_POST['allmailbox'] ) && isset( $_POST['allsearch'] ) ) {
	$_POST['uid'] = implode( ",", rawMessageList( $_POST['allsearch'] ) );
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

		$result['begin'] = $start_time;
		$result['connect'] = $connect_time;
		$result['proctime'] = microtime(TRUE);

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
	
	include( "include/htmlrender.inc.php" );

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
	function request_setFlash( $flashMessage ) {
		$_SESSION['flash'] = $flashMessage;
	}
	function request_displayFlash( $requestResult = array(), $flashMessage = "" ) {
		// Display a flash message, if set... and an error or message, if needed.
		$result = "";

		if ( isset( $_SESSION['flash'] ) ) {
			$result .= $_SESSION['flash'];
			unset( $_SESSION['flash'] );
		}

		if ( !empty( $flashMessage ) ) {
			$result .= $flashMessage;
		}

		if ( isset( $requestResult['success'] ) && $requestResult['success'] == false ) {
			$result .= _('Error:');
			$result .= " " . $requestResult['errorString'];
		}

		if ( !empty( $result ) ) {
			$result = "<div id=\"notification\" style=\"display: block;\">" . $result . "</div>";
		}

		return $result;
	}

	// Prepare core variables.
	$requestParams = array();
	$requestParams['mailbox'] = _GETORPOST( 'mailbox', "INBOX" );
	$requestParams['search']  = _GETORPOST( 'search' );
	$requestParams['sort']    = _GETORPOST( 'sort' );
	$requestParams['page']    = _GETORPOST( 'page', 0 );

	// Twiddle with the list of open mailboxes.
	$openMailboxes = _GETORPOST( 'mboxopen' );
	$openMailboxes = explode( ",", $openMailboxes );

	$toggleMailbox = _GETORPOST( 'mboxtoggle' );
	if ( !empty( $toggleMailbox ) ) {
		// Toggle a mailbox.
		$index = array_search( $toggleMailbox, $openMailboxes );
		if ( $index === false ) {
			// Add this mailbox to the list of open mailboxes.
			array_push( $openMailboxes, $toggleMailbox );
		} else {
			// Remove this mailbox from the list of open mailboxes.
			array_splice( $openMailboxes, $index, 1 );
		}
	}

	$requestParams['mboxopen'] = implode( ",", $openMailboxes );

	// Figure out the title.
	// It should be in the form "Mailbox - (N unread, N total)"
	$pageTitle = "";
	foreach ( $mailboxList as $thisMailbox ) {
		if ( $mailbox == $thisMailbox['fullboxname'] ) {
			$pageTitle .= " - ";
			$pageTitle .= $thisMailbox['mailbox'];
			$pageTitle .= " (";
			if ( $thisMailbox['unseen'] > 0 ) {
				$pageTitle .= $thisMailbox['unseen'] . " " . _('unread') . ", ";
			}
			$pageTitle .= "{$thisMailbox['messages']} total)";
		}
	}

	// Step 1: basic page layout.
	// We use tables so that browsers that don't support
	// all the fancy CSS stuff do look alright.
	// (And browsers using CSS will ignore the table)
	ob_start();
	printPageHeader( $pageTitle );
	echo "<table border=\"0\" width=\"100%\">";
	echo "<tr><td valign=\"top\" rowspan=\"2\" style=\"border: none;\">";

	// Step 2: Mailbox list (always visible)
	echo "<ul id=\"mailboxes\">\n";
	echo render_mailboxList( array( "mailboxList" => $mailboxList, "openMailboxes" => $openMailboxes ), $requestParams );
	echo "</ul>\n";
	
	echo "</td><td style=\"border: none;\">";
	drawToolbar( 'corner-bar', true );

	// Function to end the toolbar area and show the content area.
	function html_startContentArea() {
		echo "</td></tr>";
		echo "<tr><td align=\"left\"valign=\"top\" style=\"border: none;\">";
	}

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

			if ( $requestParams['selector'] == "allinmailbox" ) {
				// Grubby hack: set the parameters so that operations on all
				// messages in an inbox work.
				$requestParams['allmailbox'] = $requestParams['mailbox'];
				$requestParams['allsearch']  = $requestParams['search'];
			}

			// Perform any actions if we need to.
			$subaction = _GETORPOST( 'listaction' );
			$saResult = array();
			if ( !empty( $subaction ) ) {
				if ( $requestParams['selector'] != "allinmailbox" ) {
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
				}

				// Determine what action to take and make it happen!
				switch ( $subaction ) {
					case _('move'):
						// Move messages to a given mailbox.
						$_POST['destbox'] = substr( _GETORPOST( 'movemessage' ), 5 );

						$saResult = request_moveMessage();

						unset( $requestParams['selector'] );
						break;
					case _('delete'):
						$saResult = request_deleteMessage();
						
						unset( $requestParams['selector'] );
						break;
					case _('flag'):
						$_POST['flag'] = 'flagged';
						$_POST['state'] = "true";

						$saResult = request_setFlag();
						break;
					case 'flagtoggle': // Not translated: internal only.
						$_POST['flag'] = 'flagged';
						$_POST['state'] = "toggle";

						$saResult = request_setFlag();
						break;
					case _('mark read'):
						$_POST['flag'] = "seen";
						$_POST['state'] = "true";

						$saResult = request_setFlag();
						break;
				}
			}
			
			// Show the toolbar.
			drawToolbar( 'list-bar', true, $requestParams );

			// Show any error messages.
			if ( !empty( $subaction ) ) {
				if ( request_failed( $saResult ) ) {
					echo request_displayFlash( $saResult );
				} else {
					echo request_displayFlash( array(), $saResult['message'] );
				}
			}
			html_startContentArea();

			// Get the list of messages.
			$result = request_wrapper( 'mailboxContentsList' );

			// Hack... the data we need is in the key 'data' in request,
			// but we want it up one level.
			$result['sort'] = $requestParams['sort'];
			$result = array_merge( $result, $result['data'] );

			// Render the list.
			echo "<div id=\"list-wrapper\">";
			echo render_messageList( $result, $requestParams );
			echo "</div>";

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
				case _("forward inline"):
					$_POST['mode'] = "forwardinline";
				case _("forward message as attachment"):
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
				case _("Send Message"):
					$result = request_wrapper( 'sendMessage' );

					if ( !request_failed( $result ) ) {
						// Success! Return to the inbox...
						$displayComposer = false;
					} else {
						// Restore the data that the user posted.
						$mergeBack = true;
					}
					break;
				case _("Save Draft"):
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
				case _("Change Identity"):
					$oldIdentity = getUserIdentity( $_POST['comp_shipped_identity'] );
					$newIdentity = getUserIdentity( $_POST['comp_identity'] );
					
					$mergeBack = true;

					// What we need to do here is change the signature to match
					// the new identity.
					// If we can't replace it, just append the new signature to the end.
					$data = $_POST['comp_msg'];
					$data = str_replace( "\r\n", "\n", $data );
					$sigIndex = false;
					if ( !empty( $oldIdentity['signature'] ) ) {
						$sigIndex = strpos( $data, $oldIdentity['signature'] );
					}
					if ( $sigIndex !== false ) {
						// Replace the signature with the new one.
						$data = substr( $data, 0, $sigIndex ) .
							$newIdentity['signature'] .
							substr( $data, $sigIndex + strlen( $oldIdentity['signature'] ) );
					} else {
						// Tack the new signature onto the end.
						$data .= "\n" . $newIdentity['signature'];
					}
					$_POST['comp_msg'] = $data;
					break;
				case _("upload file"):
					// Uploaded a new file...

					$result = request_wrapper( 'uploadAttachment' );

					if ( !request_failed( $result ) ) {
						$result['comp_attach'] = array( array(
							"filename" => $result['filename'],
							"type" => $result['type'],
							"size" => $result['size'],
							"isforwardedmessage" => false
						) );
					}

					$mergeBack = true;
					break;
			}
			
			if ( $displayComposer ) {
				// Show any error messages.
				echo request_displayFlash( $result );
				html_startContentArea();

				if ( $mergeBack ) {
					// Assemble some data that we'll need.
					$result = array_merge( $_POST, $result );
					$result['identities'] = encodeIdentities( $USER_SETTINGS['identities'] );
					$result['identity']   = encodeIdentities( array( getUserIdentity( $_POST['comp_identity'] ) ) );
					$result['identity']   = $result['identity'][0];
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
				// TODO: Include proper query string below.
				request_setFlash( $result['message'] );
				ob_end_clean();
				header( "Location: ajax.php?" . html_entity_decode( genLinkQuery( $requestParams, array( 'sequence' => 'list' ) ) ) );
				die();
			}
			break;
		case "disp":
			// ---------------------
			// Display a message
			//
			$subaction = _GETORPOST( 'dispaction', '' );

			$saResult = array(
				"success" => true
			);

			switch ( $subaction ) {
				case _('move'):
					$_POST['destbox'] = substr( _GETORPOST( 'movemessage' ), 5 );
					$_POST['uid']     = $_POST['msg'];

					$saResult = request_moveMessage();
					break;
				case _('delete message'):
					$_POST['uid'] = $_POST['msg'];
					$saResult = request_deleteMessage();
					break;
			}

			if ( request_failed( $saResult ) ) {
				// Flash an error message.
				// After the toolbar is drawn.
			} else if ( !empty( $subaction ) ) {
				// View the next message, if set. Otherwise, return to list.
				request_setFlash( $saResult['message'] );
				if ( isset( $_POST['nextuid'] ) ) {
					// Slice out the UID from the boxcache list.
					// We use this list to find the previous/next messages, and if we
					// don't remove it from the cached list, things go strange.
					$currentIndex = array_search( $_POST['msg'], $_SESSION['boxcache'] );
					if ( $currentIndex !== false ) {
						array_splice( $_SESSION['boxcache'], $currentIndex, 1 );
					}
					$_POST['msg'] = $_POST['nextuid'];
				} else {
					request_setFlash( _('End of mailbox reached.') );
					ob_end_clean();
					header( "Location: ajax.php?" . html_entity_decode( genLinkQuery( $requestParams, array( 'sequence' => 'list' ) ) ) );
					die();
				}
			}

			// Fetch the message data.
			$result = request_wrapper( 'getMessage' );

			// Organise a few other parameters.
			$result['sort'] = $requestParams['sort'];
			$requestParams['mode'] = _GETORPOST( 'mode', 'auto' );
			$requestParams['sequence'] = 'disp';
			$requestParams['msg'] = $result['data']['uid'];
			
			// Draw the toolbar.
			// This toolbar is depenant on the request parameters.
			drawToolbar( 'msg-bar', true, $requestParams );
			echo request_displayFlash( $saResult );
			html_startContentArea();
			
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
			} else {
				// Set a flash message of the error, then return to the inbox.
				request_setFlash( $result['errorString'] );
				ob_end_clean();
				header( "Location: ajax.php?" . html_entity_decode( genLinkQuery( $requestParams, array( "sequence" => "list" ) ) ) );
				die();
			}
			break;
		case "settings":
			// Settings toolbar.
			// Perform an action if we need to.
			$setaction = _GETORPOST( 'setaction', 'nothing' );
			if ( $setaction == 'cancel' ) $setaction = 'nothing';

			// For the mailbox manager...
			$setactionReturn = _GETORPOST( 'setactionreturn', false );
			if ( $setactionReturn == "true" ) $setactionReturn = true;
			$requestParams['mbm-mailbox'] = _GETORPOST( 'mbm-mailbox' );
			$requestParams['setaction'] = $setaction;

			$result = array();

			// For the identity editor.
			$selectedIdentity = _GETORPOST( 'opts-identity-list' );
			
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
				case _('add identity'):
					// Just set the workingident flag, and the form will do the rest!
					$result['workingident'] = "";
					break;
				case _('edit identity'):
					// Display an identity...
					// All we have to do here is say which one to show.
					$result['workingident'] = $selectedIdentity;
					break;
				case _('set as default'):
					$_POST['action'] = "setdefault";
					$_POST['oldid'] = $selectedIdentity;

					$result = request_wrapper( 'identityEditor' );
					break;
				case _('remove identity'):
					$_POST['action'] = "delete";
					$_POST['oldid'] = $selectedIdentity;

					$result = request_wrapper( 'identityEditor' );
					break;
				case _('save identity'):
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
					$_POST['idsig'] = $_POST['opts-identity-sig'];

					$result = request_wrapper( 'identityEditor' );
					break;

				// Mailbox manager actions.
				// mbm-mailbox
				// newname
				// newparent
				case _('add mailbox'):
				case _('add subfolder'):
					if ( $setactionReturn ) {
						$_POST['action'] = 'create';
						$_POST['mailbox1'] = $_POST['mbm-mailbox'];
						$_POST['mailbox2'] = $_POST['newname'];

						$result = request_wrapper( 'mailboxAction' );
					}
					break;
				case _('move mailbox'):
					if ( $setactionReturn ) {
						$_POST['action'] = 'move';
						$_POST['mailbox1'] = $_POST['mbm-mailbox'];
						$_POST['mailbox2'] = $_POST['newparent'];

						$result = request_wrapper( 'mailboxAction' );
					}
					break;
				case _('rename mailbox'):
					if ( $setactionReturn ) {
						$_POST['action'] = 'rename';
						$_POST['mailbox1'] = $_POST['mbm-mailbox'];
						$_POST['mailbox2'] = $_POST['newname'];
						
						$result = request_wrapper( 'mailboxAction' );
					}
					break;
				case _('delete mailbox'):
					if ( $setactionReturn ) {
						$_POST['action'] = 'delete';
						$_POST['mailbox1'] = $_POST['mbm-mailbox'];

						$result = request_wrapper( 'mailboxAction' );
					}
					break;

				// The do-nothing handler...
				default:
				case 'nothing':
					break;
			}

			// Show any error messages.
			echo request_displayFlash( $result );
			html_startContentArea();

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
		default:
			// Do nothing.
			break;
	}

	// Step 4. Display page footers.
	echo "</td></tr></table>";
	echo "</body></html>";
	ob_flush();
}

imap_errors();
@imap_close($mbox);
exit;

?>

<?php
/**

Lichen - AJAX IMAP client
version 0.4 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
include/compose.inc.php - form code to compose new messages
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

// Generate all the data required to build a composer window.
// At the moment, this gets returned to the client which can then build a composer.
// TODO: Stop using all these global variables and $_POST vars.
function generateComposerData( $mode, $uid, $mailto ) {
	global $mbox, $IMAP_CONNECT, $mailbox;
	global $IMAP_PORT, $IMAP_SERVER, $IS_SSL;
	global $SMTP_SERVER, $SMTP_PORT, $USER_SETTINGS;
	global $LICHEN_URL, $LICHEN_VERSION;
	global $SPECIAL_FOLDERS, $UPLOAD_ATTACHMENT_MAX;

	include ( 'libs/streamattach.php' );

	$compData = array();

	$message = null;
	$msgArray = array();
	if ( !empty( $uid ) ) {
		// It's in reply/forward of...
		// Load that message.
		$msgArray = retrieveMessage( $uid, false );

		if ( $msgArray == null ) {
			return null;
		}
	}

	$mailtoDetails = array();
	if ( !empty( $mailto ) ) {
		// Parse a string that was in an email.
		// Probably just a raw email address, but may be
		// something like "mailto:foo@bar.com?subject=Baz"
		$bits = explode( "?", $mailto );

		if ( count( $bits ) > 1 ) {
			// Parse the later part.
			parse_str( $bits[1], $mailtoDetails );
		}
		$mailtoDetails['email-to'] = $bits[0];
	}

	$action = "new";
	if ( !empty( $mode ) ) {
		$action = $mode;
	}

	if ( $action == "forward_default" ) {
		// Determine the default forward mode from the configuration, and
		// then allow the user to override it later.
		if ( $USER_SETTINGS['forward_as_attach'] ) {
			$action = "forwardasattach";
		} else {
			$action = "forwardinline";
		}
	}

	$understoodActions = array( "reply", "replyall", "forwardinline", "forwardasattach", "draft", "mailto", "new" );
	if ( !in_array( $action, $understoodActions ) ) {
		$action = "new";
	}

	$compData['comp_mode'] = $action;
	$compData['action']    = $action;
	if ( !empty( $uid ) ) {
		$compData['comp_quoteuid'] = $uid;
		$compData['comp_quotemailbox'] = $mailbox;
	}
	if ( $action == "draft" ) {
		$compData['comp_draftuid'] = $uid;
	}

	if ( isset( $msgArray['from'] ) ) {
		$compData['comp_from'] = $msgArray['from'];
	}
	
	// Send back a list of identities.
	$compData['identities'] = encodeIdentities( $USER_SETTINGS['identities'] );

	// Figure out the current identity.
	$compData['identity'] = null;
	if ( $action == "reply" || $action == "replyall" || $action == "forwardasattach" || $action == "forwardinline" ) {
		$compData['identity'] = getUserIdentity( $msgArray['to'], true );
	} else if ( $action == "draft" ) {
		$compData['identity'] = getUserIdentity( $msgArray['from'], true );
	}
	if ( $compData['identity'] == null ) {
		$compData['identity'] = getUserIdentity();
	}

	$compData['identity'] = encodeIdentities( array( $compData['identity'] ) );
	$compData['identity'] = $compData['identity'][0];


	// TODO: Much of this data is htmlentity encoded before it goes to the client, which is intended
	// to save the client doing it. That works as we're building innerHTML contents on the client.
	// Maybe in future this won't be true...

	// Determine the "to" address.
	$compData['comp_to'] = "";
	switch ($action) {
		case 'reply':
			// This is probably not the right thing to do.
			if ( isset( $msgArray['replyto'] ) && !empty( $msgArray['replyto'] ) ) {
				$compData['comp_to'] = $msgArray['reply_to'];
			} else {
				$compData['comp_to'] = $msgArray['from'];
			}
			break;
		case 'replyall':
			// TODO: pick the right header
			if ( isset( $msgArray['replyto'] ) && !empty( $msgArray['replyto'] ) ) {
				$compData['comp_to'] = $msgArray['replyto'];
			} else {
				$compData['comp_to'] = $msgArray['from'];
			}
			break;
		case 'draft':
			$compData['comp_to'] = $msgArray['to'];
			break;
		case 'mailto':
			$compData['comp_to'] = $mailtoDetails['email-to'];
			break;
	}
	$compData['comp_to'] = htmlentities( $compData['comp_to'] );

	// Determine CC address(es), if we need them.
	$compData['comp_cc'] = "";
	if ( isset( $msgArray['cc'] ) && !empty( $msgArray['cc'] ) ) {
		// Don't CC yourself when replying-to-all.
		if ( $action == "replyall" ) {
			$ccList  = parseRecipientList( $msgArray['to'] );
			$ccList += parseRecipientList( $msgArray['cc'] );
			$output = array();
			foreach ( $ccList as $index => $ccer ) {
				if ( $ccer['address'] == $compData['identity']['address'] ) {
					// Skip this address...
				} else {
					$output[] = $ccer;
				}
			}
			$compData['comp_cc'] .= htmlentities( formatRecipientList( $output ) );
		} else {
			$compData['comp_cc'] .= htmlentities( $msgArray['cc'] );
		}
	}
	if ( isset( $mailtoDetails['cc'] ) ) {
		$compData['comp_cc'] .= htmlentities( $msgArray['cc'] );
	}
	$compData['show_cc'] = !empty( $compData['comp_cc'] );

	// Determine BCC address(es), if we need them.
	$compData['comp_bcc'] = "";
	if ( isset( $msgArray['bcc'] ) && !empty( $msgArray['bcc'] ) ) {
		$compData['comp_bcc'] .= htmlentities( $msgArray['bcc'] );
	}
	if ( isset( $mailtoDetails['bcc'] ) ) {
		$compData['comp_bcc'] .= htmlentities( $mailtoDetails['bcc'] );
	}
	$compData['show_bcc'] = !empty( $compData['comp_bcc'] );

	// Determine the subject of the message.
	$compData['comp_subj'] = "";
	switch ($action) {
		case 'reply':
		case 'replyall':
			if ( stristr( $msgArray['subject'], _("Re:") ) === false ) {
				// No existing "re:" in the subject, so add one.
				$compData['comp_subj'] = _("Re:") . " " . $msgArray['subject'];
			} else {
				// Already got a "Re" in the subject.
				$compData['comp_subj'] = $msgArray['subject'];
			}
			break;
		case 'forwardinline':
		case 'forwardasattach':
			if ( stristr( $msgArray['subject'], _("Fwd:") ) === false ) {
				// No existing "fwd:" in the subject, so add one.
				$compData['comp_subj'] = _("Fwd:") . " ". $msgArray['subject'];
			} else {
				// Already got a "Fwd" in the subject.
				$compData['comp_subj'] = $msgArray['subject'];
			}
			break;
		case 'draft':
			$compData['comp_subj'] = $msgArray['subject'];
			break;
		case 'mailto':
			if ( isset( $mailtoDetails['subject'] ) ) {
				$compData['comp_subj'] = $mailtoDetails['subject'];
			}
			break;
	}
	$compData['comp_subj'] = htmlentities( $compData['comp_subj'] );

	// Determine the initial body of the message.
	$compData['comp_msg'] = "";
	switch ($action) {
		case 'reply':
		case 'replyall':
			// TODO: The date format below will be different depending on what time the message was replied to.
			$compData['comp_msg'] .= sprintf( _("At %s, %s wrote:\n"), $msgArray['localdate'], $msgArray['from'] );
			$compData['comp_msg'] .= markupQuotedMessage( $msgArray['texthtml'], 'text/html', 'reply' );
			$compData['comp_msg'] .= "\n";
			$compData['comp_msg'] .= markupQuotedMessage( $msgArray['textplain'], 'text/plain', 'reply' );
			break;
		case 'forwardinline':
			$compData['comp_msg'] .= "--- ". _("Forwarded message"). " ---\n";
			$compData['comp_msg'] .= _("From"). ": " . formatIMAPAddress( $msgArray['from'] ) . "\n";
			$compData['comp_msg'] .= _("To"). ": " . formatIMAPAddress( $msgArray['to'] ) . "\n";
			$compData['comp_msg'] .= _("Subject"). ": " . $msgArray['subject'] . "\n";
			$compData['comp_msg'] .= "\n";
			$compData['comp_msg'] .= markupQuotedMessage( $msgArray['texthtml'], 'text/html', 'forward' );
			$compData['comp_msg'] .= "\n";
			$compData['comp_msg'] .= markupQuotedMessage( $msgArray['textplain'], 'text/plain', 'forward' );
			break;
		case 'draft':
			$compData['comp_msg']  = implode( "", $msgArray['textplain'] );
			break;
		case 'mailto':
			if ( isset( $mailtoDetails['body'] ) ) {
				$compData['comp_msg'] = $mailtoDetails['body'];
			}
			break;
	}
	if ( $action != "draft" ) {
		$sigTest = trim( $compData['identity']['signature'] );
		if ( !empty( $sigTest ) ) {
			$compData['comp_msg'] .= "\n";
			$compData['comp_msg'] .= $compData['identity']['signature'];
		}
	}
	$compData['comp_msg'] = htmlentities( $compData['comp_msg'] );

	// Collect data on all the attachments.
	$compData['comp_attach'] = array();
	
	if ( $action == "forwardasattach" ) {
		// TODO: Lots of copied code from below - don't copy and paste!
		$userDir = getUserDirectory() . "/attachments";
		// TODO: What if subject is blank?
		$attachmentFilename = "{$msgArray['subject']}.eml";
		$attachmentFilename = str_replace( array( "/", "\\" ), array( "-", "-" ), $attachmentFilename );
		$serverFilename = hashifyFilename( $attachmentFilename );
		$attachmentHandle = fopen( "{$userDir}/{$serverFilename}", "w" );
		streamLargeAttachment( $IMAP_SERVER, $IMAP_PORT, $IS_SSL, $_SESSION['user'], $_SESSION['pass'],
			$mailbox, $uid, "LICHENSOURCE", $attachmentHandle );
		fclose( $attachmentHandle );

		$compData['comp_attach'][] = array(
			"filename" => $attachmentFilename,
			"type" => "message/rfc822",
			"size" => filesize( "{$userDir}/{$serverFilename}" ),
			"isforwardedmessage" => true
		);
	}

	if ( $action == "forwardinline" || $action == "forwardasattach" || $action == "draft" ) {
		// Save out the attachments to the users attachment dir.
		$userDir = getUserDirectory() . "/attachments";
		foreach ( $msgArray['attachments'] as $attachment ) {

			if ( $attachment['filename'] == "" ) continue; // Skip attachments that are inline-only.

			$serverFilename = hashifyFilename( $attachment['filename'] );
			$attachmentHandle = fopen( "{$userDir}/{$serverFilename}", "w" );
			streamLargeAttachment( $IMAP_SERVER, $IMAP_PORT, $IS_SSL, $_SESSION['user'], $_SESSION['pass'],
				$mailbox, $uid, $attachment['filename'], $attachmentHandle );
			fclose( $attachmentHandle );
			$compData['comp_attach'][] = array(
				"filename" => $attachment['filename'],
				"type" => $attachment['type'],
				"size" => filesize( "{$userDir}/{$serverFilename}" ),
				"isforwardedmessage" => false
			);
		}
	}

	$compData['maxattachmentsize'] = $UPLOAD_ATTACHMENT_MAX;

	return $compData;
}


?>

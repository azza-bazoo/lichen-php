<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
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


// Generate new message compose window (rather uglily using lots of globals!)
function generateMessageCompose() {
	global $mbox, $IMAP_CONNECT, $mailbox;
	global $IMAP_PORT, $IMAP_SERVER, $IS_SSL;
	global $SMTP_SERVER, $SMTP_PORT, $USER_SETTINGS;
	global $LICHEN_URL, $LICHEN_VERSION;
	global $SPECIAL_FOLDERS, $UPLOAD_ATTACHMENT_MAX;

	include ( 'libs/streamattach.php' );

	$message = null;
	if ( isset( $_POST['uid'] ) ) {
		// It's in reply/forward of...
		// Load that message.
		$msgNo = imap_msgno( $mbox, $_POST['uid'] );

		$msgArray = retrieveMessage( $msgNo, false );

		if ( $msgArray == null ) {
			die( remoteRequestFailure( 'COMPOSER', _("Error: cannot find message to reply to or forward.") ) );
		}
		$headerObj = imap_headerinfo( $mbox, $msgNo );
	}

	$mailtoDetails = array();
	if ( isset( $_POST['mailto'] ) ) {
		// Parse a string that was in an email.
		// Probably just a raw email address, but may be
		// something like "mailto:foo@bar.com?subject=Baz"
		$bits = explode( "?", $_POST['mailto'] );

		if ( count( $bits ) > 1 ) {
			// Parse the later part.
			parse_str( $bits[1], $mailtoDetails );
		}
		$mailtoDetails['email-to'] = $bits[0];
	}

	$action = "new";
	if ( isset( $_POST['mode'] ) ) {
		$action = $_POST['mode'];
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

	// Do we need to display the CC or BCC fields immediately?
	$showCC = false;
	if ( ( isset( $headerObj->cc  ) && count( $headerObj->cc ) > 0 &&
		( $action == "draft" || $action == "replyall" ) )
		|| isset( $mailtoDetails['cc'] ) ) {
			// Unhide the CC field if:
			// 1. editing a draft with CC.
			// 2. replying-to-all on a message.
			// 3. we were invoked from a mailto: link that had ?cc=foo
			// All other cases: don't show the CC field by default.
			$showCC = true;
	}

	// Show the BCC field if editing a draft, or if a mailto: link has ?bcc=foo
	$showBCC = false;
	if ( isset( $headerObj->bcc ) && count( $headerObj->bcc ) > 0 ) $showBCC = true;
	if ( isset( $mailtoDetails['bcc'] ) ) $showBCC = true;

	// Capture the output.
	ob_start();

	// Right-side float here is to prevent IE7 from collapsing the div
	echo "<div class=\"header-bar\"><img src=\"themes/{$USER_SETTINGS['theme']}/top-corner.png\" alt=\"\" class=\"top-corner\" /><div class=\"header-right\">&nbsp;</div><div class=\"comp-header\">New message</div></div>";

	echo "<form action=\"$LICHEN_URL\" method=\"post\" id=\"compose\" onsubmit=\"comp_send();return false\">";

	echo "<input type=\"hidden\" name=\"comp-mode\" id=\"comp-mode\" value=\"{$action}\" />";
	if ( isset( $_POST['uid'] ) ) {
		echo "<input type=\"hidden\" name=\"comp-quoteuid\" id=\"comp-quoteuid\" value=\"" . htmlentities( $_POST['uid'] ) . "\" />";
		echo "<input type=\"hidden\" name=\"comp-quotemailbox\" id=\"comp-quotemailbox\" value=\"" . htmlentities( $_POST['mailbox'] ) . "\" />";
	}
	echo "<input type=\"hidden\" name=\"comp-draftuid\" id=\"comp-draftuid\" value=\"";
	if ( $action == "draft" ) {
		echo htmlentities( $_POST['uid'] );
	}
	echo "\" />";

	// Build identity selector.
	if ( count( $USER_SETTINGS['identities'] ) == 1 ) {
		// Simple case: just display use the one identity - hidden form element..
		echo "<input name=\"comp-identity\" id=\"comp-identity\" type=\"hidden\" value=\"".
		       htmlentities( $USER_SETTINGS['identities'][0]['address'] ) ."\" />";
	} else {
		echo "<label class=\"comp-label\" for=\"comp-identity\">", _("From:"), "</label> <select name=\"comp-identity\" id=\"comp-identity\">";
		foreach ( $USER_SETTINGS['identities'] as $identity ) {
			echo "<option value=\"". htmlentities( $identity['address'] ). "\"";
			if ( $action == 'reply' || $action == 'replyall' ) {
				if ( stristr( formatIMAPAddress( $headerObj->to ), $identity['address'] ) !== FALSE ) {
					// Select this identity.
					echo " selected=\"selected\"";
				}
			} else if ( $action == "draft" ) {
				if ( stristr( formatIMAPAddress( $headerObj->from ), $identity['address'] ) !== FALSE ) {
					// Select this identity.
					echo " selected=\"selected\"";
				}
			} else if ( $identity['isdefault'] ) {
				echo " selected=\"selected\"";
			}
			echo ">" . htmlentities( $identity['name'] ) .
			       " &lt;" . htmlentities( $identity['address'] ) . "&gt;</option>";
		}
		echo "</select>";
	}

	// Build to To: area, including buttons to display CC and BCC fields
	echo "<div class=\"comp-label\"><label for=\"comp-to\">", _("To:"), "</label>";

	echo "<p class=\"comp-add-fields\"><a id=\"comp-ccshow\" href=\"#\"" . ( $showCC ? " style=\"display:none\"" : "" ) . " onclick=\"$('comp-cceditor').style.display='block';$('comp-ccshow').style.display='none';return false\">", _("add CC"), "</a>";
	echo " <a id=\"comp-bccshow\" href=\"#\"". ( $showBCC ? " style=\"display:none\"" : "" ) . " onclick=\"$('comp-bcceditor').style.display='block';$('comp-bccshow').style.display='none';return false\">", _("add BCC"), "</a></p>";

	echo "</div> <textarea name=\"comp-to\" id=\"comp-to\">";
	switch ($action) {
		case 'reply':
			// TODO: parse headers properly earlier, see above, also support multiple CCs
			if ( isset( $headerObj->reply_to ) ) {
				echo htmlentities( formatIMAPAddress( $headerObj->reply_to ) );
			} else {
				echo htmlentities( formatIMAPAddress( $headerObj->from ) );
			}
			break;
		case 'replyall':
			// TODO: pick the right header
			if ( isset( $headerObj->reply_to ) ) {
				echo htmlentities( formatIMAPAddress( $headerObj->reply_to ) );
			} else {
				echo htmlentities( formatIMAPAddress( $headerObj->from ) );
			}
			break;
		case 'draft':
			echo htmlentities( formatIMAPAddress( $headerObj->to ) );
			break;
		case 'mailto':
			echo htmlentities( $mailtoDetails['email-to'] );
			break;
	}
	echo "</textarea>";

	echo "<div id=\"comp-cceditor\" style=\"display: ". ( $showCC ? "block" : "none" ) .";\">";
	echo "<label class=\"comp-label\" for=\"comp-cc\">", _("CC:"), "</label> <textarea name=\"comp-cc\" id=\"comp-cc\">";
	if ( $showCC && isset( $headerObj ) && isset( $headerObj->cc ) ) {
		echo htmlentities( formatIMAPAddress( $headerObj->cc ) );
	}
	if ( isset( $mailtoDetails['cc'] ) ) {
		echo htmlentities( $mailtoDetails['cc'] );
	}
	echo "</textarea></div>";

	echo "<div id=\"comp-bcceditor\" style=\"display: ". ( $showBCC ? "block" : "none" ). ";\">";
	echo "<label class=\"comp-label\" for=\"comp-bcc\">", _("BCC:"), "</label> <textarea name=\"comp-bcc\" id=\"comp-bcc\">";
	if ( $showBCC && isset( $headerObj ) && isset( $headerObj->bcc ) ) {
		echo htmlentities( formatIMAPAddress( $headerObj->bcc ) );
	}
	if ( isset( $mailtoDetails['bcc'] ) ) {
		echo htmlentities( $mailtoDetails['bcc'] );
	}
	echo "</textarea></div>";

	// Build the subject area.
	echo "<label class=\"comp-label\" for=\"comp-subj\">", _("Subject:"), "</label> <input type=\"text\" name=\"comp-subj\" id=\"comp-subj\" value=\"";
	switch ($action) {
		case 'reply':
		case 'replyall':
			echo htmlentities( _("Re:") . " " . $headerObj->subject );
			break;
		case 'forwardinline':
		case 'forwardasattach':
			echo htmlentities( _("Fwd:") . " ". $headerObj->subject );
			break;
		case 'draft':
			echo htmlentities( $headerObj->subject );
			break;
		case 'mailto':
			if ( isset( $mailtoDetails['subject'] ) ) {
				echo htmlentities( $mailtoDetails['subject'] );
			}
			break;
	}
       	echo "\" />";

	// Build the text area. Text only at the moment.
	echo "<textarea name=\"comp-msg\" id=\"comp-msg\">";
	switch ($action) {
		case 'reply':
		case 'replyall':
			// Insert the text of the message.
			// TODO: Handle HTML properly.
			// TODO: insert ">" characters into reply.
			echo htmlentities( trim( strip_tags( implode("", $msgArray['text/html'] ) ) ) );
			// HACK: To insert ">" characters into reply.
			function quoteReply( $line ) {
				return "> {$line}";
			}
			echo htmlentities( implode( "\n", array_map( "quoteReply", explode( "\n", implode( "", $msgArray['text/plain'] ) ) ) ) );
			break;
		case 'forwardinline':
			echo "--- ", _("Forwarded message"), " ---\n";
			echo _("From"), ": " . formatIMAPAddress( $headerObj->from ) . "\n";
			echo _("To"), ": " . formatIMAPAddress( $headerObj->to ) . "\n";
			// TODO: CC as well.
			echo _("Subject"), ": " . $headerObj->subject  . "\n";
			echo "\n";
			echo htmlentities( trim( strip_tags( implode( "", $msgArray['text/html'] ) ) ) );
			echo htmlentities( implode( "", $msgArray['text/plain'] ) );
			break;
		case 'draft':
			echo htmlentities( implode( "", $msgArray['text/plain'] ) );
			break;
		case 'mailto':
			if ( isset( $mailtoDetails['body'] ) ) {
				echo htmlentities( $mailtoDetails['body'] );
			}
	}
	echo "</textarea>";

	if ( $action == "forwardinline" ) {
		// If we have an inline-forwarded message, provide a link to forward as attachment instead.
		// TODO: If the user clicks this, it WILL NOT preserve the message content or anything!
		echo "<p><a href=\"#\" onclick=\"comp_showForm('forwardasattach',lastShownUID); return false\">", _("&raquo; forward message as attachment"), "</a></p>";
	}

	echo "</form>";

	echo "<div class=\"sidebar-panel\" id=\"comp-attachments\">";
	echo "<h2 class=\"sidebar-head\"><img src=\"themes/{$USER_SETTINGS['theme']}/icons/attach.png\" alt=\"\" /> attachments</h2>";

	// Build the attachments list...
	echo "<ul id=\"comp-attachlist\">";

	if ( $action == "forwardinline" || $action == "forwardasattach" || $action == "draft" ) {
		// Save out the attachments to the users attachment dir.
		$userDir = getUserDirectory() . "/attachments";
		foreach ( $msgArray['attachments'] as $attachment ) {

			if ( $attachment['filename'] == "" ) continue; // Skip attachments that are inline-only.

			$serverFilename = hashifyFilename( $attachment['filename'] );
			$attachmentHandle = fopen( "{$userDir}/{$serverFilename}", "w" );
			streamLargeAttachment( $IMAP_SERVER, $IMAP_PORT, $IS_SSL, $_SESSION['user'], $_SESSION['pass'],
				$mailbox, $_POST['uid'], $attachment['filename'], $attachmentHandle );
			fclose( $attachmentHandle );
			echo "<li>{$attachment['filename']} ({$attachment['type']}, {$attachment['size']})";

			echo " <a href=\"#\" onclick=\"comp_removeAttachment('".addslashes($attachment['filename'])."');return false\">", _("[remove]"), "</a></li>";

			echo "<input type=\"hidden\" name=\"comp-attach[]\" value=\"" . htmlentities( $attachment['filename'] ) . "\" />";
		}
	}

	if ( $action == "forwardasattach" ) {
		// TODO: Lots of copied code from above - don't copy and paste!
		$userDir = getUserDirectory() . "/attachments";
		$attachmentFilename = "{$headerObj->subject}.eml";
		$attachmentFilename = str_replace( array( "/", "\\" ), array( "-", "-" ), $attachmentFilename );
		$serverFilename = hashifyFilename( $attachmentFilename );
		$attachmentHandle = fopen( "{$userDir}/{$serverFilename}", "w" );
		streamLargeAttachment( $IMAP_SERVER, $IMAP_PORT, $IS_SSL, $_SESSION['user'], $_SESSION['pass'],
			$mailbox, $_POST['uid'], "LICHENSOURCE", $attachmentHandle );
		fclose( $attachmentHandle );

		echo "<li>{$attachmentFilename} (message/rfc822, " . formatNumberBytes( filesize( "{$userDir}/{$serverFilename}" ) ) . ")";
//		echo "<a href=\"#\" onclick=\"comp_removeAttachment('".addslashes($attachmentFilename)."');return false\">", _("[remove]"), "</a>";

		// TODO: this resets any data entered!
		echo " <a href=\"#\" onclick=\"comp_showForm('forwardinline',lastShownUID); return false\">", _("[forward inline]"), "</a>";
		echo "</li>";

		echo "<input type=\"hidden\" name=\"comp-attach[]\" value=\"" . htmlentities( $attachmentFilename ) . "\" />";
	}

	echo "</ul>";

	echo "<form enctype=\"multipart/form-data\" action=\"ajax.php\" id=\"comp-uploadform\" method=\"post\" onsubmit=\"return asyncUploadFile($('comp-uploadform'))\">";
	echo "<input type=\"hidden\" name=\"request\" id=\"request\" value=\"uploadAttachment\" />";
	echo "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"{$UPLOAD_ATTACHMENT_MAX}\" />";
	echo "<label for=\"comp-attachfile\">", _("add new"), "</label><br />";
	echo "<input type=\"file\" name=\"comp-attachfile\" id=\"comp-attachfile\" />";
	echo "<div class=\"comp-attach-submit\"><input type=\"submit\" value=\"", _("upload file"), "\" /></div>";
	echo "<input type=\"hidden\" name=\"upattach\" value=\"1\" />";
	echo "</form></div>";

	return ob_get_clean();
}


?>

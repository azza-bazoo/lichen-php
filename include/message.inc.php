<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
include/message.inc.php - functions for retrieving messages
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


// Retrieves the given message number and processes MIME (if present), and
// return an array containing the text and HTML parts as well as a list of
// attachments. Also returns header metadata - eg, subject, from, to, etc.
// If in preview mode, doesn't return the header metadata - because you've probably
// already got that data!
// TODO: support $preferredType ...?
function retrieveMessage( $msgUid, $preview=false ) {	// $preferredType='plain',
	global $mbox, $mailbox, $IMAP_CONNECT;
	global $DATE_FORMAT_MSG;

	$processedResult = array();

	$msgNo = imap_msgno( $mbox, $msgUid );

	// Fetch the MIME structure of this message (also works for non-MIME messages)
	$msgStruct = imap_fetchstructure( $mbox, $msgNo );

	if ( $msgStruct === false ) {
		// Invalid message number, so return NULL.
		return null;
	}

	if ( $preview == false ) {
		// ----------------------------------------------------------------
		// HEADER EXTRACTION
		// Fetch details about the message - subject, from, to, etc.
		$headerObj = imap_headerinfo( $mbox, $msgNo );

		if ( $headerObj === false ) {
			// Non existant message...
			return null;
		}

		if ( isset( $headerObj->from ) ) {
			$processedResult['from']    = filterHeader( formatIMAPAddress( $headerObj->from ), false );
		}
		if ( isset( $headerObj->to ) ) {
			$processedResult['to']      = filterHeader( formatIMAPAddress( $headerObj->to ), false );
		}
		if ( isset( $headerObj->cc ) ) {
			$processedResult['cc']      = filterHeader( formatIMAPAddress( $headerObj->cc ), false );
		}
		if ( isset( $headerObj->bcc ) ) {
			$processedResult['bcc']     = filterHeader( formatIMAPAddress( $headerObj->bcc ), false );
		}
		if ( isset( $headerObj->replyto ) ) {
			$processedResult['replyto'] = filterHeader( formatIMAPAddress( $headerObj->reply_to ), false );
		}
		if ( isset( $headerObj->sender ) ) {
			$processedResult['sender']  = filterHeader( formatIMAPAddress( $headerObj->sender ), false );
			$processedResult['sender_wasme'] = false;
			if ( getUserIdentity( $processedResult['sender'], true ) != null ) {
				$processedResult['sender_wasme'] = true;
			}
		}
		$processedResult['mailbox'] = $mailbox;
		$processedResult['uid']     = $msgUid;
	
		$subject = "(no subject)";
		if ( isset( $headerObj->subject ) ) {
			$subject = filterHeader( $headerObj->subject, false );
		}

		$processedResult['subject'] = $subject;

		// TODO: Date should be formatted elsewhere?
		if ( isset( $headerObj->date ) ) {
			$processedResult['localdate'] = processDate( $headerObj->date, $DATE_FORMAT_MSG );
		}

		// Create HTML encoded versions of all of these keys, in case they have special characters.
		// These are stored alongside. This is useful for code that displays the results of this -
		// for example, the JavaScript, for which it would be computationaly expensive to try and
		// insert entities for.
		foreach ( array_keys( $processedResult ) as $key ) {
			if ( is_string( $processedResult[$key] ) ) {
				$processedResult["{$key}_html"] = htmlentities( $processedResult[$key] );
			}
		}
	}

	// ----------------------------------------------------------------
	// CONTENT EXTRACTION
	// Is this a multi-part message?
	if ( isset( $msgStruct->parts ) ) {

		// Separate the different MIME parts into the three types we recognise.
		$processedResult = array_merge( $processedResult, separateMsgParts( $msgStruct->parts, $msgNo, '', $preview ) );

	} else {

		// Use our MIME-separation function but feed it an array with
		// the whole message's structure (so it loops once)
		$processedResult = array_merge( $processedResult, separateMsgParts( array( $msgStruct ), $msgNo, '', $preview ) );

	}
	
	// Change the key "text/html" and "text/plain" into "texthtml" and "textplain", so that
	// the array can be safely JSONified.
	// TODO: Test that this works on PHP4.
	// We use a reference here, so that we don't copy the data - we unset the original, which
	// removes one reference, keeping the data.
	$processedResult["texthtml"] = &$processedResult["text/html"];
	$processedResult["textplain"] = &$processedResult["text/plain"];
	unset( $processedResult["text/html"] );
	unset( $processedResult["text/plain"] );

	// ----------------------------------------------------------------
	// FINAL CONTENT FLAGS
	// Determine what we have.
	if ( count( $processedResult['texthtml'] ) > 0 ) {
		$processedResult['texthtmlpresent'] = true;
	} else {
		$processedResult['texthtmlpresent'] = false;
	}
	if ( count( $processedResult['textplain'] ) > 0 ) {
		$processedResult['textplainpresent'] = true;
	} else {
		$processedResult['textplainpresent'] = false;
	}

	return $processedResult;
}


// Given a message structure object produced by imap_fetchstructure,
// loop over each of the message parts and return them in an array
// of HTML, text, and other parts.
// TODO: clean up this code.
function separateMsgParts( $partsObject, $msgNo, $partPrefix, $preview=false ) {
	global $mbox;

	$separatedParts = array();
	$separatedParts['text/html'] = array();
	$separatedParts['text/plain'] = array();
	$separatedParts['attachments'] = array();

	// PHP's imap_fetchstructure has funny codes for file types.
	// TODO: find out why the last two 'other's are necessary;
	// they pop up in the UW MIME torture tests.
	$phpMimeTypeCodes = array( 'text', 'multipart', 'message', 'application',
					'audio', 'image', 'video', 'other', 'other', 'other' );
	$phpMimeEncodingCodes = array( '7bit', '8bit', 'binary', 'base64',
					'quoted-printable', 'other', 'utf-8' ); // ?? 6 = utf-8??

	// Deal with each part in turn.
	foreach ( $partsObject as $partNo => $thisPart ) {

		// Get metadata: content type, transfer encoding, character set.
		$contentType = $phpMimeTypeCodes[ $thisPart->type ] . '/' . strtolower( $thisPart->subtype );
		$transferEncoding = $phpMimeEncodingCodes[ $thisPart->encoding ];

		// The PHP-IMAP method of recording character set kinda sucks.
		$charset = "";
		foreach ( $thisPart->parameters as $thisParam ) {
			if ( $thisParam->attribute == "charset" ) {
				$charset = $thisParam->value;
			}
		}

		// Temporary hack: so that we display returned failure messages
		// correctly, treat delivery status parts as plain text.
		if ( $contentType == "message/delivery-status" ) {
			$contentType = "text/plain";
		}

		// Another temporary hack: calculate the approximate size of this
		// part, assuming that it's base64 encoded. This is used to avoid
		// processing text parts that'd trip the PHP memory limit, and to
		// give an indication of size in the attachments list.
		// TODO: Instead of an approximation, get the real size...
		$approxSize = 0;
		if ( isset( $thisPart->bytes ) ) {
			$approxSize = floor( 0.741 * $thisPart->bytes );
		}

		if ( $contentType == "text/plain" || $contentType == "text/html" ) {
			if ( $approxSize > 524288 ) { // 512 kilobytes
				// It's a little big to include directly.
				// TODO: we need to stream it out...
				$fullPartNo = "{$partPrefix}" . ($partNo + 1);
				$separatedParts[ $contentType ][] = "LICHENTOOLARGE({$fullPartNo})" . _("[error: message part too large to display]");
			} else {
				// Fetch the content of this part and add to the return array.
				if ( $preview ) {
					$partContents = imap_fetchbody( $mbox, $msgNo, $partPrefix.($partNo+1), FT_PEEK );
				} else {
					$partContents = imap_fetchbody( $mbox, $msgNo, $partPrefix.($partNo+1) );
				}

				$separatedParts[ $contentType ][] = decodeText( $partContents, $charset, $transferEncoding );
			}

		} elseif ( $thisPart->type == 1 || $contentType == "message/rfc822" ) {
			// This part is another message embedded within the
			// main message, so we must recurse.
			$subParts = separateMsgParts( $thisPart->parts, $msgNo, $partPrefix.($partNo+1).'.', $preview );
			$separatedParts['text/html'] = array_merge( $separatedParts['text/html'], $subParts['text/html'] );
			$separatedParts['text/plain'] = array_merge( $separatedParts['text/plain'], $subParts['text/plain'] );
			$separatedParts['attachments'] = array_merge( $separatedParts['attachments'], $subParts['attachments'] );

		} else {
			// This is probably an attachment; fetch its filename.
			// If the filename is long, PHP splits it into multiple headers
			// of the form "filename*N" where N is part number
			$filename = array();
			if ( isset( $thisPart->dparameters ) ) {
				foreach ( $thisPart->dparameters as $thisParam ) {
					if ( substr( $thisParam->attribute, 0, 8 ) == "filename" ) {
						$fileBit = 0;
						if ( strpos( $thisParam->attribute, "*" ) ) {
							// Multiple parts of a filename. This one is...
							$fileBit = substr( $thisParam->attribute, 9 ) + 0;
						}
						$filename[$fileBit] = $thisParam->value;
					}
				}
			}
			$filename = implode( '', $filename );
			$id = "";
			if ( isset( $thisPart->id ) ) {
				$id = $thisPart->id;
				if ( $id[0] == "<" ) {
					$id = substr( $id, 1, -1 );
				}
			}

			// In some (all?) cases, UW-IMAP doesn't return an attachment file name.
			// mt_rand here is because $filename is used to identify attachments
			// TODO: this requires more detailed investigation
			if ( $filename == "" ) { $filename = _("(unnamed attachment)") . "-" . mt_rand(0,10000); }

			$separatedParts['attachments'][] = array(
				"type" => $contentType,
				"encoding" => $transferEncoding,
				"filename" => $filename,
				"size" => $approxSize,
				"part" => $partPrefix.($partNo+1),
				"id" => $id
			);
		}
	}

	return $separatedParts;
}

?>

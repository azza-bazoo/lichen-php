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
// attachments and the unmodified headers.
// TODO: support $preferredType ...?
function retrieveMessage( $msgNo, $preview=false ) {	// $preferredType='plain',
	global $mbox, $mailbox, $IMAP_CONNECT;

	$processedResult = array();

	// Fetch the MIME structure of this message (also works for non-MIME messages)
	$msgStruct = imap_fetchstructure( $mbox, $msgNo );

	if ( $msgStruct === false ) {
		// Invalid message number.
		// Inform upstream by returning NULL.
		return null;
	}

	// Is this a multi-part message?
	if ( isset( $msgStruct->parts ) ) {

		// Separate the different MIME parts into the three types we recognise.
		$processedResult = separateMsgParts( $msgStruct->parts, $msgNo, '', $preview );

	} else {

/*		if ( $preview ) {
			$message = imap_body( $mbox, $msgUID, FT_UID + FT_PEEK );
		} else {
			$message = imap_body( $mbox, $msgUID, FT_UID );
		}

		// Although this isn't a MIME message, fetchstructure should
		// still have given us information about it.
		$_contentType = $phpMimeTypeCodes[ $msgStruct->type ] . '/' . strtolower( $msgStruct->subtype );
		$_transferEncoding = $phpMimeEncodingCodes[ $msgStruct->encoding ];

		$charset = "";
		foreach ( $msgStruct->parameters as $thisParam ) {
			if ( $thisParam->attribute == "charset" ) {
				$_charset = $thisParam->value;
			}
		}

		$processedResult['text/html'] = array();
		$separatedParts['attachments'] = array();
		$processedResult['text/plain'] = decodeText( $message, $charset, $transferEncoding ); */

		// Use our MIME-separation function but feed it an array with
		// the whole message's structure (so it loops once)
		$processedResult = separateMsgParts( array( $msgStruct ), $msgNo, '', $preview );

	}

	// Record the full headers of this message and its mailbox name.
	// TODO: we shouldn't need either.
//	if ( $preview ) {
//		$processedResult['headers'] = imap_fetchheader( $mbox, $msgUID, FT_PEEK + FT_UID );
//	} else {
//		$processedResult['headers'] = imap_fetchheader( $mbox, $msgUID, FT_UID );
//	}
//	$processedResult['mailbox'] = $mailbox;

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
					'quoted-printable', 'other' );

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
			if ( $approxSize > 500000 ) { // 500,000 bytes
				// It's a little big to include directly.
				// We need to stream it out...
				$separatedParts[ $contentType ][] = _("This part is too large to incorporate directly. Bug the developers to fix this.");
			} else {
				// Fetch the content of this part and add to the return array.
				if ( $preview ) {
					$partContents = imap_fetchbody( $mbox, $msgNo, $partPrefix.($partNo+1), FT_PEEK );
				} else {
					$partContents = imap_fetchbody( $mbox, $msgNo, $partPrefix.($partNo+1) );
				}

				// Not true: we use peek-only here because the caller function retrieveMessage
				// will (if needed) mark the message as read when fetching headers.

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
			// The GOTCHA here is that filename is split into multiple headers
			// if it is too long - each header is "filename*N" in this case,
			// where N is the number of the part, to assist reassembly.
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
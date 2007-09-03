<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
libs/streamattach.php

This file contains a mini implementation of IMAP, to solve two problems
in Lichen that can't be solved with the PHP IMAP libraries:
1. getting attachments larger than PHP's memory limit.
2. saving messages (i.e. drafts, sent mail) that are larger than
PHP's memory limit.
It was built by looking at RFC3501 and SquirrelMail's code.

TODO: This is filled with gaping holes, and user input is not
checked very much at all.

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

// Generate a UID for IMAP commands.
function imapGetUID() {
	static $lastUID = 1;

	$uid = sprintf("U%03d", $lastUID);
	$lastUID++;

	return $uid;
}

// Basic quote parameter for IMAP...
// TODO: Make this better.
function quoteIMAP( $string ) {
	$string = str_replace( "\"", "\\\"", $string );
	$string = str_replace( "\\", "\\\\", $string );
	return $string;
}

// This function waits for the next line from the server,
// and then parses it into the component parts.
function imapGetResponse( $imap_stream, $uid ) {

	$otherLines = array();
	$result = array();

	while (1) {
		$response = fgets( $imap_stream );

		$response = trim( $response );

		/* imapDebugLog("Server line: {$response}"); */

		// Result line is in format
		//  UNNN OK Message
		// First, is this a result line?
		if ( substr( $response, 0, 4 ) == $uid ) {
			$result['uid'] = $uid;
			$response = substr( $response, 5 );
			$result['message'] = trim( strstr( $response, " " ) );
			$result['code'] = trim( substr( $response, 0, strlen( $response ) - strlen( $result['message'] ) ) );
			break;
		} else {
			// Add this line to the other response
			// lines.
			$otherLines[] = $response;
		}
	}

	$result['otherlines'] = $otherLines;
	return $result;
}

// Select an IMAP mailbox.
// Returns NULL on success, or an error message on failure.
function imapSelectMailbox( $imap_stream, $mailbox ) {

	$selectUID = imapGetUID();
	$result = fwrite( $imap_stream, sprintf( "%s SELECT \"%s\"\r\n", $selectUID, quoteIMAP( $mailbox ) ) );

	if ( $result === FALSE ) {
		return _("Stream error sending command to IMAP server.");
	}

	$serverResponse = imapGetResponse( $imap_stream, $selectUID );

	if ( $serverResponse['code'] != "OK" ) {
		return _("Unable to select mailbox: ") . $serverResponse['message'];
	}

	return NULL;
}

/*
$debugFD = NULL;
function imapDebugLog( $string ) {
	global $debugFD;

	if ( $debugFD == NULL ) {
		$debugFD = fopen("debug.txt", "a");
		fwrite( $debugFD, "---\n" );
	}

	fwrite( $debugFD, $string . "\n" );
}
 */

// Connect to the given IMAP server and login.
// Returns a socket stream if successful, or an error message on failure.
// Check the return value with is_resource().
function imapConnectAndLogin( $server, $port, $ssl, $username, $password ) {

	// Open our socket connection to the server.
	if ( $ssl ) $server = "tls://{$server}";

	$errorNumber = 0;
	$errorString = "";
	$imap_stream = fsockopen( $server, $port, $errorNumber, $errorString );

	if ( $imap_stream === FALSE || $errorNumber != 0 ) {
		if ( $errorString != "" ) return $errorString;
		return _("Unable to connect to server - unknown error.");
	}

	// imapDebugLog("Connected to server.");

	// Read the initial response, and check that we're good.
	$response = fgets( $imap_stream );

	if ( strstr( $response, "OK" ) === FALSE ) {
		// Hmm. Something went wrong.
		return _("Server did not respond when we opened the connection.");
	}

	// imapDebugLog("Server has responded.");

	// Login to the server.
	$loginUID = imapGetUID();
	// TODO: Support things other than 'LOGIN'.
	$result = fwrite( $imap_stream, sprintf( "%s LOGIN \"%s\" \"%s\"\r\n", $loginUID, quoteIMAP( $username ), quoteIMAP( $password ) ) );

	if ( $result === FALSE ) {
		return _("Stream error logging into IMAP server.");
	}

	// imapDebugLog("Sent login request.");

	// Await a suitable response from the server.
	$serverResponse = imapGetResponse( $imap_stream, $loginUID );

	if ( $serverResponse['code'] != "OK" ) {
		return _("Unable to login - server said ") . $serverResponse['message'];
	}

	// imapDebugLog("Logged in to server.");

	return $imap_stream;
}

// Logs out and closes the IMAP connection.
// Returns NULL on success, error string on failure.
function imapLogout( $imap_stream ) {
	$logoutUID = imapGetUID();

	$result = fwrite( $imap_stream, sprintf( "%s LOGOUT\r\n", $logoutUID ) );

	if ( $result === FALSE ) {
		return _("Unable to write to IMAP server.");
	}

	$serverResponse = imapGetResponse( $imap_stream, $logoutUID );

	if ( $serverResponse['code'] != "OK" ) {
		return _("Unable to logout - the server said ") . $serverResponse['message'];
	}

	fclose( $imap_stream );

	return NULL;
}

// Stream a particular part of a message.
// Return error string on failure, NULL on success.
function imapStreamMessagePart( $imap_stream, $uid, $messagePart = "", $decoding = "", $outputStream = NULL ) {

	$streamUID = imapGetUID();

	$result = fwrite( $imap_stream, sprintf( "%s UID FETCH %s BODY[%s]\r\n", $streamUID, $uid, $messagePart ) );

	if ( $result === FALSE ) {
		return _("Unable to write to IMAP server.");
	}

	// Determine the stream decoder.
	if ( $decoding == 'base64' ) {
		$decoding = 1;
	} else {
		$decoding = 0;
	}

	// Now that we've sent that down, we expect this back:
	// * NN FETCH (UID NNN BODY[NNN] {NNNN}\r\n
	// ... Contents of part ...
	// U003 OK Fetch completed.
	// On the first line, the value in {} is the size of the part.

	$details = fgets( $imap_stream );
	//echo "Server said: {$details}";
	if ( $details[0] == "*" ) {
		$details = trim( $details );
		$size = strstr( $details, "{" );
		$size = substr( $size, 1, -1 );
		$size = $size + 0;

		//echo "We got size: {$size}\n";

		$retrievedSize = 0;
		$thisSize = 0;
		$chunkSize = 7800;
		$remainderData = "";

		while ( $retrievedSize < $size ) {

			// If the remainder is less than the chunk size, only
			// try to read that much back - otherwise we'll read
			// the IMAP servers result code, which will corrupt our stream.
			if ( ( $size - $retrievedSize ) < $chunkSize ) {
				$chunkSize = $size - $retrievedSize;
			}

			$data = fread( $imap_stream, $chunkSize );

			if ( $data === FALSE ) {
				return _("Error reading from server.");
			}

			// This is going to be slow.
			$thisSize = strlen( $data );
			$retrievedSize += $thisSize;

			//echo "Asked for {$chunkSize} bytes, fetched {$thisSize} bytes, got {$retrievedSize} total...\n";

			// If we had data left over from the last decode run
			// (eg, extra base64 data), put it back in for this run.
			$data = $remainderData . $data;

			if ( $decoding == 0 ) {
				// No decoding.
			} else if ( $decoding == 1 ) {
				// Base64 decoding. Heavily based on Squirrelmail's stream decoding.
				// Strip newlines and spaces.
				$data = str_replace( array("\r\n", "\n", "\r", " "), array('', '', '', ''), $data );

				// Is the string a multiple of 4?
				$remainder = strlen( $data ) % 4;
				$remainderData = "";
				if ( $remainder ) {
					// Calculate the remainder data.
					$remainderData = substr( $data, -$remainder );
					if ( substr( $remainderData, -1 ) != "=" ) {
						// Remainder data contains padding chars.
						// So remove remainder chars from the data. ?
						$data = substr( $data, 0, -$remainder );
					} else {
						// Remainder data has padding, clear it.
						$remainderData = "";
					}
				}

				//echo "Size of chunk: " . strlen( $data ) . " Remainder: {$remainderData} \n";
				//echo "About to decode: {$data}\n";

				$data = base64_decode( $data );
			}

			if ( $outputStream == NULL ) {
				// Dump directly to browser.
				echo $data;
			} else {
				// Write it to the given stream.
				fwrite( $outputStream, $data );
			}
		}

		// Now fetch the last line of the response.
		$serverResponse = imapGetResponse( $imap_stream, $streamUID );

		if ( $serverResponse['code'] != "OK" ) {
			return _("Error fetching message: ") . $serverResponse['message'];
		}
	} else {
		// Error of some kind.
		// TODO: Clean up this message.
		return _("Error fetching part: ") . $details;
	}

	return NULL;
}

// Prepare to append data.
// Returns error message on failure, or NULL on success.
function imapAppendBegin( $imap_stream, &$uid, $mailbox, $contentSize, $extraFlags = "" ) {

	$appendUID = imapGetUID();
	$uid = $appendUID;

	$result = fwrite( $imap_stream, sprintf( "%s APPEND \"%s\" (\Seen %s) {%s}\r\n", $appendUID, quoteIMAP( $mailbox ), $extraFlags, $contentSize ) );

	if ( $result === FALSE ) {
		return _("Error writing to IMAP server.");
	}

	$response = fgets( $imap_stream );

	// imapDebugLog("Got response from server: {$response} which is " . gettype($response));

	if ( $response[0] != "+" ) {
		// TODO: Better error message.
		return _("Error beginning append: ") . $response;
	}

	// The server is now ready and waiting. Write your data now!
	return NULL;
}

// Returns NULL if all was ok, or error message if not.
function imapAppendComplete( $imap_stream, $uid ) {

	fwrite( $imap_stream, "\r\n" );

	// imapDebugLog("Writing complete, awaiting final response from server.");

	$serverResponse = imapGetResponse( $imap_stream, $uid );

	if ( $serverResponse['code'] != "OK" ) {
		return $serverResponse['message'];
	}

	return NULL;
}

// Stream an attachment back to the user.
// This creates a seperate connection to the IMAP server to stream back data, so it can
// handle attachments of any size.
// Set filename to "LICHENSOURCE" to stream the entire message's source.
// Pass a valid resource that can be fwrite()'d as outputStream if you need to save it to a local file.
// Returns NULL on success, error string on failure.
function streamLargeAttachment($server, $port, $usessl, $user, $pass, $mailbox, $uid, $filename, $outputStream = NULL) {
	global $mbox;

	$getMessageSource = FALSE;
	if ( $filename == "LICHENSOURCE" ) $getMessageSource = TRUE;

	$contentType = "application/octet-stream";
	$encoding = "";
	$found = FALSE;
	$partToExtract = "";

	$searchCID = "";
	if ( substr( $filename, 0, 4 ) == "cid:" ) {
		$searchCID = substr( $filename, 4 );
	}
	$realFilename = "";
	$searchPart = "";
	if ( substr( $filename, 0, 5 ) == "part:" ) {
		$searchPart = substr( $filename, 5 );
		$getMessageSource = TRUE;
	}

	if ( !$getMessageSource ) {
		// NOT grabbing the source - find the part that has the filename that we want.
		$phpMimeTypeCodes = array( '0' => 'text', '1' => 'multipart', '2' => 'message',
			'3' => 'application', '4' => 'audio', '5' => 'image',
			'6' => 'video', '7' => 'other' );
		$phpMimeEncodingCodes = array( '0' => '7bit', '1' => '8bit', '2' => 'binary',
				'3' => 'base64', '4' => 'quoted-printable', '5' => 'other' );

		// Using PHP's IMAP libraries, find the section in question.
		$messageParts = retrieveMessage( $uid, true );

		foreach ( $messageParts['attachments'] as $attachment ) {
			if ( $attachment['filename'] == $filename || ( $searchCID != "" && $searchCID == $attachment['id'] ) ) {
				$partToExtract = $attachment['part'];
				$contentType = $attachment['type'];
				$encoding = $attachment['encoding'];
				$realFilename = $attachment['filename'];
				$found = TRUE;
			}
		}

		if ( !$found ) {
			return _("Error, filename not found in this message.");
		}
	} else {
		// We are grabbing the source - set up the capture for this.
		$contentType = "text/plain";
		if ( $searchPart == "" ) {
			$partToExtract = FALSE;
		} else {
			$partToExtract = $searchPart;
		}
		$encoding = "none";
	}

	// Make the connection to the server.
	$imap_stream = imapConnectAndLogin( $server, $port, $usessl, $user, $pass );
	if ( !is_resource( $imap_stream ) ) {
		return $imap_stream;
	}

	$result = imapSelectMailbox( $imap_stream, $mailbox );
	if ( $result != NULL) return $result;

	if ( $outputStream == NULL ) {
		header( "Content-Type: " . $contentType );

		if ( !$getMessageSource ) {
			if ( $searchCID == "" ) {
				// Tell the browser to save it.
				header( "Content-Disposition: attachment; filename=\"" . $filename . "\"" );
				// TODO: Content length. ?? Hard one - can only estimate the size.
			}
			// Else we want it inline.
		}
	}

	$result = imapStreamMessagePart( $imap_stream, $uid, $partToExtract, $encoding, $outputStream );

	if ( $result != NULL ) return $result;

	imapLogout( $imap_stream );
}

function streamSaveMessage($server, $port, $usessl, $user, $pass, $mailbox, $swift_ioobject, $contentSize, $extraFlags) {
	global $mbox;

	// Make the connection to the server.
	$imap_stream = imapConnectAndLogin( $server, $port, $usessl, $user, $pass );
	if ( !is_resource( $imap_stream ) ) {
		return $imap_stream;
	}

	// Start the append command.
	$appendUID = "";
	$result = imapAppendBegin( $imap_stream, $appendUID, $mailbox, $contentSize, $extraFlags );
	if ( $result != NULL ) return $result;

	// Write the data to the server.
	while ( $data = $swift_ioobject->read( 8192 ) ) {
		/* imapDebugLog( "Wrote " . strlen( $data ) . " bytes." ); */
		fwrite( $imap_stream, $data );
	}

	// Finish off the append command.
	$result = imapAppendComplete( $imap_stream, $appendUID );

	if ( $result != NULL ) return $result;

	imapLogout( $imap_stream );
}

?>

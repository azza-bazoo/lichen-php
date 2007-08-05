<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
include/interface.inc.php - functions for interacting with the client-side code
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

if ( version_compare( PHP_VERSION, '5.2.0', '<' ) ) {
	// We don't have a json_encode function, so include one from PEAR.
	include( 'libs/JSON.php' );
	$json_convertor = new Services_JSON();

	function json_encode_real($data) {
		global $json_convertor;
		return $json_convertor->encode($data);
	}
	function json_decode_real($data) {
		global $json_convertor;
		return $json_convertor->decode($data);
	}
} else {
	// PEARs JSON functions take and return arrays.
	// PHP 5.2.0's will return 'stdClass' objects instead,
	// which breaks code (because you can't "data['foo']" on them)
	// So we wrap these with ones that work on arrays.
	function json_encode_real($data) {
		return json_encode($data);
	}
	function json_decode_real($data) {
		// The second parameter - TRUE - make it return arrays instead.
		return json_decode($data, TRUE);
	}
}

// Prepares a JSON response to the client of a failure.
function remoteRequestFailure( $code, $message ) {
	$imapErrors = imap_errors();
	if ( $imapErrors ) {
		$imapErrors = implode( ", ", $imapErrors );
	} else {
		$imapErrors = "";
	}
	return json_encode_real(
		array(
			'resultCode' => $code,
			'errorMessage' => $message,
			'imapNotices' => $imapErrors
		));
}

// Successful remote request: merge the success tokens with the data
// and prepare that to send to the client.
function remoteRequestSuccess( $array = NULL ) {
	if ( $array == NULL ) {
		$array = array();
	}

	$array['resultCode'] = 'OK';
	$imapErrors = imap_errors();
	if ( $imapErrors ) {
		$array['imapNotices'] = implode( ", ", $imapErrors );
	}

	return json_encode_real( $array );
}

?>

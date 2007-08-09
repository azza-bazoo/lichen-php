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


if ( !function_exists( 'json_encode' ) ) {
	// We don't have a json_encode function, so include one from PEAR.
	include( 'libs/JSON.php' );
	$json_convertor = new Services_JSON();

	function unobjectify_deep($value) {
		$value = is_object( $value ) ? array_map( 'unobjectify_deep', $value ) : get_object_vars( $value );
		return $value;
	}
	function json_encode_assoc($data) {
		global $json_convertor;
		return $json_convertor->encode($data);
	}
	function json_decode_assoc($data) {
		global $json_convertor;
		$decoded = $json_convertor->decode($data);
		// PEAR's JSON follows the JSON spec exactly; so associative arrays become objects.
		// This breaks our code, because we expect to be able to array['foo'] which doesn't work with objects.
		$decoded = unobjectify_deep($decoded);
		return $decoded;
	}
} else {
	// PEAR's JSON functions take and return arrays.
	// PHP 5.2.0's will return 'stdClass' objects instead, which
	// breaks our code (because you can't call $data['foo'])
	function json_encode_assoc( $data ) {
		return json_encode( $data );
	}
	function json_decode_assoc( $data ) {
		// The second parameter here makes it return arrays instead.
		return json_decode( $data, true );
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
	return json_encode_assoc(
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

	return json_encode_assoc( $array );
}

?>

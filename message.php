<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
message.php - returns the contents of messages and message parts
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
	die( _("Sorry, you're not logged in!") );
}

if ( !isset( $_GET['uid'] ) ) {
	die( _("You haven't specified a message to display.") );
}

if ( isset( $_GET['mailbox'] ) ) {
	connectToServer( $_SESSION['user'], $_SESSION['pass'], $_GET['mailbox'] );
} else {
	connectToServer( $_SESSION['user'], $_SESSION['pass'] );
}

// To retrieve a message, we need its number in the mailbox.
$msgUid = $_GET['uid'];

if ( isset( $_GET['filename'] ) ) {

//
// Display the contents of an attachment
//
	include( "libs/streamattach.php" );

	$result = streamLargeAttachment( $IMAP_SERVER, $IMAP_PORT, $IS_SSL, $_SESSION['user'], $_SESSION['pass'],
	       $mailbox, $msgUid, $_GET['filename'] );

	if ( $result != NULL ) {
		echo _("Error getting attachment: "), $result;
	}


} elseif ( isset( $_GET['struct'] ) ) {

//
// Debug code - show parsing structures for a message
//

	header( "Content-type: text/plain" );

	$msgNo = imap_msgno( $mbox, $msgUid );

	print_r( imap_headerinfo( $mbox, $msgNo ) );

	print_r( imap_fetchstructure( $mbox, $msgNo ) );

	echo "\n-----------------------\n";

	$msgArray = retrieveMessage( $msgNo, false );

	print_r( $msgArray );


} elseif ( isset( $_GET['source'] ) ) {

//
// Draw the source text of a message
//

	include ( 'libs/streamattach.php' );

	$result = streamLargeAttachment( $IMAP_SERVER, $IMAP_PORT, $IS_SSL, $_SESSION['user'], $_SESSION['pass'], $mailbox, $msgUid, "LICHENSOURCE" );

	if ( $result != NULL ) {
		// Something went wrong.
		die( $result );
	}

}

// Supress the "Notice:  Unknown: SECURITY PROBLEM: insecure server advertised AUTH=PLAIN (errflg=1)"
// that PHP generates when not using TLS on Localhost.
imap_errors();

imap_close($mbox);
exit;

?>

/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/interface-helpers.js - helper functions for the interface
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


function if_remoteRequestFailed( remoteText ) {
	// For the time being...
	if_remoteRequestEnd();
	// TODO: show, rather than momentarily flash, the error
	Flash.flashMessage( remoteText );
	//alert( remoteText );
}


function if_relogin() {
//	if_remoteRequestStart();
	var password = $('relogin_pass').value;
	new Ajax( 'index.php', {
		postBody: 'action=relogin&user='+encodeURIComponent(serverUser)+'&pass='+encodeURIComponent(password),
		onComplete: if_reloginCB,
		onFailure: if_remoteRequestFailed
		} ).request();
}


function if_reloginCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	// Check the result.
	if ( result.error ) {
		Flash.flashMessage( result.error );
	} else {
		// All ok, logged in. Our session cookie will be restored.
		$('opts-wrapper').setStyle( 'display', 'none' );
		$('opts-wrapper').empty();
	}
}


// For testing purposes.
function if_logoutSilent() {
//	if_remoteRequestStart();
	new Ajax( 'index.php', {
		postBody: 'logout=0&silent=0',
		onComplete: if_logoutSilentCB,
		onFailure: if_remoteRequestFailed
		} ).request();
}


function if_logoutSilentCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	Flash.flashMessage( _("Silently logged out.") );
}


// Spawns a new window at the given URL.
function if_newWin( addr ) {
	var nw = window.open( addr );
	return !nw;
}

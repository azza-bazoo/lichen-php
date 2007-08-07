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


function if_returnToList( leavingUID ) {
	list_checkCount(); // Check the list is up to date and reset the timer.
	if ( leavingUID && $('mr-'+leavingUID) ) {
		$('mr-'+leavingUID).removeClass('new');
	}
	if_hideWrappers();
	if_hideToolbars();
	$('list-wrapper').style.display = 'block';
	$('list-bar').style.display = 'block';
	list_show();
}


function if_returnToMessage() {
	if_hideWrappers();
	if_hideToolbars();
	$('msg-wrapper').style.display = 'block';
	$('msg-bar').style.display = 'block';
}


function if_remoteRequestFailed( remoteText ) {
	// For the time being...
	if_remoteRequestEnd();
	Flash.flashMessage( remoteText );
	//alert( remoteText );
}


function if_relogin() {
	if_remoteRequestStart();
	var password = $('relogin_pass').value;
	new Ajax( 'index.php', {
		postBody: 'action=relogin&user='+encodeURIComponent(serverUser)+'&pass='+encodeURIComponent(password),
		onComplete : function ( responseText ) {
			if_reloginCB( responseText );
		},
		onFailure : function( responseText ) {
			if_remoteRequestFailed( responseText );
		}
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
	if_remoteRequestStart();
	new Ajax( 'index.php', {
		postBody: 'logout=0&silent=0',
		onComplete : function ( responseText ) {
			if_logoutSilentCB( responseText );
		},
		onFailure : function( responseText ) {
			if_remoteRequestFailed( responseText );
		}
		} ).request();
}


function if_logoutSilentCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	Flash.flashMessage( "Silently logged out." );
}


function if_hideWrappers() {
//	$('list-status').style.display = 'none';
	$('list-wrapper').style.display = 'none';
	$('msg-wrapper').style.display = 'none';
	$('opts-wrapper').style.display = 'none';
	$('comp-wrapper').style.display = 'none';
}


function if_hideToolbars() {
	$('list-bar').style.display = 'none';
	$('comp-bar').style.display = 'none';
	$('msg-bar').style.display = 'none';
	$('opts-bar').style.display = 'none';
}


// function toggleDisplay( itemID ) {
// 	var el = $( itemID );
//
// 	if ( el.style.display == 'none' ) {
// 		el.style.display = 'block';
// 	} else if ( el.style.display == 'block' ) {
// 		el.style.display = 'none';
// 	} else {
// 		el.style.display = 'none';
// 	}
// }


// Spawns a new window at the given URL.
function if_newWin( addr ) {
	var nw = window.open( addr );
	return !nw;
}

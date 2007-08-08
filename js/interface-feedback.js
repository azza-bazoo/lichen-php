/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/interface-feedback.js - loading feedback, notification messages
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


var FlashArea = new Class({
	initialize: function ( wrapper ) {
		this.wrapper = wrapper;
		this.messages = Array();
		this.timeouts = Array();
		this.onscreen = false;
		//this.slider = new Fx.Slide( wrapper );
	},
	flashMessage: function ( message, submessage ) {
		if ( submessage ) {
			this.messages.push( message + "<br /><span class=\"flash-submessage\">" + submessage + "</span>" );
		} else {
			this.messages.push( message );
		}

		this.renderFlash();

		if ( !this.onscreen ) {
			//this.slider.slideOut();
			$(this.wrapper).setStyle( 'display', 'block' );
			this.onscreen = true;
		}

		this.timeouts.push( window.setTimeout( this.clearFlash.bind( this ), 5000 ) );
	},
	renderFlash: function () {
		var flashHTML = this.messages.join( "<br />" );
		flashHTML += "<br /><a href=\"#\" onclick=\"Flash.hideFlash(); return false\">Hide</a>";
		$(this.wrapper).setHTML( flashHTML );
	},
	clearFlash: function () {
		this.messages.shift();
		this.timeouts.shift();

		if ( this.messages.length == 0 ) {
			//this.slider.slideIn();
			$(this.wrapper).setStyle( 'display', 'none' );
			this.onscreen = false;
		} else {
			this.renderFlash();
		}
	},
	hideFlash: function () {
		// Hide the flash instantly.
		// Cancel all the timeouts.
		this.messages = Array();
		for ( var i = 0; i < this.timeouts.length; i++ ) {
			window.clearTimeout( this.timeouts[i] );
		}
		this.timeouts = Array();
		this.renderFlash();
		this.onscreen = false;
		$(this.wrapper).setStyle( 'display', 'none' );
	}
});


// Remote request started; display some sort of loading feedback.
// Keep a count of the remote requests.
function if_remoteRequestStart() {
	remoteRequestCount += 1;

//	$('loading').style.display = 'block';
}


// A request has completed... decrement the count, and if all requests
// have finished, remove the loading div.
function if_remoteRequestEnd() {
	if (remoteRequestCount > 0) {
		remoteRequestCount -= 1;
	}
	if (remoteRequestCount == 0) {
//		$('loading').style.display = 'none';
	}
}


// Check to see if the remote was all ok.
// Return JSON result if ok, or null if it failed.
function if_checkRemoteResult( remoteText ) {
	var result = "";

	if ( $type( remoteText ) != "string" ) {
		// Must have got an object; example if we simulate a callback
		// from the server by passing a JSON object.
		// Just return the object.
		return remoteText;
	}

	result = Json.evaluate( remoteText, true );

	if (!result) {
		// TODO: this shouldn't be an alert box.
		alert( "Unable to Json decode what the server sent back.\n" +
			"The server sent us: '" + remoteText + "'\n" );
		if_remoteRequestEnd();
		return null;
	}

	if_remoteRequestEnd();

	if (result.resultCode != 'OK') {
		// Not ok.
		if ( result.resultCode == 'AUTH' ) {
			// Ask the user to enter their password.
			// TODO: Make everything else unusable on the page .. ?
			// TODO: Server side: make a note of what we were trying to do so that
			// we can do it again!
			var reloginBox = $('opts-wrapper');
			var reloginHTML = "";
			reloginHTML += "<h3>You were logged out.</h3>";
			reloginHTML += "<p>The server said: " + result.errorMessage + "</p>";
			reloginHTML += "<p>Enter your password to login again:</p>";
			reloginHTML += "<input type=\"password\" name=\"relogin_pass\" id=\"relogin_pass\" />";
			reloginHTML += "<button onclick=\"if_relogin(); return false\">Login</button>";

			reloginBox.setHTML( reloginHTML );
			reloginBox.setStyle( 'display', 'block' );
			$('relogin_pass').focus();
		} else {
			// Just alert the message we're given.
			Flash.flashMessage( result.errorMessage, "Imap errors: " + result.imapNotices );
			//alert( "Server says: " + result.errorMessage + "\nImap errors: " + result.imapNotices );
			return null;
		}
	} else {
		// All ok.
		return result;
	}
}

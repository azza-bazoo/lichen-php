/**

Lichen - AJAX IMAP client
version 0.4 by Hourann Bosci and Daniel Foote
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
		this.messages = new Array();
		this.timeouts = new Array();
		this.onscreen = false;
		//this.slider = new Fx.Slide( wrapper );
	},
	flashMessage: function ( message, submessage ) {
		if ( submessage ) {
			this.messages.push( message + "<div class=\"detail\">" + submessage + "</div>" );
		} else {
			this.messages.push( message );
		}

		this.renderFlash();

		if ( !this.onscreen ) {
			//this.slider.slideOut();
			$(this.wrapper).setStyle( 'display', 'block' );
			this.onscreen = true;
		}

		this.timeouts.push( window.setTimeout( this.clearFlash.bind( this ), 7500 ) );
	},
	renderFlash: function () {
		var flashHTML = this.messages.join( "<br />" );
//		flashHTML += "<br /><a href=\"#\" onclick=\"Flash.hideFlash(); return false\">Hide</a>";
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
		this.messages = new Array();
		for ( var i = 0; i < this.timeouts.length; i++ ) {
			window.clearTimeout( this.timeouts[i] );
		}
		this.timeouts = new Array();
		this.renderFlash();
		this.onscreen = false;
		$(this.wrapper).setStyle( 'display', 'none' );
	}
});


// Simple class to apply (and later stop) an exit transition effect
// on a given element, typically one of the interface pane wrappers.
var PaneTransition = new Class({

	initialize: function ( targetElementName ) {
		this.fadeOut = new Fx.Style( targetElementName, 'opacity', { duration: 1500 } );
		this.elementID = targetElementName;
		if ( !window.ie ) {
			// we couldn't get this to work properly in IE6 in
			// time for release -- sometimes opacity wasn't going
			// back to 1 -- and there's the ClearType problem!
			this.fadeOut.start( 0.9, 0 );
		}
	},

	end: function () {
		// Stop the animation and reset opacity back to 1, but hide the element
		this.fadeOut.stop();
		$( this.elementID ).style.display='none';
		$( this.elementID ).setStyle( 'opacity', 1 );	// mootools will use filter() for IE
	}
});


// Remote request started; display some sort of loading feedback.
// TODO: this needs to be replaced with a more organised feedback class
function if_remoteRequestStart() {
	// TODO: debug in IE, problem seems be execution order of this function's callers
	/*
	if ( !window.ie ) {
		var wrapElements = new Array( 'msg-wrapper', 'opts-wrapper', 'comp-wrapper',
						'addr-wrapper', 'list-wrapper' );

		for ( var i = 0; i < wrapElements.length; i ++ ) {
			if ( $( wrapElements[i] ).style.display != 'none' ) {
				break;
			}
		}

		activeFadeEffect = new PaneTransition( wrapElements[i] );
	}
	*/
}


// Stop the loading feedback animation
function if_remoteRequestEnd() {
	/*
	if ( activeFadeEffect ) {
		activeFadeEffect.end();
		activeFadeEffect = false;
	}
	*/
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
		alert( _("Unable to Json decode what the server sent back.\n The server sent us:") +
			" '" + remoteText + "'\n" );
		if_remoteRequestEnd();
		return null;
	}

	if_remoteRequestEnd();

	if (result.resultCode != 'OK') {
		// Not ok.
		if ( result.resultCode == 'AUTH' ) {
			// Ask the user to enter their password.
			// TODO: Make everything else unusable on the page .. ?
			// TODO: Server side: make a note of what action we were just trying to
			// perform, so we can repeat it - at the moment you have to redo the
			// action you were trying to accomplish.
			// 
			// Only recreate the form if it wasn't already displayed; subsequent
			// auth failures will not reset the box - and reset your half-typed
			// password.
			if ( $('relogin_pass') == null ) {
				var reloginBox = $('opts-wrapper');
				var reloginHTML = "";
				reloginHTML += "<h3>" + _('You were logged out.') + "</h3>";
				reloginHTML += "<p>" + _('The server said:') + " " + result.errorMessage + "</p>";
				reloginHTML += "<p>" + _('Enter your password to login again:') + "</p>";
				reloginHTML += "<input type=\"password\" name=\"relogin_pass\" id=\"relogin_pass\" />";
				reloginHTML += "<button onclick=\"if_relogin(); return false\">" + _('Login') + "</button>";

				reloginBox.setHTML( reloginHTML );
				reloginBox.setStyle( 'display', 'block' );
				$('relogin_pass').focus();
			}
		} else {
			// Just alert the message we're given.
			if ( result.imapNotices != "" ) {
				Lichen.Flash.flashMessage( result.errorMessage, _("IMAP messages: ") + result.imapNotices );
			} else {
				Lichen.Flash.flashMessage( result.errorMessage, "" );
			}
			//alert( "Server says: " + result.errorMessage + "\nImap errors: " + result.imapNotices );
			return null;
		}
	} else {
		// All ok.
		return result;
	}
}

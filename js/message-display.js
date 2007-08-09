/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/message-display.js - retrieval and display of message bodies
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


var MessageDisplay = new Class({
	initialize: function( wrapperdiv ) {
		this.wrapperdiv = wrapperdiv;
		this.displayMode = "";
		this.displayModeUID = "";
	},

	showMessage: function( mailbox, uid, mode ) {
		// Rather than having fetchMessage send us the mode back,
		// cache it in our instance data. We have to mark which UID we were
		// talking about, though.
		this.displayMode = mode;
		this.displayModeUID = uid;
		var requestMode = mode;

		// Hack to fetch the text data but display it as monospaced.
		if ( mode == "monospace" ) {
			requestMode = "text";
		}

		// Go off and fetch the data.
		Messages.fetchMessage( mailbox, uid, requestMode );
	},

	showMessageCB: function( message, mode ) {
		// Got the data about the message. Render it and begin the display.
		if ( $('mr-'+message.uid) ) {
			$('mr-'+message.uid).removeClass('new');
		}

		if ( !mode && this.displayModeUID == message.uid ) {
			mode = this.displayMode;
		}

		clearTimeout( refreshTimer );
		if_hideWrappers();
		if_hideToolbars();
		$(this.wrapperdiv).empty();
		$(this.wrapperdiv).setHTML( this._render( message, mode ) );
		$(this.wrapperdiv).style.display = 'block';
		$('msg-bar').style.display = 'block';
		lastShownUID = message.uid;
	},

	_render: function( message, forceType ) {
		// Find the next/previous messages.
		var adjacentMessages = Messages.fetchAdjacentMessages( listCurrentMailbox, listCurrentSearch, listCurrentPage, listCurrentSort, message.uid );

		var htmlFragment = "<div class=\"header-bar\"><img src=\"themes/" + userSettings.theme + "/top-corner.png\" alt=\"\" id=\"top-corner\" />";

		var messageNavBar = "<div class=\"header-left\"><a class=\"list-return\" href=\"#inbox\" onclick=\"if_returnToList(lastShownUID);return false\">back to " + listCurrentMailbox + "</a></div>";

		messageNavBar += "<div class=\"header-right\">";
		if ( adjacentMessages.previous ) {
			messageNavBar += "<a href=\"#\" onclick=\"return showMsg('" + message.mailbox + "','" + adjacentMessages.previous.uid + "')\">previous";
			if ( adjacentMessages.next ) {
				messageNavBar += "</a> | ";
			} else {
				messageNavBar += "message</a>";
			}
		}
		if ( adjacentMessages.next ) {
			messageNavBar += "<a href=\"#\" onclick=\"return showMsg('" + message.mailbox + "','" + adjacentMessages.next.uid + "')\">next message</a>";
		}
		messageNavBar += "</div>";

		htmlFragment += messageNavBar + "</div>";

		htmlFragment += "<h1 class=\"msg-head-subject\">" + message.subject + "</h1>";
		htmlFragment += "<p class=\"msg-head-line2\">from <span class=\"msg-head-sender\">" + message.from + "</span> ";
		htmlFragment += "at <span class=\"msg-head-date\">" + message.localdate + "</span></p>";

		htmlFragment += "<div>";
		var viewLinks = Array();
		if ( message.texthtml.length > 0 || message.texthtmlpresent ) {
			viewLinks.push( "<a href=\"#\" onclick=\"return showMsg('" + message.mailbox + "','" + message.uid + "', 'html')\">HTML View</a>" );
			if ( message.htmlhasremoteimages ) {
				viewLinks.push( "<a href=\"#\" onclick=\"return MessageDisplayer.enableRemoteImages()\">Show Remote Images</a>" );
			}
		}
		if ( message.textplain.length > 0 || message.textplainpresent ) {
			viewLinks.push( "<a href=\"#\" onclick=\"return showMsg('" + message.mailbox + "','" + message.uid + "', 'text')\">Text View</a>" );
			viewLinks.push( "<a href=\"#\" onclick=\"return showMsg('" + message.mailbox + "','" + message.uid + "', 'monospace')\">Monospace View</a>" );
		}
		viewLinks.push( "<a href=\"#\" onclick=\"if_newWin('message.php?source&amp;mailbox='+listCurrentMailbox+'&amp;uid='+encodeURIComponent(lastShownUID))\">Source</a>" );

		htmlFragment += viewLinks.join( " | " );
		htmlFragment += "</div>";

		// TODO: Clean up this multistage IF. Its a bit IFFY.
		if ( message.texthtml.length > 0 && forceType != "text" && forceType != "monospace" && forceType != "source" ) {
			// Display HTML in preference.
			for ( var i = 0; i < message.texthtml.length; i++ ) {
				htmlFragment += "<div class=\"html-message\">";
				htmlFragment += message.texthtml[i]; // This is sanitised on the server.
				htmlFragment += "</div>";
			}
		} else {
			// Display the text parts.
			for ( var i = 0; i < message.textplain.length; i++ ) {
				htmlFragment += "<div class=\"plain-message";
				if ( forceType == "monospace" ) {
					htmlFragment += " plain-message-monospace";
				}
				htmlFragment += "\">";
				htmlFragment += message.textplain[i]; // This is linkified/cleaned on the server.
				htmlFragment += "</div>";
			}
		}

		if ( message.attachments.length > 0 && forceType != "source" ) {
			htmlFragment += "Attachments: <ul class=\"attachments\">";

			for ( var i = 0; i < message.attachments.length; i++ ) {
				var thisAttach = message.attachments[i];
				// Skip attachments that are internal-only.
				if ( thisAttach.filename == "" ) continue;
				htmlFragment += "<li>";
				var attachUrl = "message.php?mailbox=" + encodeURIComponent( message.mailbox ) +
					"&uid=" + encodeURIComponent( message.uid ) + "&filename=" + encodeURIComponent( thisAttach.filename );
				htmlFragment += "<a href=\"" + attachUrl + "\" onclick=\"return if_newWin('" + attachUrl + "')\">";
				htmlFragment += thisAttach.filename + "</a>";
				htmlFragment += " (type " + thisAttach.type + ", size ~" + thisAttach.size + " bytes)";

				if ( thisAttach.type.substr( 0, 5 ) == "image" ) {
					htmlFragment += "<br />";
					htmlFragment += "<img src=\"" + attachUrl + "\" alt=\"" + attachUrl + "\" />";
				}
			}

			htmlFragment += "</ul>";
		}

		htmlFragment += "<div class=\"footer-bar\"><img src=\"themes/" + userSettings.theme + "/bottom-corner.png\" alt=\"\" id=\"bottom-corner\" />" + messageNavBar + "</div>";

		return htmlFragment;
	},

	enableRemoteImages: function () {
		// Remote images have the class "remoteimage", and are disabled by prepending a
		// "_" to the url, breaking it. Strip this break.
		// TODO: this is the correct way to do it:
		// var remoteImages = $A( $ES( 'img.remoteimage' ) );
		var remoteImages = $('msg-wrapper').getElementsByTagName('img');

		for ( var i = 0; i < remoteImages.length; i++ ) {
			if ( remoteImages[i].src.substr( 7, 1 ) == "_" ) {
				var fixedUrl = "http://" + remoteImages[i].src.substr( 8 );
				remoteImages[i].src = fixedUrl;
			}
		}

		return false;
	}
});


// TODO: This is a hack to make it work for now ...
function showMsg( mailbox, uid, mode ) {
	MessageDisplayer.showMessage( mailbox, uid, mode );
	return false;
}
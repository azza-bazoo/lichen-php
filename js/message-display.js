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
	initialize: function( dataStore, wrapperdiv ) {
		this.dataStore = dataStore;
		this.wrapperdiv = wrapperdiv;
		this.displayMode = "";
		this.displayModeUID = "";
		this.lastShownUID = "";
	},

	getViewedUID: function () {
		return this.lastShownUID;
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
		this.dataStore.fetchMessage( mailbox, uid, requestMode );

		return false;
	},

	showMessageCB: function( message, mode ) {
		// Got the data about the message. Render it and begin the display.
		if ( $('mr-'+message.uid) ) {
			$('mr-'+message.uid).removeClass('new');
		}

		if ( !mode && this.displayModeUID == message.uid ) {
			mode = this.displayMode;
		}

		$(this.wrapperdiv).empty();
		$(this.wrapperdiv).setHTML( this._render( message, mode ) );
		$(this.wrapperdiv).style.display = 'block';
		$('msg-bar').style.display = 'block';
		this.lastShownUID = message.uid;

		// This is a hack to display the "edit as draft" button
		// only if the user is viewing the drafts folder.
		var draftButton = $('btn-draft');
		if ( Lichen.MessageList.getMailbox() == specialFolders.drafts ) {
			draftButton.setStyle('display', 'inline');
		} else {
			draftButton.setStyle('display', 'none');
		}
	},

	_render: function( message, forceType ) {
		// ****************************************************
		// IF YOU CHANGE THIS CODE, CHANGE THE MATCHING CODE IN
		// include/htmlrender.php AS WELL.

		// Find the next/previous messages.
		var adjacentMessages = this.dataStore.fetchAdjacentMessages( Lichen.MessageList.getMailbox(), Lichen.MessageList.getSearch(), Lichen.MessageList.getPage(), Lichen.MessageList.getSortStr(), message.uid );

		var htmlFragment = "<div class=\"list-header-bar\"><img src=\"themes/" + userSettings.theme + "/top-corner.png\" alt=\"\" class=\"top-corner\" />";

		var messageNavBar = "<div class=\"header-left\"><a class=\"list-return\" href=\"#inbox\" onclick=\"Lichen.action('list','MessageList','listUpdate');return false\">" + _('back to ') + Lichen.MessageList.getMailbox() + "</a></div>";
			
		messageNavBar += "<div class=\"header-right\">";
		if ( adjacentMessages.previous ) {
			messageNavBar += "<a href=\"#\" onclick=\"return Lichen.action('display','MessageDisplayer','showMessage',['" + message.mailbox + "','" + adjacentMessages.previous.uid + "'])\">" + "&laquo; " + adjacentMessages.previous.subject;
			if ( adjacentMessages.next ) {
				messageNavBar += "</a> | ";
			} else {
				messageNavBar += "</a>";
			}
		}
		if ( adjacentMessages.next ) {
			messageNavBar += "<a href=\"#\" onclick=\"return Lichen.action('display','MessageDisplayer','showMessage',['" + message.mailbox + "','" + adjacentMessages.next.uid + "'])\">" + adjacentMessages.next.subject + " &raquo;" + "</a>";
		}

		messageNavBar += "</div>";
		messageNavBar += "<div class=\"header-left\">";
		messageNavBar += "<select onchange=\"Lichen.MessageDisplayer.moveMessage(this)\">";
		messageNavBar += "<option value=\"noop\" selected=\"selected\">" + _('move message to ...') + "</option>";

		// Build a list of mailboxes.
		// Only use a version that the MessagesDatastore has cached.
		// TODO: Figure out how to make it synchonously request and return this data
		// if needed. For the moment, we're relying on the fact that it's already
		// been requested.
		mailboxes = this.dataStore.fetchMailboxList( true );
		if ( mailboxes ) {
			for ( var i = 0; i < mailboxes.length; i++ ) {
				messageNavBar += "<option value=\"move-" + mailboxes[i].fullboxname + "\">";
				for ( var j = 0; j < mailboxes[i].folderdepth; j++ ) {
					messageNavBar += "-";
				}
				messageNavBar += mailboxes[i].mailbox;
				messageNavBar += "</option>";
			}
		}

		messageNavBar += "</select>";
		messageNavBar += " &nbsp; <input type=\"button\" onclick=\"Lichen.MessageDisplayer.deleteMessage();return false\" value=\"" + _('delete message') + "\" />";
		messageNavBar += "</div>";
		messageNavBar += "</div>";
		htmlFragment += messageNavBar;

		htmlFragment += "<select id=\"msg-switch-view\" onchange=\"return Lichen.action('display','MessageDisplayer','switchView',[this.value])\">";
		htmlFragment += "<option value=\"noop\">" + _('switch view ...') + "</option>";

		if ( message.texthtml.length > 0 || message.texthtmlpresent ) {
			htmlFragment += "<option value=\"html\">" + _('HTML part') + "</option>";
		}
		if ( message.textplain.length > 0 || message.textplainpresent ) {
			htmlFragment += "<option value=\"text\">" + _('text part') + "</option>";
			htmlFragment += "<option value=\"text-mono\">" + _('monospace text') + "</option>";
		}
		htmlFragment += "<option value=\"source\">" + _('message source') + "</option>";
		htmlFragment += "</select>";

		htmlFragment += "<h1 class=\"msg-head-subject\">" + message.subject_html + "</h1>";
		htmlFragment += "<p class=\"msg-head-line2\">from <span class=\"msg-head-sender\">" + message.from_html + "</span> ";
		htmlFragment += "at <span class=\"msg-head-date\">" + message.localdate_html + "</span></p>";

		if ( message.htmlhasremoteimages ) {
			htmlFragment += "<div class=\"msg-notification\">";
			htmlFragment += _("Remote images are not displayed.") + " [<a href=\"#\" onclick=\"return Lichen.MessageDisplayer.enableRemoteImages()\">" + _('show images') + "</a>]";
			htmlFragment += "</div>";
		}

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
				htmlFragment += "<div id=\"plainmsg-" + i + "\" class=\"plain-message";
				if ( forceType == "monospace" ) {
					htmlFragment += " plain-message-monospace";
				}
				htmlFragment += "\">";
				
				// Hack: if the message part was too large, the server will not have returned it.
				// Include a link to make this happen.
				if ( message.textplain[i].substr( 0, 14 ) == "LICHENTOOLARGE" ) {
					var messagePart = message.textplain[i].substr(15).split(")")[0];
					htmlFragment += "<a href=\"#\" onclick=\"Lichen.MessageDisplayer.getLargePart('" +
						message.uid + "', '" + message.mailbox + "','" + messagePart + "'," +
						i + ");return false\">" + ('This message part was too large to return directly. Click here to load it.') + "</a>";
				} else {
					htmlFragment += message.textplain[i]; // This is linkified/cleaned on the server.
				}
				htmlFragment += "</div>";
			}
		}

		if ( message.attachments.length > 0 && forceType != "source" ) {
			htmlFragment += "<ul class=\"attachments\">";

			for ( var i = 0; i < message.attachments.length; i++ ) {
				var thisAttach = message.attachments[i];
				// Skip attachments that are internal-only.
				if ( thisAttach.filename == "" ) continue;
				htmlFragment += "<li>";
				var attachUrl = "message.php?mailbox=" + encodeURIComponent( message.mailbox ) +
					"&uid=" + encodeURIComponent( message.uid ) + "&filename=" + encodeURIComponent( thisAttach.filename );
				htmlFragment += "<a href=\"" + attachUrl + "\" onclick=\"return if_newWin('" + attachUrl + "')\">";
				htmlFragment += thisAttach.filename + "</a>";
				htmlFragment += " <span class=\"msg-attach-meta\">type " + thisAttach.type + ", size ~" + thisAttach.size + " bytes</span>";

				if ( thisAttach.type.substr( 0, 5 ) == "image" ) {
					htmlFragment += "<br />";
					htmlFragment += "<img src=\"" + attachUrl + "\" alt=\"" + attachUrl + "\" />";
				}
			}

			htmlFragment += "</ul>";
		}

		htmlFragment += "<div class=\"footer-bar\"><img src=\"themes/" + userSettings.theme + "/bottom-corner.png\" alt=\"\" class=\"bottom-corner\" />" + messageNavBar + "</div>";

		return htmlFragment;
	},

	switchView: function( toMode ) {
		// Respond to a user's click on the "switch view" dropdown
		switch( toMode ) {
			case 'html':
				this.showMessage( Lichen.MessageList.getMailbox(), this.lastShownUID, 'html' );
				break;
			case 'text':
				this.showMessage( Lichen.MessageList.getMailbox(), this.lastShownUID, 'text' );
				break;
			case 'text-mono':
				this.showMessage( Lichen.MessageList.getMailbox(), this.lastShownUID, 'monospace' );
				break;
			case 'source':
				if_newWin(
					'message.php?source&mailbox='
					+ encodeURIComponent( Lichen.MessageList.getMailbox() ) + '&uid='
					+ encodeURIComponent( this.lastShownUID ) );
				break;
		}

		return false;
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
	},

	getLargePart: function( uid, mailbox, part, index ) {
		// Ask the server to fetch this part of the message.
		// Do it synchonously.
		$('plainmsg-' + index).setHTML( _("Please wait... this might take a while...") );

		var contents = Lichen.Messages.fetchLargePart( mailbox, uid, part, index );
		
		// Inline replace the contents. This is a hack.
		$('plainmsg-' + index).setHTML( contents );
	},

	moveMessage: function ( selector ) {
		var destBox = selector.value.substr( 5 );

		Lichen.Messages.moveMessage( Lichen.MessageList.getMailbox(), destBox, this.displayModeUID, this.movedMessageCB.bind( this ) );
	},

	deleteMessage: function () {
		Lichen.Messages.deleteMessage( Lichen.MessageList.getMailbox(), this.displayModeUID, this.movedMessageCB.bind( this ) );
	},

	movedMessageCB: function ( result ) {
		Lichen.Flash.flashMessage( result.message );

		// Move onto the next message.
		var adjacentMessages = this.dataStore.fetchAdjacentMessages( Lichen.MessageList.getMailbox(), Lichen.MessageList.getSearch(), Lichen.MessageList.getPage(), Lichen.MessageList.getSortStr(), this.displayModeUID );

		if ( adjacentMessages.next ) {
			this.showMessage( Lichen.MessageList.getMailbox(), adjacentMessages.next.uid );
		} else {
			Lichen.action( "list", "MessageList", "listUpdate" );
		}
	}
});


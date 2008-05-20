/**

Lichen - AJAX IMAP client
version 0.4 by Hourann Bosci and Daniel Foote
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
		this.messageData = null;
	},

	getViewedUID: function () {
		return this.lastShownUID;
	},

	getWindowTitle: function () {
		if ( this.messageData ) {
			return this.messageData.subject + _(" - Message Display");
		} else {
			return _("Message Display");
		}
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
			messageNavBar += "<a href=\"#\" onclick=\"return Lichen.action('display','MessageDisplayer','showMessage',['" + message.mailbox + "','" + adjacentMessages.previous.uid + "'])\">" + "&laquo; ";
			if ( adjacentMessages.previous.subject.length > 24 ) {
				messageNavBar += adjacentMessages.previous.subject.substr(0,24) + "...";
			} else {
				messageNavBar += adjacentMessages.previous.subject;
			}
			if ( adjacentMessages.next ) {
				messageNavBar += "</a> | ";
			} else {
				messageNavBar += "</a>";
			}
		}
		if ( adjacentMessages.next ) {
			messageNavBar += "<a href=\"#\" onclick=\"return Lichen.action('display','MessageDisplayer','showMessage',['" + message.mailbox + "','" + adjacentMessages.next.uid + "'])\">";
			if ( adjacentMessages.next.subject.length > 24 ) {
				messageNavBar += adjacentMessages.next.subject.substr(0,24) + "...";
			} else {
				messageNavBar += adjacentMessages.next.subject;
			}
			messageNavBar += " &raquo;" + "</a>";
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
		if ( !window.ie ) {
			// woohoo hack! this check fails in IE, so for now just skip it
			// and let the list be fetched elsewhere in the loading processes
			mailboxes = this.dataStore.fetchMailboxList( true );
		}
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
		htmlFragment += "<p class=\"msg-head-line2\" id=\"msg-details-short\">";
//		if ( message.from_wasMe || !message.to_wasMe ) {
		htmlFragment += _("to") + " <span class=\"msg-head-sender\">" + message.to_html + "</span><br />";
		htmlFragment += _("from") + " <span class=\"msg-head-sender\">" + message.from_html + "</span> ";
		htmlFragment += "at <span class=\"msg-head-date\">" + message.localdate + "</span> ";
//		}
//		htmlFragment += "[<a href=\"#\" onclick=\"Lichen.MessageDisplayer.showFullDetails(); return false\">" + _('details') + "</a>]"
		htmlFragment += "</p>";

/*
		htmlFragment += "<p class=\"msg-head-line2\" id=\"msg-details-long\" style=\"display: none\">";
		htmlFragment += _("from") + " <span class=\"msg-head-sender\">" + message.from_html + "</span><br />";
		htmlFragment += _("to") + " <span class=\"msg-head-sender\">" + message.to_html + "</span><br />";
		if ( message.cc ) {
			htmlFragment += _("cc") + " <span class=\"msg-head-sender\">" + message.cc_html + "</span><br />";
		}
		if ( message.bcc ) {
			htmlFragment += _("bcc") + " <span class=\"msg-head-sender\">" + message.bcc_html + "</span><br />";
		}
		if ( message.replyto ) {
			htmlFragment += _("reply-to") + " <span class=\"msg-head-sender\">" + message.replyto_html + "</span><br />";
		}
//		htmlFragment += _("uid") + " <span class=\"msg-head-sender\">" + message.uid + "</span><br />";
		htmlFragment += "at <span class=\"msg-head-date\">" + message.localdate + "</span> ";
		htmlFragment += "</p>";
*/

		if ( message.htmlhasremoteimages ) {
			htmlFragment += "<div class=\"msg-notification\" id=\"msg-remoteimagesnotify\">";
			htmlFragment += _("Remote images are not displayed.") + " [<a href=\"#\" onclick=\"return Lichen.MessageDisplayer.enableRemoteContent()\">" + _('show images') + "</a>]";
//			htmlFragment += "<a href=\"#\" onclick=\"return Lichen.MessageDisplayer.alwaysEnableRemoteContent()\">" + _('always show from this sender') + "</a> (not yet implemented)]";
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

		this.messageData = message;

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

	alwaysEnableRemoteContent: function () {
		this.enableRemoteContent();

		// TODO: save this email address so that the images are shown by default in future...
	},

	showFullDetails: function () {
		$('msg-details-short').setStyle('display', 'none');
		$('msg-details-long').setStyle('display', 'block');
	},

	enableRemoteContent: function () {
		// Display all remote content.
		if ( this.messageData.htmlhasremoteimages ) {
			for (var i = 0; i < this.messageData.remotecontent.length; i++) {
				var thisContent = this.messageData.remotecontent[i];

				var thisItem = $( thisContent.id );
				if ( thisItem ) {
					// Set the given property to the original value.
					// This means we can change things other than 
					// just the src attribute of images.
					if ( thisContent.attr == "style" ) {
						for ( var j = 0; j < thisContent.url.length; j++ ) {
							thisItem.setStyle( thisContent.url[j][0], thisContent.url[j][1] );
						}
					} else {
						thisItem[ thisContent.attr ] = thisContent.url;
					}
				}
			}
		}

		$('msg-remoteimagesnotify').setStyle( 'display', 'none' );

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


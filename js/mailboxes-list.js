/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/mailboxes-list.js - display and refresh for the list of mailboxes
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

var MailboxLister = new Class({
	initialize: function ( wrapper ) {
		this.wrapper      = wrapper;
		this.mailboxCount = 0;
		this.lastUIDconst = 0;
		this.mailboxCount = -1;
		this.msgCount     = -1;
	},

	listUpdate: function () {
		Lichen.Messages.fetchMailboxList();
	},

	listUpdateCB: function ( mailboxes ) {
		var msgCounts = mailboxes;

		if (this.mailboxCount != msgCounts.length) {
			// A mailbox has been added/removed.
			// Build the interface list.
			this._render( msgCounts );
			// And stop...
			return;
		}
		var i = 0;

		for (i = 0; i < msgCounts.length; i++) {
			if ( Lichen.MessageList.getMailbox() == msgCounts[i].fullboxname ) {
				// Do we need to update the list?
				if ( this.lastUIDconst != "" && lastUIDconst != msgCounts[i].uidConst ) {
					Lichen.action( 'list', 'MessageList', 'listUpdate' );
				} else if ( this.msgCount != 0 && this.msgCount != msgCounts[i].messages ) {
					// Refresh the mailbox list. There are new/different messages.
					Lichen.action( 'list', 'MessageList', 'listUpdate' );
				}
				this.lastUIDconst = msgCounts[i].uidConst;
				this.msgCount = msgCounts[i].messages;

				// Update the highlight in the mailbox list, and the page title
				$('mb-'+Lichen.MessageList.getMailbox()).addClass('mb-active');
				document.title = msgCounts[i].mailbox +' ('+msgCounts[i].unseen+' unread, '+msgCounts[i].messages+' total)';
			}

			var countresult = "";
			// If non-zero, update the unread messages.
			if ( msgCounts[i].unseen > 0 || userSettings.boxlist_showtotal ) {
				countresult = "(" + msgCounts[i].unseen;
				if ( userSettings.boxlist_showtotal ) countresult += "/" + msgCounts[i].messages;
				countresult += ")";
			}

			if ( $('mb-unread-'+msgCounts[i].fullboxname) ) {
				$('mb-unread-'+msgCounts[i].fullboxname).setHTML( countresult );
			} else {
				// This mailbox is new or renamed; rebuild the list
				this._render( msgCounts );
				// Stop this function.
				return;
			}
		}
		
		this.mailboxCount = mailboxes.length;
	},

	_render: function ( mailboxes ) {
		$(this.wrapper).empty();
		
		// ****************************************************
		// IF YOU CHANGE THIS CODE, CHANGE THE MATCHING CODE IN
		// include/htmlrender.php AS WELL.

		var containerContents = "<li id=\"mb-header\"><span class=\"s-head\">Mailboxes</span> [<a href=\"#manage-mailboxes\" onclick=\"return Lichen.action('options','OptionsEditor','showEditor',['mailboxes'])\">edit</a>]</li>";

		for ( var i = 0; i < mailboxes.length; i++ ) {

			containerContents += "<li id=\"mb-" + mailboxes[i].fullboxname;
			if ( Lichen.MessageList.getMailbox() == mailboxes[i].fullboxname ) {
				containerContents += "\" class=\"mb-active";
			}
			containerContents += "\">";

			if ( mailboxes[i].selectable ) {
				containerContents += "<a href=\"#\" onclick=\"return Lichen.action('list', 'MailboxList', 'selectMailbox', ['" + mailboxes[i].fullboxname + "'])\" class=\"mb-click\">";
			}

			// This is a really bad way to indent.
			for ( var j = 0; j < mailboxes[i].folderdepth; j++ ) {
				containerContents += "&nbsp;&nbsp;";
			}

			containerContents += "<span class=\"mailbox\">" + mailboxes[i].mailbox;

			containerContents += " <span id=\"mb-unread-" + mailboxes[i].fullboxname + "\">";
			if (mailboxes[i].unseen > 0 || userSettings.boxlist_showtotal) {
				containerContents += "(" + mailboxes[i].unseen;
				if ( userSettings.boxlist_showtotal ) containerContents += "/" + mailboxes[i].messages;
				containerContents += ")";
			}
			containerContents += "</span>";
			containerContents += "</span>";

			if ( mailboxes[i].selectable ) {
				containerContents += "</a>";
			}
			containerContents += "</li>";
		}

		$(this.wrapper).setHTML( containerContents );
	},

	// Change mailboxes: reset tracking variables and draw a new list.
	selectMailbox: function ( mailbox ) {
		this.lastUIDconst = "";
		this.msgCount = 0;

		// Remove the current mailbox's selection highlight in the listing
		$('mb-'+Lichen.MessageList.getMailbox()).removeClass('mb-active');

		Lichen.action( 'list', 'MessageList', 'setMailbox', [mailbox] );
				
		// Hilight the appropriate row in the message list.
		$('mb-'+Lichen.MessageList.getMailbox()).addClass('mb-active');

		return false;
	}
});


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
		this.lastUIDconst = -1;
		this.mailboxCount = -1;
		this.msgCount     = -1;
		this.openMailboxes = Array();
		this.listCache    = null;
	},

	isMailboxOpen: function ( mailbox ) {
		for ( var i = 0; i < this.openMailboxes.length; i++ ) {
			if ( this.openMailboxes[i] == mailbox ) {
				return i;
			}
		}
		return -1;
	},

	listUpdate: function () {
		Lichen.Messages.fetchMailboxList();
	},

	listUpdateCB: function ( mailboxes ) {
		var msgCounts = mailboxes;
		this.listCache = mailboxes;

		if (this.mailboxCount != msgCounts.length) {
			// A mailbox has been added/removed.
			// Build the interface list.
			this._render( msgCounts );
			this.mailboxCount = msgCounts.length;
		}
		var i = 0;

		for (i = 0; i < msgCounts.length; i++) {
			if ( Lichen.MessageList.getMailbox() == msgCounts[i].fullboxname ) {
				// Do we need to update the list?
				if ( this.lastUIDconst != -1 && this.lastUIDconst != msgCounts[i].uidConst ) {
					Lichen.action( 'list', 'MessageList', 'listUpdate' );
				} else if ( this.msgCount != -1 && this.msgCount != msgCounts[i].messages ) {
					// Refresh the mailbox list. There are new/different messages.
					Lichen.action( 'list', 'MessageList', 'listUpdate' );
				}
				this.lastUIDconst = msgCounts[i].uidConst;
				this.msgCount = msgCounts[i].messages;

				// Update the highlight in the mailbox list, and the page title
				$('mb-'+Lichen.MessageList.getMailbox()).addClass('mb-active');
				document.title = msgCounts[i].mailbox +' ('+msgCounts[i].unseen+' '+_('unread')+', '+msgCounts[i].messages+' '+_('total')+')';
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

		var containerContents = "<li id=\"mb-header\"><span class=\"s-head\">" + _('Mailboxes') + "</span> [<a href=\"#manage-mailboxes\" onclick=\"return Lichen.action('options','OptionsEditor','showEditor',['mailboxes'])\">" + _('edit') + "</a>]</li>";

		var hideUntil = Array();

		for ( var i = 0; i < mailboxes.length; i++ ) {

			if ( hideUntil.length > 0 ) {
				var lastMailbox = hideUntil[hideUntil.length - 1];
				if ( mailboxes[i].fullboxname.substr( 0, lastMailbox.length ) == lastMailbox ) {
					// We're hiding this tree.
					// TODO: Continue is bad style. Don't do it.
					continue;
				} else {
					// Finished our run of hidden mailboxes.
					// Undo our hide trick.
					hideUntil.pop();
				}
			}

			containerContents += "<li id=\"mb-" + mailboxes[i].fullboxname;
			if ( Lichen.MessageList.getMailbox() == mailboxes[i].fullboxname ) {
				containerContents += "\" class=\"mb-active";
			}
			containerContents += "\">";
			
			// Add links to expand this list of mailboxes.
			if ( mailboxes[i].haschildren ) {
				// Righto, this mailbox has children. It needs special handling.
				// Is it open?
				// TODO: Hack: inline style to float the openner thingy to the right.
				// Works on Firefox, will need testing on other browsers. Also, probably should not be here.
				containerContents += " <a href=\"#\" style=\"float:right;\" onclick=\"return Lichen.MailboxList.viewToggle('" + mailboxes[i].fullboxname + "');\">";
				if ( this.isMailboxOpen( mailboxes[i].fullboxname ) != -1 ) {
					// Show the current children.
					containerContents += "<img width=\"8\" height=\"8\" src=\"themes/" + userSettings.theme + "/icons/folder_contract.png\" alt=\"" + _('[contract]') + "\" />";
				} else {
					// Hide any subchildren.
					hideUntil.push( mailboxes[i].fullboxname + mailboxes[i].delimiter );
					containerContents += "<img width=\"8\" height=\"8\" src=\"themes/" + userSettings.theme + "/icons/folder_expand.png\" alt=\"" + _('[expand]') + "\" />";
				}
				containerContents += "</a>";
			}

			// Is the mailbox openable?
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
	},

	viewToggle: function ( mailbox ) {
		// Open or close the mailbox, if it has children.
		var openIndex = this.isMailboxOpen( mailbox );
		if ( openIndex == -1 ) {
			// Not open.
			this.openMailboxes.push( mailbox );
			// Crude: re-render.
			this._render( this.listCache );
		} else {
			// Was open, remove that mailbox.
			this.openMailboxes.splice( openIndex, 1 );
			this._render( this.listCache );
		}
		
		return false;
	},
});


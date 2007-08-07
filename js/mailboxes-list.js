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


// Refresh the mailbox listing and set a timeout to do it again in five minutes
function list_checkCount() {
	Messages.fetchMailboxList();
	refreshTimer = setTimeout( list_checkCount, 5 * 60 * 1000 );
}


function list_buildMailboxList( mailboxes ) {

	$('mailboxes').empty();

	var containerContents = "<li id=\"mb-header\"><span class=\"s-head\">Mailboxes</span> [<a href=\"#manage-mailboxes\" onclick=\"MailboxManager.showManager();return false\">edit</a>]</li>";

	for ( var i = 0; i < mailboxes.length; i++ ) {

		containerContents += "<li id=\"mb-" + mailboxes[i].fullboxname;
		if ( listCurrentMailbox == mailboxes[i].mailbox ) {
			containerContents += "\" class=\"mb-active";
		}
		containerContents += "\">";

		if ( mailboxes[i].selectable ) {
			containerContents += "<a href=\"#\" onclick=\"return if_selectmailbox('" + mailboxes[i].fullboxname + "')\" class=\"mb-click\">";
		}

		// This is a really bad way to indent.
		for ( var j = 0; j < mailboxes[i].folderdepth; j++ ) {
			containerContents += "&nbsp;&nbsp;";
		}

		containerContents += "<span class=\"mailbox\">" + mailboxes[i].mailbox + "</strong> ";


		containerContents += "<span id=\"mb-unread-" + mailboxes[i].fullboxname + "\">";
		if (mailboxes[i].unseen > 0 || userSettings['boxlist_showtotal']) {
			containerContents += "(" + mailboxes[i].unseen;
			if ( userSettings['boxlist_showtotal'] ) containerContents += "/" + mailboxes[i].messages;
			containerContents += ")";
		}
		containerContents += "</span>";

		if ( mailboxes[i].selectable ) {
			containerContents += "</a>";
		}
		containerContents += "</li>";
	}

	containerContents += "</ul>";

	$('mailboxes').setHTML( containerContents );
	mailboxCount = mailboxes.length;
}


function list_countCB( mailboxes ) {
	//var result = if_checkRemoteResult( responseText );
	//if (!result) return;

	var msgCounts = mailboxes;
	mailboxCache = mailboxes; // This is a hack to allow list_createPageBar() to have a mailbox list.

	if (mailboxCount != msgCounts.length) {
		// A mailbox has been added/removed.
		// Build the interface list.
		list_buildMailboxList( msgCounts );
		// And stop...
		return;
	}
	var i = 0;

	for (i = 0; i < msgCounts.length; i++) {
		if ( listCurrentMailbox == msgCounts[i].fullboxname ) {
			// Do we need to update the list?
			if ( lastUIDconst != "" && lastUIDconst != msgCounts[i].uidConst ) {
				list_show();
			} else if ( msgCount != 0 && msgCount != msgCounts[i].messages ) {
				// TODO: this should be a less intrusive "add new
				// message rows", not a complete refresh.
				list_show();
			}
			lastUIDconst = msgCounts[i].uidConst;
			msgCount = msgCounts[i].messages;

			// Update the highlight in the mailbox list, and the page title
			$('mb-'+listCurrentMailbox).addClass('mb-active');
			document.title = msgCounts[i].mailbox +' ('+msgCounts[i].unseen+' unread, '+msgCounts[i].messages+' total)';
		}

		var countresult = "";
		// If non-zero, update the unread messages.
		if ( msgCounts[i].unseen > 0 || userSettings['boxlist_showtotal'] ) {
			countresult = "(" + msgCounts[i].unseen;
			if ( userSettings['boxlist_showtotal'] ) countresult += "/" + msgCounts[i].messages;
			countresult += ")";
		}

		if ( $('mb-unread-'+msgCounts[i].fullboxname) ) {
			$('mb-unread-'+msgCounts[i].fullboxname).setHTML( countresult );
		} else {
			// This mailbox is new or renamed; rebuild the list
			list_buildMailboxList( msgCounts );
			// Stop this function.
			return;
		}
	}
}


// Change mailboxes: reset tracking variables and draw a new list.
function if_selectmailbox( mailbox ) {
	lastUIDconst = "";
	msgCount = 0;

	// Remove the current mailbox's selection highlight in the listing
	$('mb-'+listCurrentMailbox).removeClass('mb-active');

	if ( $('list-wrapper').style.display == 'none' ) {
		// If something other than a message list is being displayed,
		// hide it and show the list instead.
		// TODO: this flickers the old mailbox; instead, it should
		// show loading feedback and display in the callback
		if_hideWrappers();
		if_hideToolbars();
		$('list-wrapper').style.display = 'block';
		$('list-bar').style.display = 'block';
	}

	listCurrentPage = 0;
	listCurrentSearch = "";

	list_show( mailbox, 0 );

	return false;
}

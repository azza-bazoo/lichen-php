/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/message-listings.js - display and handling code for mailbox message lists
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

var MessageLister = new Class({
	initialize: function ( wrapper ) {
		this.parseSort( userSettings['list_sortmode'] );
		this.mailbox = "INBOX";
		this.page    = 0;
		this.search  = "";
		this.wrapper = wrapper;
	},

	setSort: function ( newSort, noUpdate ) {
		// Clear the old sort image
		$('list-sort-'+this.sort).getParent().getElementsByTagName('img')[0].remove();
	
		if ( newSort == this.sort ) {
			// Already sorting by this field, so reverse direction.
			this.sortAsc = !this.sortAsc;
		} else {
			// Set this as sort field, assuming an ascending sort.
			this.sort = newSort;
			this.sortAsc = true;
		}

		// Change the preference value for default sort
		userSettings['list_sortmode'] = this.getSortStr();

		// Trigger an update of the list.
		// The list callback will set the correct sort icon.
		if ( !noUpdate ) {
			this.listUpdate();
		}
	},
	getSortStr: function () {
		return this.sort + ( this.sortAsc ? "" : "_r" );
	},
	parseSort: function ( newSort ) {
		if ( newSort ) {
			if ( newSort.substr( newSort.length - 2, 2  ) == "_r" ) {
				this.sort = newSort.substr( 0, newSort.length - 2 );
				this.sortAsc = false;
			} else {
				this.sort = newSort;
				this.sortAsc = true;
			}
		} else {
			this.sort = "date";
			this.sortAsc = false;
		}
	},

	setSearch: function ( searchTerm, noUpdate ) {
		 if ( this.search != searchTerm ) {
			this.search = searchTerm;
			this.page = 0;
			if ( !noUpdate ) {
				this.listUpdate();
			}
		 }
		 return false;
	},
	getSearch: function () {
		return this.search;
	},

	setMailbox: function ( newMailbox, noUpdate ) {
		if ( this.mailbox != newMailbox ) {
			this.mailbox = newMailbox;
			this.page = 0;
			this.search = "";
		}
		if ( !noUpdate ) {
			this.listUpdate();
		}
		return false;
	},
	getMailbox: function () {
		return this.mailbox;
	},

	setPage: function ( newPage, noUpdate ) {
		if ( this.page != newPage ) {
			if ( newPage >= 0 && newPage <= 100 ) { // TODO: Correctly check bounds.
				this.page = newPage;
				if ( !noUpdate ) {
					this.listUpdate();
				}
			}
		}
		return false;
	},
	nextPage: function () {
		return this.setPage( this.page + 1 );
	},
	previousPage: function () {
		return this.setPage( this.page - 1 );
	},
	firstPage: function () {
		return this.setPage( 0 );
	},
	lastPage: function () {
		return this.setPage ( 100 ); // TODO: Correctly set based on bounds.
	},

	listUpdate: function () {
		Messages.fetchMessageList( this.mailbox, this.search, this.page, this.getSortStr() );
	},
	getPage: function () {
		return this.page;
	},

	listUpdateCB: function ( result ) {
		// Check to see if the data we just got matches what the client was waiting for.
		if ( result.mailbox != this.mailbox ||
		     result.search  != this.search ||
		     result.sort    != this.getSortStr() ||
		     result.thispage.toInt() != this.page ) {
			return;
		}

		// Go ahead and render the messages.
		this._render( result.messages, result );
		
		// Update the mailbox lists
		Messages.fetchMailboxList();
	},

	_render: function ( messages, resultObj ) {
		$( this.wrapper ).empty();
		// TODO: Some other code should be handling this!
		$( this.wrapper ).style.display = 'block';
	
		var tableContents = "";

		tableContents += this._createPageBar( resultObj, true );

		if ( this.search != "" ) {
			tableContents += "<div class=\"list-notification\"><strong>Search results for &#8220;"
				+ this.search + "&#8221;</strong> "
				+ "[<a href=\"#clearsearch\" onclick=\"doQuickSearch(null, true);return false\">clear search</a>]</div>";
		}

		tableContents += "<table>";

		// To work around imperfect CSS layout implementations, we manually
		// calculate the width of the subject column.
		// TODO: respond to the window being resized
		var subjColWidth = window.getWidth() - 515;
		if ( userSettings.list_showsize ) {
			subjColWidth = window.getWidth() - 590;
		}

		tableContents += "<colgroup><col class=\"mcol-checkbox\" /><col class=\"mcol-flag\" /><col class=\"mcol-sender\" /><col class=\"mcol-subject\" style=\"width:" + subjColWidth + "px\" />";
		if ( userSettings.list_showsize ) {
			tableContents += "<col class=\"mcol-size\" />";
		}
		tableContents += "<col class=\"mcol-date\" /></colgroup>";

		tableContents += "<thead><tr class=\"list-sortrow\"><th></th><th></th>";

		tableContents += "<th class=\"list-sortlabel\"><a href=\"#sort-from\" id=\"list-sort-from\" onclick=\"MessageList.setSort('from');return false\">sender</a></th>";
		tableContents += "<th class=\"list-sortlabel\"><a href=\"#sort-subject\" id=\"list-sort-subject\" onclick=\"MessageList.setSort('subject');return false\">subject</a></th>";
		if ( userSettings.list_showsize ) {
			tableContents += "<th class=\"list-sortlabel\"><a href=\"#sort-size\" id=\"list-sort-size\" onclick=\"MessageList.setSort('size');return false\">size</a></th>";
		}
		tableContents += "<th class=\"list-sortlabel\"><a href=\"#sort-date\" id=\"list-sort-date\" onclick=\"MessageList.setSort('date');return false\">date</a></th>";
		tableContents += "</tr></thead><tbody>";

		if ( messages.length == 0 ) {
			tableContents += "<tr><td colspan=\"5\" class=\"list-nothing\">No messages in this mailbox.</td></tr>";
		}

		// Hack: use a better loop later, but this avoids scoping problems.
		for ( var i = 0; i < messages.length; i ++ ) {

			var thisMsg = messages[i];
			var uid = thisMsg.uid;

			var thisRow = "<tr id=\"mr-"+thisMsg.uid+"\" class=\"";

			if ( i % 2 == 1 ) {
				thisRow += "odd";
			} else {
				thisRow += "even";
			}

			if ( thisMsg.readStatus == 'U' ) {
				thisRow += " new";
			}

			thisRow += "\">";

			thisRow += "<td><input type=\"checkbox\" class=\"msg-select\" name=\"s-" + thisMsg.uid + "\" id=\"s-" + thisMsg.uid + "\" value=\"" + thisMsg.uid + "\" onclick=\"MessageList.messageCheckboxClicked();\" /></td>";

			var flagImage = thisMsg.flagged ? "/icons/flag.png" : "/icons/flag_off.png";
			thisRow += "<td><img src=\"themes/" + userSettings.theme + flagImage + "\" id=\"flagged_" + thisMsg.uid + "\" alt=\"\" onclick=\"list_twiddleFlag('" + thisMsg.uid + "', 'flagged', 'toggle')\" title=\"Flag this message\" class=\"list-flag\" /></td>";

			thisRow += "<td class=\"sender\" onclick=\"MessageDisplayer.showMessage('"
				+ this.mailbox + "','" + thisMsg.uid + "')\"";
			if ( thisMsg.fromName == "" ) {
				if ( thisMsg.fromAddr.length > 22 ) {
					// Temporary hack to decide if tooltip is needed
					thisRow += " title=\"" + thisMsg.fromAddr + "\"";
				}
				thisRow += "><div class=\"sender\">" + thisMsg.fromAddr;
			} else {
				thisRow += " title=\"" + thisMsg.fromAddr + "\"><div class=\"sender\">" + thisMsg.fromName;
			}
			thisRow += "</div></td>";

			thisRow += "<td class=\"subject\" onclick=\"MessageDisplayer.showMessage('"+this.mailbox+"','"+thisMsg.uid+"')\"><div class=\"subject\">" + thisMsg.subject;

			if ( userSettings.list_showpreviews ) {
				thisRow += "<span class=\"messagePreview\">" + thisMsg.preview + "</span>";
			}

			thisRow += "</div></td>";

			if ( userSettings.list_showsize ) {
				thisRow += "<td class=\"size\"><div class=\"size\">" + thisMsg.size + "</div></td>";
			}

			thisRow += "<td class=\"date\"><div class=\"date\">" + thisMsg.dateString + "</div></td>";

			thisRow += "</tr>";

			tableContents += thisRow;

		}

		tableContents += "</tbody></table>";
		tableContents += this._createPageBar( resultObj, false );

		$( this.wrapper ).setHTML( tableContents );

		// Set an icon to indicate the current sort.
		var sortImg = null;
		if ( !this.sortAsc ) {
			sortImg = new Element( 'img', { 'class': 'list-sort-marker',
					'src': 'themes/' + userSettings.theme + '/icons/sort_decrease.png' } );
		} else {
			sortImg = new Element( 'img', { 'class': 'list-sort-marker',
					'src': 'themes/' + userSettings.theme + '/icons/sort_incr.png' } );
		}
		// mootools' injectAfter doesn't seem to work here
		$('list-sort-'+this.sort).getParent().adopt( sortImg );
	},

	// Given a parsed result object for a mailbox message listing, generate a
	// string with the text-only toolbar to display above and below the list.
	_createPageBar: function ( resultObj, isTopBar ) {
		var newPageBar = "";

		if ( isTopBar ) {
			newPageBar += "<div class=\"list-header-bar\"><img src=\"themes/" + userSettings.theme + "/top-corner.png\" alt=\"\" class=\"top-corner\" />";
		} else {
			newPageBar += "<div class=\"list-footer-bar\"><img src=\"themes/" + userSettings.theme + "/bottom-corner.png\" alt=\"\" class=\"bottom-corner\" />";
		}

		var thisPage = resultObj.thispage.toInt() + 1;
		var pageCount = resultObj.numberpages.toInt();

		var lastMsgThisPage = thisPage * resultObj.pagesize.toInt();
		if ( lastMsgThisPage > resultObj.numbermessages.toInt() ) {
			lastMsgThisPage = resultObj.numbermessages.toInt();
		}

		newPageBar += "<div class=\"header-left\">";
		newPageBar += "<select onchange=\"MessageList.withSelected(this)\">";
		newPageBar += "<option value=\"noop\" selected=\"selected\">move selected to ...</option>";

		// TODO: Make this work properly - currently using a global var
		// specially created in mailboxes-list.js
		for ( var i = 0; i < mailboxCache.length; i++ ) {
			newPageBar += "<option value=\"move-" + mailboxCache[i].fullboxname + "\">";
			for ( var j = 0; j < mailboxCache[i].folderdepth; j++ ) {
				newPageBar += "-";
			}
			newPageBar += mailboxCache[i].mailbox;
			newPageBar += "</option>";
		}

		newPageBar += "</select>";
		newPageBar += " &nbsp; <input type=\"button\" onclick=\"if_deleteMessages();return false\" value=\"delete\" />";
		newPageBar += " &nbsp; <input type=\"button\" onclick=\"MessageList.withSelected(null, 'flag');return false\" value=\"flag\" />";
		newPageBar += " &nbsp; <input type=\"button\" onclick=\"MessageList.withSelected(null, 'markseen');return false\" value=\"mark read\" /><br />";

		if ( !isTopBar ) {
			newPageBar += "select: <a href='#' onclick='MessageList.selectMessages(\"all\"); return false'>all</a> | ";
			newPageBar += "<a href='#' onclick='MessageList.selectMessages(\"none\"); return false'>none</a> | ";
			newPageBar += "<a href='#' onclick='MessageList.selectMessages(\"invert\"); return false'>invert</a>";
		}

		newPageBar += "</div><div class=\"header-right\">";

		if ( resultObj.numberpages > 1 ) {
		// 	if ( thisPage > 2 ) {
		// 		newPageBar += "<a href=\"#\" onclick=\"MessageList.firstPage(); return false\">first</a> | ";
		// 	}
			if ( thisPage > 1 ) {
				newPageBar += "<a href=\"#\" onclick=\"MessageList.previousPage(); return false\">previous</a> | ";
			}

			newPageBar += "<select onchange=\"MessageList.setPage(this.value);\">";
			var pageSize = resultObj.pagesize.toInt();
			var maxMessages = resultObj.numbermessages.toInt();
			var pageCounter = 0;
			for ( var i = 1; i <= resultObj.numbermessages.toInt(); i += pageSize ) {
				newPageBar += "<option value=\"" + pageCounter + "\"";
				if ( thisPage == (pageCounter + 1) ) newPageBar += " selected=\"selected\"";
				newPageBar += ">" + i + " to ";
				if ( (pageCounter + 1) * pageSize > maxMessages ) {
					newPageBar += maxMessages;
				} else {
					newPageBar += (pageCounter + 1) * pageSize;
				}
				newPageBar += "</option>";
				pageCounter++;
			}
			newPageBar += "</select>";

			// (resultObj.thispage.toInt() * resultObj.pagesize.toInt() + 1) + " to " + lastMsgThisPage
			newPageBar += " of " + resultObj.numbermessages.toInt();

			if ( pageCount - thisPage > 0 ) {
				newPageBar += " | <a href=\"#\" onclick=\"MessageList.nextPage(); return false\">next</a>";
			}
		// 	if ( pageCount - thisPage > 1 ) {
		// 		newPageBar += " | <a href=\"#\" onclick=\"MessageList.lastPage(); return false\">last</a>";
		// 	}
		} else if ( resultObj.numbermessages > 0 && !isTopBar ) {
			newPageBar += "showing 1 to " + resultObj.numbermessages + " of " + resultObj.numbermessages;
		}

		newPageBar += "</div></div>";

		return newPageBar;
	},

	getSelectedMessages: function () {
		var selectedMessages = Array();

		var inputElements = $A( $( this.wrapper ).getElementsByTagName('input') );

		for ( var i = 0; i < inputElements.length; i++ ) {
			if ( inputElements[i].type != 'checkbox' ) continue;
			if ( inputElements[i].checked ) {
				selectedMessages.push( inputElements[i].value );
			}
		}

		return selectedMessages;
	},

	// onclick handler for the checkbox next to "sender" in a message list
	// Currently does nothing but will later highlight active rows, etc.
	messageCheckboxClicked: function () {
		return false;
	},

	selectMessages: function ( mode ) {
		var inputElements = $A( $( this.wrapper ).getElementsByTagName('input') );

		for ( var i = 0; i < inputElements.length; i++ ) {
			if ( inputElements[i].type != 'checkbox' ) continue;
			switch ( mode ) {
				case 'all':
					inputElements[i].checked = true;
					break;
				case 'none':
					inputElements[i].checked = false;
					break;
				case 'invert':
					inputElements[i].checked = !inputElements[i].checked;
					break;
			}
		}
	},

	withSelected: function( sourceBox, textAction ) {
		var selectedMessages = this.getSelectedMessages();

		var action = "noop";
		if ( !sourceBox && textAction ) {
			action = textAction;
		} else {
			action = sourceBox.value;
		}

		var mailbox = "";
		if ( action.substr( 0, 5 ) == "move-" ) {
			mailbox = action.substr( 5 );
			action = "move";
		}

		switch ( action ) {
			case 'noop':
				// Do nothing! Gracefully!
				break;
			case 'markseen':
				list_twiddleFlag( selectedMessages.join(","), 'seen', 'true' );
				break;
			case 'markunseen':
				list_twiddleFlag( selectedMessages.join(","), 'seen', 'false' );
				break;
			case 'flag':
				list_twiddleFlag( selectedMessages.join(","), 'flagged', 'true' );
				break;
			case 'unflag':
				list_twiddleFlag( selectedMessages.join(","), 'flagged', 'false' );
				break;
			case 'move':
				// TODO: if_moveMessages gets a list of messages to work on...
				// meaning that we do this twice if we call this...
				if_moveMessages( mailbox );
				break;
		}

		if ( sourceBox ) {
			sourceBox.selectedIndex = 0;
		}
	},



});

var MessageList = new MessageLister( 'list-wrapper' );

// Replaces the contents of the message list with the results
// for an IMAP "TEXT" search using the current mailbox.
function doQuickSearch( mode, clearSearch ) {
	if ( clearSearch ) {
		$('qsearch').value = "";
	}
	MessageList.setSearch( $('qsearch').value );
	return false;
}

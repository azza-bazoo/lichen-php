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


function list_sort( sortField ) {
	// Clear the old sort image
	if ( listCurrentSort.substr( -2 ) == "_r" ) {
		$( 'list-sort-' + listCurrentSort.substring( 0, listCurrentSort.length-2 ) ).getParent().getElementsByTagName('img')[0].remove()
	} else {
		$('list-sort-'+listCurrentSort).getParent().getElementsByTagName('img')[0].remove();
	}

	if ( sortField == listCurrentSort ) {
		// Already sorting by this field, so reverse direction.
		if ( listCurrentSort.substr( -2 ) == "_r" ) {
			// Already in reverse. Go forward.
			listCurrentSort = sortField;
		} else {
			// Going forward. Reverse it.
			listCurrentSort = sortField + "_r";
		}
	} else {
		// Set this as sort field, assuming an ascending sort.
		listCurrentSort = sortField;
	}

	// Change the preference value for default sort
	userSettings['list_sortmode'] = sortField;

	// Trigger an update of the list.
	// The list callback will set the correct sort icon.
	list_show();
}


function list_show( mailbox, page ) {
	var sendmailbox = "";
	var sendpage = listCurrentPage;
	var searchQuery = listCurrentSearch;
	var sendSort = listCurrentSort;
	if ( mailbox ) {
		sendmailbox = mailbox;
	} else {
		sendmailbox = listCurrentMailbox;
	}
	if ( page != null ) {
		sendpage = page;
	} else {
		sendpage = listCurrentPage;
	}

	// Fetch the data on those messages.
	Messages.fetchMessageList( sendmailbox, listCurrentSearch, sendpage, sendSort );
}


// Activate loading feedback and change the page
// of the currently-displayed mailbox
function list_switchPage( page ) {
	// Disabled for 0.3: doesn't play nice with the caching class
//	if_remoteRequestStart();
	list_show( listCurrentMailbox, page );
}


// Callback function to draw a message listing after
// fetching a JSON string for the mailbox.
function list_showCB( responseText ) {
	//var result = if_checkRemoteResult( responseText );
	//if ( !result ) { return false; }
	result = responseText;

	var messages = result.messages;
	listCurrentMailbox = result.mailbox;
	listCurrentPage = result.thispage.toInt();
	listCurrentSearch = result.search;
	listCurrentSort = result.sort;

	$('list-wrapper').empty();
	$('list-wrapper').style.display = 'block';
	var tableContents = "";

	tableContents += list_createPageBar( result, true );

	if ( result.search != "" ) {
		tableContents += "<div class=\"list-notification\"><strong>Search results for &#8220;"
			+ result.search + "&#8221;</strong> "
			+ "[<a href=\"#clearsearch\" onclick=\"doQuickSearch(null, true);return false\">clear search</a>]</div>";
	}

	tableContents += "<table>";

	tableContents += "<colgroup><col class=\"mcol-checkbox\" /><col class=\"mcol-flag\" /><col class=\"mcol-sender\" /><col class=\"mcol-subject\" style=\"width:" + (window.getWidth()-515) + "px\" /><col class=\"mcol-date\" /></colgroup>";

	tableContents += "<thead><tr class=\"list-sortrow\"><th></th><th></th>";

	tableContents += "<th class=\"list-sortlabel\"><a href=\"#sort-from\" id=\"list-sort-from\" onclick=\"list_sort('from');return false\">sender</a></th>";
	tableContents += "<th class=\"list-sortlabel\"><a href=\"#sort-subject\" id=\"list-sort-subject\" onclick=\"list_sort('subject');return false\">subject</a></th>";
	if ( userSettings.list_showsize ) {
		tableContents += "<th class=\"list-sortlabel\"><a href=\"#sort-size\" id=\"list-sort-size\" onclick=\"list_sort('size');return false\">size</a></th>";
	}
	tableContents += "<th class=\"list-sortlabel\"><a href=\"#sort-date\" id=\"list-sort-date\" onclick=\"list_sort('date');return false\">date</a></th>";
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

		thisRow += "<td><input type=\"checkbox\" class=\"msg-select\" name=\"s-" + thisMsg.uid + "\" id=\"s-" + thisMsg.uid + "\" value=\"" + thisMsg.uid + "\" onclick=\"list_messageCheckboxClicked();\" /></td>";

		var flagImage = thisMsg.flagged ? "/icons/flag.png" : "/icons/flag_off.png";
		thisRow += "<td><img src=\"themes/" + userSettings.theme + flagImage + "\" id=\"flagged_" + thisMsg.uid + "\" alt=\"\" onclick=\"list_twiddleFlag('" + thisMsg.uid + "', 'flagged', 'toggle')\" title=\"Flag this message\" class=\"list-flag\" /></td>";

		thisRow += "<td class=\"sender\" onclick=\"showMsg('"
			+ listCurrentMailbox + "','" + thisMsg.uid + "',0)\"";
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

		thisRow += "<td class=\"subject\" onclick=\"showMsg('"+listCurrentMailbox+"','"+thisMsg.uid+"',0)\">" + thisMsg.subject;

		if ( userSettings.list_showpreviews ) {
			thisRow += "<span class=\"messagePreview\">" + thisMsg.preview + "</span>";
		}

		thisRow += "</td>";

		if ( userSettings.list_showsize ) {
			thisRow += "<td>" + thisMsg.size + "</td>";
		}

		thisRow += "<td class=\"date\"><div class=\"date\">" + thisMsg.dateString + "</div></td>";

		thisRow += "</tr>";

		tableContents += thisRow;

	}

	tableContents += "</tbody></table>";
	tableContents += list_createPageBar( result, false );

	$('list-wrapper').setHTML( tableContents );

	// Set an icon to indicate the current sort.
	if ( listCurrentSort.substr( -2 ) == "_r" ) {
		var sortImg = new Element( 'img', { 'class': 'list-sort-marker',
				'src': 'themes/' + userSettings.theme + '/icons/sort_decrease.png' } );
		$( 'list-sort-' + listCurrentSort.substring( 0, listCurrentSort.length-2 ) ).getParent().adopt( sortImg );
	} else {
		var sortImg = new Element( 'img', { 'class': 'list-sort-marker',
				'src': 'themes/' + userSettings.theme + '/icons/sort_incr.png' } );
		// mootools' injectAfter doesn't seem to work here
		$('list-sort-'+listCurrentSort).getParent().adopt( sortImg );
	}

	// Update the mailbox lists
	Messages.fetchMailboxList();
}


// Given a parsed result object for a mailbox message listing, generate a
// string with the text-only toolbar to display above and below the list.
function list_createPageBar( resultObj, isTopBar ) {
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
	newPageBar += "<select onchange=\"list_withSelected(this)\">";
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
	newPageBar += " &nbsp; <button onclick=\"if_moveMessages('LICHENTRASH');return false\">delete</button>";
	newPageBar += " &nbsp; <button onclick=\"list_withSelected(null, 'flag');return false\">flag</button>";
	newPageBar += " &nbsp; <button onclick=\"list_withSelected(null, 'markseen');return false\">mark read</button><br />";

	if ( !isTopBar ) {
		newPageBar += "select: <a href='#' onclick='list_selectMessages(\"all\"); return false'>all</a> | ";
		newPageBar += "<a href='#' onclick='list_selectMessages(\"none\"); return false'>none</a> | ";
		newPageBar += "<a href='#' onclick='list_selectMessages(\"invert\"); return false'>invert</a>";
	}

	newPageBar += "</div><div class=\"header-right\">";

	if ( resultObj.numberpages > 1 ) {
	// 	if ( thisPage > 2 ) {
	// 		newPageBar += "<a href=\"#\" onclick=\"list_switchPage(0); return false\">first</a> | ";
	// 	}
		if ( thisPage > 1 ) {
			newPageBar += "<a href=\"#\" onclick=\"list_switchPage(" + (thisPage-2) + "); return false\">previous</a> | ";
		}

		newPageBar += "<select onchange=\"list_switchPage(this.value);\">";
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
			newPageBar += " | <a href=\"#\" onclick=\"list_switchPage(" + thisPage + "); return false\">next</a>";
		}
	// 	if ( pageCount - thisPage > 1 ) {
	// 		newPageBar += " | <a href=\"#\" onclick=\"list_switchPage(" + (pageCount-1) + "); return false\">last</a>";
	// 	}
	} else if ( resultObj.numbermessages > 0 && !isTopBar ) {
		newPageBar += "showing 1 to " + resultObj.numbermessages + " of " + resultObj.numbermessages;
	}

	newPageBar += "</div></div>";

	return newPageBar;
}


function list_getSelectedMessages() {
	var selectedMessages = Array();

	var inputElements = $A( $('list-wrapper').getElementsByTagName('input') );

	for ( var i = 0; i < inputElements.length; i++ ) {
		if ( inputElements[i].checked ) {
			selectedMessages.push( inputElements[i].value );
		}
	}

	return selectedMessages;
}


function list_selectMessages( mode ) {
	var inputElements = $A( $('list-wrapper').getElementsByTagName('input') );

	for ( var i = 0; i < inputElements.length; i++ ) {
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
}


function list_withSelected( sourceBox, textAction ) {
	var selectedMessages = list_getSelectedMessages();

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
}


// onclick handler for the checkbox next to "sender" in a message list
// Currently does nothing but will later highlight active rows, etc.
function list_messageCheckboxClicked() {
	return false;
}


// Replaces the contents of the message list with the results
// for an IMAP "TEXT" search using the current mailbox.
function doQuickSearch( mode, clearSearch ) {
	if ( clearSearch ) {
		$('qsearch').value = "";
	}
	Messages.fetchMessageList( listCurrentMailbox, $('qsearch').value, 0, '' );
	return false;
}

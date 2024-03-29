/**

Lichen - AJAX IMAP client
version 0.4 by Hourann Bosci and Daniel Foote
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
	initialize: function ( dataStore, wrapper ) {
		this.parseSort( userSettings.list_sortmode );
		this.mailbox = specialFolders.inbox;
		this.page    = 0;
		this.search  = "";
		this.wrapper = wrapper;
		this.dataStore = dataStore;
		this.numberPages = 1;
		this.messagesOnThisPage = 0;
		this.messagesInMailbox = 0;
		this.unreadMessages = 0;

		this.allSelectedStatus = 0; // 0 = user defined, 1 = all on page, 2 = all in mailbox.
	},

	getWindowTitle: function () {
		var title = this.mailbox + " (";

		title += this.unreadMessages + _(" unread, ");
		title += this.messagesInMailbox + _(" total");
		title += ")";

		return title;
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
		userSettings.list_sortmode = this.getSortStr();

		// Trigger an update of the list.
		// The list callback will set the correct sort icon.
		if ( !noUpdate ) {
			this.listUpdate();
		}
	},
	getSortStr: function () {
		return this.sort + ( this.sortAsc ? "" : "_r" );
	},
	getSort: function () {
		return this.sort;
	},
	getSortAsc: function () {
		return this.sortAsc;
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
			this.allSelectedStatus = 0;
			if ( !noUpdate ) {
				this.listUpdate();
			}
		 }
		 if ( searchTerm == "" ) {
			// HACK: Probably should be doing this somewhere else.
			// Clear the search box if we're clearing the search.
			if ( $('qsearch') ) {
				$('qsearch').value = "";
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
			this.allSelectedStatus = 0;
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
			if ( newPage >= 0 && newPage <= (this.numberPages - 1) ) { 
				if ( this.allSelectedStatus != 2 ) {
					this.allSelectedStatus = 0;
				}
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
		return this.setPage ( this.numberPages );
	},

	listUpdate: function () {
		Lichen.busy();
		this.dataStore.fetchMessageList( this.mailbox, this.search, this.page, this.getSortStr() );
	},
	getPage: function () {
		return this.page;
	},

	listUpdateCB: function ( result ) {

		Lichen.notbusy();

		// Check to see if the data we just got matches what the client was waiting for.
		if ( result.mailbox  != this.mailbox ||
		     result.search   != this.search ||
		     result.sort     != this.getSortStr() ||
		     result.thispage != this.page ) {
			return;
		}

		// Is this a non-existant page? Likely because we moved all the messages from
		// this page.
		// View the last page.
		if ( result.thispage >= result.numberpages || result.thispage == -1 ) {
			this.setPage( result.numberpages - 1 );
			return;
		}

		// This stores the number of pages. This could cause a trip up,
		// if between updates enough messages show up to suggest that there
		// is an additional page. Wow, that was a bad description.
		this.numberPages = result.numberpages;
		
		if ( result.unreadmessages != null ) {
			this.unreadMessages = result.unreadmessages;
		}

		// Go ahead and render the messages.
		this._render( result.messages, result );
		
		// Update the mailbox lists
		this.dataStore.fetchMailboxList();
	},

	_render: function ( messages, resultObj ) {
		// Make a note of what was selected first...
		var lastSelected = this.getSelectedMessages();

		$( this.wrapper ).empty();

		// ****************************************************
		// IF YOU CHANGE THIS CODE, CHANGE THE MATCHING CODE IN
		// include/htmlrender.php AS WELL.
	
		var tableContents = "";

		tableContents += this._createPageBar( resultObj, true );

		if ( this.search != "" ) {
			// TODO: This string below is not really properly translatable.
			tableContents += "<div class=\"list-notification\"><strong>" + _("Found ") + resultObj.numbermessages
				+ _(' results for') + " &#8220;"
				+ this.search + "&#8221;</strong> "
				+ "[<a href=\"#clearsearch\" onclick=\"return Lichen.action('list','MessageList','setSearch',[''])\">"
				+ _('clear search') + "</a>]</div>";
			this.messagesInMailbox = resultObj.numbermessages;
		} else {
			this.messagesInMailbox = resultObj.mailboxmessages;
		}
		
		this.messagesOnThisPage = messages.length;

		var allNotificationHtml = this.allSelectedDisplay( true );
		tableContents += "<div class=\"list-notification\" id=\"select-all-notification\" ";
		if ( allNotificationHtml == "" ) {
			tableContents += "style=\"display: none\">";
		} else {
			tableContents += ">" + allNotificationHtml;
		}
		tableContents += "</div>";

		tableContents += "<table id=\"list-data-tbl\" class=\"messagelist\">";

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

		tableContents += "<thead><tr class=\"list-sortrow\">";

		tableContents += "<th><input type=\"checkbox\" id=\"list-check-main\" value=\"0\" onclick=\"Lichen.MessageList.selectMessages(0)\" /></th><th></th>";

		tableContents += "<th class=\"list-sortlabel\"><a href=\"#sort-from\" id=\"list-sort-from\" onclick=\"Lichen.MessageList.setSort('from');return false\">" + _('sender') + "</a></th>";
		tableContents += "<th class=\"list-sortlabel\"><a href=\"#sort-subject\" id=\"list-sort-subject\" onclick=\"Lichen.MessageList.setSort('subject');return false\">" + _('subject') + "</a></th>";
		if ( userSettings.list_showsize ) {
			tableContents += "<th class=\"list-sortlabel\"><a href=\"#sort-size\" id=\"list-sort-size\" onclick=\"Lichen.MessageList.setSort('size');return false\">" + _('size') + "</a></th>";
		}
		tableContents += "<th class=\"list-sortlabel\"><a href=\"#sort-date\" id=\"list-sort-date\" onclick=\"Lichen.MessageList.setSort('date');return false\">" + _('date') + "</a></th>";
		tableContents += "</tr></thead><tbody>";

		if ( messages.length == 0 ) {
			tableContents += "<tr><td colspan=\"5\" class=\"list-nothing\">" + _('No messages in this mailbox.') + "</td></tr>";
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

			if ( this.allSelectedStatus == 2 ) {
				thisRow += " active";
			}

			thisRow += "\">";

			thisRow += "<td><input type=\"checkbox\" class=\"msg-select\" name=\"s-" + thisMsg.uid +
				"\" id=\"s-" + thisMsg.uid + "\" value=\"" + thisMsg.uid +
				"\" onclick=\"Lichen.MessageList.messageCheckboxClicked(this);\" /></td>";

			var flagImage = thisMsg.flagged ? "/icons/flag.png" : "/icons/flag_off.png";
			thisRow += "<td><img src=\"themes/" + userSettings.theme + flagImage + "\" id=\"f-" + thisMsg.uid + "\" alt=\"\" onclick=\"Lichen.MessageList.twiddleFlag('" + thisMsg.uid + "', 'flagged', 'toggle')\" title=\"Flag this message\" class=\"list-flag\" /></td>";

			thisRow += "<td class=\"sender\" onclick=\"Lichen.action('display','MessageDisplayer','showMessage',['"
				+ this.mailbox + "','" + thisMsg.uid + "'])\"";
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

			thisRow += "<td class=\"subject\" onclick=\"Lichen.action('display','MessageDisplayer','showMessage',['"+this.mailbox+"','"+thisMsg.uid+"'])\"><div class=\"subject\">" + thisMsg.subject;

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

		// Reset any previously-selected messages.
		if ( this.allSelectedStatus == 2 ) {
			// All messages in mailbox were selected...
			this.selectMessages(7);
		} else {
			// Only some messages were selected.
			for ( var i = 0; i < lastSelected.length; i++ ) {
				var checkBox = $( 's-' + lastSelected[i] );
				if ( checkBox ) {
					checkBox.checked = true;
				}
			}
		}
	},

	// Given a parsed result object for a mailbox message listing, generate a
	// string with the text-only toolbar to display above and below the list.
	_createPageBar: function ( resultObj, isTopBar ) {
		var newPageBar = "";
		
		// ****************************************************
		// IF YOU CHANGE THIS CODE, CHANGE THE MATCHING CODE IN
		// include/htmlrender.php AS WELL.

		if ( isTopBar ) {
			newPageBar += "<div class=\"list-header-bar\"><img src=\"themes/" + userSettings.theme + "/top-corner.png\" alt=\"\" class=\"top-corner\" />";
		} else {
			newPageBar += "<div class=\"list-footer-bar\"><img src=\"themes/" + userSettings.theme + "/bottom-corner.png\" alt=\"\" class=\"bottom-corner\" />";
		}

		var thisPage = resultObj.thispage + 1;
		var pageCount = resultObj.numberpages;

		var lastMsgThisPage = thisPage * resultObj.pagesize;
		if ( lastMsgThisPage > resultObj.numbermessages ) {
			lastMsgThisPage = resultObj.numbermessages;
		}

		newPageBar += "<div class=\"header-left\">";
		newPageBar += "<select onchange=\"Lichen.MessageList.withSelected(this)\">";
		newPageBar += "<option value=\"noop\" selected=\"selected\">" + _('move selected to ...') + "</option>";

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
				newPageBar += "<option value=\"move-" + mailboxes[i].fullboxname + "\">";
				for ( var j = 0; j < mailboxes[i].folderdepth; j++ ) {
					newPageBar += "-";
				}
				newPageBar += mailboxes[i].mailbox;
				newPageBar += "</option>";
			}
		}

		newPageBar += "</select>";
		newPageBar += " &nbsp; <input type=\"button\" onclick=\"Lichen.MessageList.deleteMessages();return false\" value=\"" + _('delete') + "\" />";
		newPageBar += " &nbsp; <input type=\"button\" onclick=\"Lichen.MessageList.withSelected(null, 'flag');return false\" value=\"" + _('flag') + "\" />";
		newPageBar += " &nbsp; <input type=\"button\" onclick=\"Lichen.MessageList.withSelected(null, 'markseen');return false\" value=\"" + _('mark read') + "\" /><br />";

		if ( !isTopBar ) {
			newPageBar += "select: <a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(1);return false\">" + _('all') + "</a> | ";
			newPageBar += "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(2);return false\">" + _('none') + "</a> | ";
			newPageBar += "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(3);return false\">" + _('read') + "</a> | ";
			newPageBar += "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(4);return false\">" + _('unread') + "</a> | ";
			newPageBar += "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(5);return false\">" + _('flagged') + "</a> | ";
			newPageBar += "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(6);return false\">" + _('unflagged') + "</a> | ";
			newPageBar += "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(0);return false\">" + _('invert') + "</a>";
		}

		newPageBar += "</div><div class=\"header-right\">";

		if ( resultObj.numberpages > 1 ) {
			var lowerPageLabel = _("previous");
			var upperPageLabel = _("next");

			if ( this.sort == "date" ) {
				if ( this.sortAsc ) {
					lowerPageLabel = _("earlier");
					upperPageLabel = _("later");
				} else {
					lowerPageLabel = _("later");
					upperPageLabel = _("earlier");
				}
			}

		// 	if ( thisPage > 2 ) {
		// 		newPageBar += "<a href=\"#\" onclick=\"MessageList.firstPage(); return false\">first</a> | ";
		// 	}
			if ( thisPage > 1 ) {
				newPageBar += "<a href=\"#\" onclick=\"Lichen.MessageList.previousPage(); return false\">" + lowerPageLabel + "</a> | ";
			}

			newPageBar += "<select onchange=\"Lichen.MessageList.setPage(this.value);\">";
			var pageSize = resultObj.pagesize;
			var maxMessages = resultObj.numbermessages;
			var pageCounter = 0;
			for ( var i = 1; i <= resultObj.numbermessages; i += pageSize ) {
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

			// (resultObj.thispage * resultObj.pagesize + 1) + " to " + lastMsgThisPage
			newPageBar += " of " + resultObj.numbermessages;

			if ( pageCount - thisPage > 0 ) {
				newPageBar += " | <a href=\"#\" onclick=\"Lichen.MessageList.nextPage(); return false\">" + upperPageLabel + "</a>";
			}
		// 	if ( pageCount - thisPage > 1 ) {
		// 		newPageBar += " | <a href=\"#\" onclick=\"MessageList.lastPage(); return false\">last</a>";
		// 	}
		} else if ( resultObj.numbermessages > 0 && !isTopBar ) {
			newPageBar += _("showing 1 to ") + resultObj.numbermessages + _(" of ") + resultObj.numbermessages;
		}

		newPageBar += "</div></div>";

		return newPageBar;
	},

	getSelectedMessages: function () {
		var selectedMessages = new Array();

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
	messageCheckboxClicked: function ( checkbox ) {
		// Unset the all-messages-checked status.
		this.allSelectedStatus = 0;
		this.allSelectedDisplay();

		// Highlight the active row if checked.
		this.setRowActive( checkbox );

		return false;
	},

	setRowActive: function( checkbox ) {
		var tableRow = $( 'mr-' + checkbox.value );
		if ( checkbox.checked ) {
			tableRow.addClass( 'active' );
		} else {
			tableRow.removeClass( 'active' );
		}
	},

	allSelectedDisplay: function ( htmlMode ) {
		var linkHtml = "";
		if ( this.allSelectedStatus == 0 ) {
			// Hide the appropriate area.
			if ( !htmlMode ) {
				$( 'select-all-notification' ).setStyle( 'display', 'none' );
			}
		} else {
			// TODO: The strings below will be impossible to translate; because languages
			// other than english might need to rearrange the numbers or the word order.
			if ( this.allSelectedStatus == 1 ) {
				linkHtml += _("Only the ") + "<b>" + this.messagesOnThisPage + "</b>" + _(" messages on this page have been selected. ");
				linkHtml += "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(7);\">";
				if ( this.getSearch() == "" ) {
					linkHtml += _("Select all messages in this mailbox");
				} else {
					linkHtml += _("Select all messages from this search");
				}
				linkHtml += "</a>.";
			} else {
				linkHtml += _("All ") + "<b>" + this.messagesInMailbox + "</b>" + _(" messages ");
				if ( this.getSearch() == "" ) {
					linkHtml += _("in this mailbox have been selected. ");
				} else {
					linkHtml += _("from this search have been selected. ");
				}
				linkHtml += "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(1);\">";
				linkHtml += _("Select only the messages on this page");
				linkHtml += "</a>.";
			}

			if ( !htmlMode ) {
				$( 'select-all-notification' ).setHTML( linkHtml );
				$( 'select-all-notification' ).setStyle( 'display', 'block' );
			}
		}

		return linkHtml;
	},

	allSelectedServerNotify: function ( forceoff ) {
		if ( this.allSelectedStatus == 2 ) {
			Lichen.Messages.nextOperationOnAllMessages( this.getMailbox(), this.getSearch() );
		} else {
			Lichen.Messages.clearOperationOnAllMessages();
		}
		if ( forceoff ) {
			this.allSelectedStatus = 0;
			Lichen.Messages.clearOperationOnAllMessages();
		}
	},

	selectMessages: function ( mode ) {
		var inputElements = $A( $('list-data-tbl').getElementsByTagName('input') );

		this.allSelectedStatus = 0;

		if ( mode == 1 ) {
			// Select all on the current page.
			this.allSelectedStatus = 1;
		} else if ( mode == 7 ) {
			// Select all in mailbox.
			this.allSelectedStatus = 2;
		}

		for ( var i = 1; i < inputElements.length; i++ ) {
			var thisMsgUID = inputElements[i].value;

			switch ( mode ) {
				case 1:		// all
				case 7:
					inputElements[i].checked = true;
					break;
				case 2:		// none
					inputElements[i].checked = false;
					break;
				case 3:		// read
					// TODO: check from message data, not display style
					inputElements[i].checked = !$('mr-'+thisMsgUID).hasClass('new');
					break;
				case 4:		// unread
					inputElements[i].checked = $('mr-'+thisMsgUID).hasClass('new');
					break;
				case 5:		// flagged
					inputElements[i].checked =
						!( $('f-'+thisMsgUID).src.substring( $('f-'+thisMsgUID).src.length-12 ) == 'flag_off.png' );
					break;
				case 6:		// unflagged
					inputElements[i].checked =
						( $('f-'+thisMsgUID).src.substring( $('f-'+thisMsgUID).src.length-12 ) == 'flag_off.png' );
					break;
				default:	// invert
					inputElements[i].checked = !inputElements[i].checked;
					break;
			}
			
			this.setRowActive( inputElements[i] );
		}

		this.allSelectedDisplay();
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
		
		this.allSelectedServerNotify();

		switch ( action ) {
			case 'noop':
				// Do nothing! Gracefully!
				break;
			case 'markseen':
				this.twiddleFlag( selectedMessages.join(","), 'seen', 'true' );
				break;
			case 'markunseen':
				this.twiddleFlag( selectedMessages.join(","), 'seen', 'false' );
				break;
			case 'flag':
				this.twiddleFlag( selectedMessages.join(","), 'flagged', 'true' );
				break;
			case 'unflag':
				this.twiddleFlag( selectedMessages.join(","), 'flagged', 'false' );
				break;
			case 'move':
				// TODO: moveMessages gets a list of messages to work on...
				// meaning that we do this twice if we call this...
				this.moveMessages( mailbox );
				break;
		}

		if ( sourceBox ) {
			sourceBox.selectedIndex = 0;
		}
	},


	twiddleFlag: function( uid, flag, state ) {
		Lichen.Messages.twiddleFlag( this.getMailbox(), uid, flag, state, this.twiddleFlagCB.bind( this ) );
	},

	twiddleFlagCB: function( result ) {
		var uidsAffected = result.uid.split(',');

		if ( result.flag == 'seen' ) {
			for (var i = 0; i < uidsAffected.length; i++ ) {
				var messageRow = $('mr-' + uidsAffected[i]);
				if ( messageRow ) {
					if ( !result.state ) {
						messageRow.addClass('new');
					} else {
						messageRow.removeClass('new');
					}
				}
			}
		}

		if ( result.flag == 'flagged' ) {
			for ( var i = 0; i < uidsAffected.length; i++ ) {
				var flagIcon = $( 'f-' + uidsAffected[i] );
				if ( flagIcon ) {
					if ( result.state ) {
						flagIcon.src = 'themes/' + userSettings.theme + '/icons/flag.png';
					} else {
						flagIcon.src = 'themes/' + userSettings.theme + '/icons/flag_off.png';
					}
				}
			}
		}

		if ( uidsAffected.length > 1 ) {
			Lichen.Flash.flashMessage( _("Updated ") + uidsAffected.length + _(" messages.") );
		}

		// Update the flag indicator ...?
		//alert( "Set " + result.uid + " " + result.flag + " to " + result.state );
	},

	moveMessages: function( target ) {
		var selectedMessages = this.getSelectedMessages();
		var selectedCount = selectedMessages.length;
		selectedMessages = selectedMessages.join(",");
		
		this.allSelectedServerNotify();

		Lichen.Messages.moveMessage( this.getMailbox(), target, selectedMessages, this.moveMessagesCB.bind( this ) );
		
		this.allSelectedServerNotify( true );
	},

	// Send AJAX request to delete the selected messages (which is a special case of moving)
	deleteMessages: function() {
		var selectedMessages = this.getSelectedMessages();
		var selectedCount = selectedMessages.length;
		selectedMessages = selectedMessages.join(",");
		
		this.allSelectedServerNotify();

		Lichen.Messages.deleteMessage( this.getMailbox(), selectedMessages, this.moveMessagesCB.bind( this ) );
		
		this.allSelectedServerNotify( true );
	},

	moveMessagesCB: function( result ) {

		Lichen.Flash.flashMessage( result.message );

		// Update the lists...
		this.listUpdate();
	},
		
	/* Testing code; not in use */
	threadTest: function () {
		new Ajax( 'ajax.php', {
			postBody: "request=getThreadedList&mailbox=" + encodeURIComponent(this.getMailbox()),
			onComplete: this.threadTestCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();
	},

	threadTestCB: function( responseText ) {
		var result = if_checkRemoteResult( responseText );
		if (!result) return;

		$(this.wrapper).setHTML( result.htmlFragment );
	}
});


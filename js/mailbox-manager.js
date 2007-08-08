/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/mailbox-manager.js - display & handling code to move/rename/delete mailboxes
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


var MailboxManagerClass = new Class({

	initialize: function () {
		this.mailboxCache = null;
	},

// 	showManager: function () {
// 		if_remoteRequestStart();
// 		new Ajax( 'ajax.php', {
// 			postBody: 'request=mailboxManager',
// 			onComplete : this.showManagerCB.bind( this ),
// 			onFailure : function( responseText ) {
// 				if_remoteRequestFailed( responseText );
// 			}
// 			} ).request();
// 	},

// 	showManagerCB: function ( responseText ) {
// 		var result = if_checkRemoteResult( responseText );
// 		if (!result) return;
//
// 		var mailboxes = result.mailboxes;
//
// 		// Build the HTML.
// 		var managerHtml = "<div>";
// 		managerHtml += "<h3>Mailbox Manager</h3>";
// 		managerHtml += "<table border=\"0\">";
// 		managerHtml += "<tr id=\"mbm-row-\"><td><div id=\"mbm-namearea-\">[Top Level]</div></td>";
// 		managerHtml += "<td><div id=\"mbm-buttonarea-\">" + this._makeButtons( '', '', true ) + "</div></td></tr>";
// 		for ( var i = 0; i < mailboxes.length; i++ ) {
// 			var thisMailbox = mailboxes[i];
// 			managerHtml += "<tr id=\"mbm-row-" + thisMailbox.fullboxname + "\"><td>";
// 			managerHtml += "<div id=\"mbm-namearea-" + thisMailbox.fullboxname + "\">";
// 			for ( var j = 0; j < thisMailbox.folderdepth; j++ ) {
// 				managerHtml += "-"; // Poor man's indenting.
// 			}
// 			managerHtml += thisMailbox.mailbox;
// 			managerHtml += "</div></td><td><div id=\"mbm-buttonarea-" + thisMailbox.fullboxname + "\">";
// 			managerHtml += this._makeButtons( thisMailbox.fullboxname, thisMailbox.mailbox ) + "</div></td></tr>";
// 		}
// 		managerHtml += "</table>";
//
// 		managerHtml += "<a href=\"#\" onclick=\"MailboxManager.closeManager(); return false\">Close Pane</a>";
// 		managerHtml += "</div>";
//
// 		this.mailboxCache = result.mailboxes;
//
// 		$('opts-wrapper').setHTML(managerHtml);
// 		$('opts-wrapper').setStyle('display', 'block');
// 	},

// 	_makeButtons: function ( fullboxname, displayname, toplevel ) {
// 		// Internal function to generate buttons to work with each mailbox.
// 		// TODO: Assumes that its instance is called "MailboxManager"
// 		var buttonsHtml = "[<a href=\"#\" onclick=\"MailboxManager.newChild('" + fullboxname + "'); return false\">add</a>] ";
// 		if ( !toplevel ) {
// 			buttonsHtml += "[<a href=\"#\" onclick=\"MailboxManager.renameInline('" + fullboxname + "', '" + displayname + "'); return false\">edit</a>] ";
// 			buttonsHtml += "[<a href=\"#\" onclick=\"MailboxManager.changeParentInline('" + fullboxname + "', '" + displayname + "'); return false\">move</a>] ";
// 			buttonsHtml += "[<a href=\"#\" onclick=\"MailboxManager.mailboxDelete('" + fullboxname + "', '" + displayname + "'); return false\">delete</a>] ";
// 		}
//
// 		return buttonsHtml;
// 	},

// 	closeManager: function () {
// 		$('opts-wrapper').setStyle('display', 'none');
// 		list_checkCount();
// 	},

	renameInline: function ( fullboxname, boxname ) {
		// Replace the area with the name with an input control with the name,
		// plus a button to submit it.
		if ( $('mbm-namearea-' + fullboxname) ) {
			var editHtml = "<input id=\"mbm-rename-" + fullboxname + "\" type=\"text\" size=\"20\" value=\"" + boxname + "\" />";
			editHtml += "<button onclick=\"MailboxManager.renameDone('" + fullboxname + "', '" + boxname + "');return false\">save</button> <button onclick=\"MailboxManager.renameCancel('" + boxname + "');return false\">cancel</button> ";

			$('mbm-namearea-' + fullboxname).setHTML( editHtml );

			var renameBox = $('mbm-rename-' + fullboxname);
			if ( renameBox ) {
				renameBox.focus();
			}
		}
	},

	renameDone: function ( fullboxname, boxname ) {
		// The inline rename is completed, tell the server.

		var nameBox = $('mbm-rename-' + fullboxname);
		if ( nameBox ) {
			var newname = nameBox.value;

			if ( newname && newname != "" ) {

				if ( newname == boxname ) {
					// No change - just hide the box.
					this.serverActionCB( {action: 'rename', mailbox1: fullboxname, mailbox2: fullboxname} );
				} else {
					newname = fullboxname.substr(0, fullboxname.length - boxname.length) + newname;

					if_remoteRequestStart();
					new Ajax( 'ajax.php', {
						postBody: 'request=mailboxAction&action=rename&mailbox1=' + encodeURIComponent(fullboxname) +
							'&mailbox2=' + encodeURIComponent(newname),
						onComplete : this.serverActionCB.bind( this ),
						onFailure : function( responseText ) {
							if_remoteRequestFailed( responseText );
						}
						} ).request();
				}
			}
		}
	},

	renameCancel: function ( boxname ) {
		// Cancel an inline rename

		var nameBox = $('mbm-rename-' + fullboxname);
		if ( nameBox ) {
			this.serverActionCB( {action: 'rename', mailbox1: fullboxname, mailbox2: fullboxname} );
		}
	},

	mailboxDelete: function ( fullboxname, boxname ) {
		if ( confirm("Are you sure you want to delete '" + boxname + "'? This action is irreversable.") ) {
			if_remoteRequestStart();
			new Ajax( 'ajax.php', {
				postBody: 'request=mailboxAction&action=delete&mailbox1=' + encodeURIComponent(fullboxname),
				onComplete : this.serverActionCB.bind( this ),
				onFailure : function( responseText ) {
					if_remoteRequestFailed( responseText );
				}
				} ).request();
		}
	},

	newChild: function ( fullboxname ) {
		// Append a new entry form to the area.
		// plus a button to submit it.

		var nameArea = $('mbm-namearea-' + fullboxname);
		var buttonArea = $('mbm-buttonarea-' + fullboxname);

		if ( nameArea && buttonArea ) {
			var childHtml = "<div id=\"mbm-newchild-wrapper-" + fullboxname + "\">";
			childHtml += "New Subfolder: <input id=\"mbm-newchild-" + fullboxname + "\" type=\"text\" size=\"20\" />";
			childHtml += "<button onclick=\"MailboxManager.newChildSubmit('" + fullboxname + "'); return false\">Add</button>";
			childHtml += "<button onclick=\"MailboxManager.newChildCancel('" + fullboxname + "'); return false\">Cancel</button>";
			childHtml += "</div>";

			nameArea.innerHTML += childHtml;
			buttonArea.setStyle( 'display', 'none' );

			var newChildBox = $('mbm-newchild-' + fullboxname);
			if ( newChildBox ) {
				newChildBox.focus();
			}
		}
	},

	newChildSubmit: function ( fullboxname ) {
		// Ask the server to create the new mailbox.
		var childMailbox = $('mbm-newchild-' + fullboxname);

		if ( childMailbox && childMailbox.value != "" ) {
			if_remoteRequestStart();
			new Ajax( 'ajax.php', {
				postBody: 'request=mailboxAction&action=create&mailbox1=' + encodeURIComponent(fullboxname) +
					'&mailbox2=' + encodeURIComponent(childMailbox.value),
				onComplete : this.serverActionCB.bind( this ),
				onFailure : function( responseText ) {
					if_remoteRequestFailed( responseText );
				}
				} ).request();
		}
	},

	newChildCancel: function ( fullboxname ) {
		// Just remove the form.
		var childArea = $('mbm-newchild-wrapper-' + fullboxname);
		var buttonArea = $('mbm-buttonarea-' + fullboxname);

		if ( childArea ) {
			childArea.remove();
		}
		if ( buttonArea ) {
			buttonArea.setStyle( 'display', 'block' );
		}
	},

	changeParentInline: function ( fullboxname, boxname ) {
		// Show a drop down allowing us to change the parent.

		var nameArea = $('mbm-namearea-' + fullboxname);
		var buttonArea = $('mbm-buttonarea-' + fullboxname);

		if ( nameArea && buttonArea ) {
			var childHtml = "<div id=\"mbm-changeparent-wrapper-" + fullboxname + "\">";
			childHtml += "Move to subfolder of: ";
			childHtml += "<select id=\"mbm-changeparent-" + fullboxname + "\">";
			childHtml += "<option value=\"\">[Top Level]</option>";
			for ( var i = 0; i < this.mailboxCache.length; i++ ) {
				var thisMailbox = this.mailboxCache[i];
				childHtml += "<option value=\"" + thisMailbox.fullboxname + "\"";
				if ( boxname == thisMailbox.mailbox ) {
					childHtml += " selected=\"selected\">";
				} else {
					childHtml += ">";
				}
				for ( var j = 0; j < thisMailbox.folderdepth; j++ ) {
					childHtml += "-";
				}
				childHtml += thisMailbox.mailbox + "</option>";
			}
			childHtml += "</select>";

			childHtml += "<button onclick=\"MailboxManager.changeParentSubmit('" + fullboxname + "', '" + boxname + "'); return false\">Move</button>";
			childHtml += "<button onclick=\"MailboxManager.changeParentCancel('" + fullboxname + "', '" + boxname + "'); return false\">Cancel</button>";
			childHtml += "</div>";

			nameArea.innerHTML += childHtml;
			buttonArea.setStyle( 'display', 'none' );
		}
	},

	changeParentSubmit: function ( fullboxname, boxname ) {
		// Ask the server to create the new mailbox.
		var newParentMailbox = $('mbm-changeparent-' + fullboxname);

		if ( newParentMailbox && newParentMailbox.value != boxname ) {
			if_remoteRequestStart();
			new Ajax( 'ajax.php', {
				postBody: 'request=mailboxAction&action=move&mailbox1=' + encodeURIComponent(fullboxname) +
					'&mailbox2=' + encodeURIComponent(newParentMailbox.value),
				onComplete : this.serverActionCB.bind( this ),
				onFailure : function( responseText ) {
					if_remoteRequestFailed( responseText );
				}
				} ).request();
		}
	},

	changeParentCancel: function ( fullboxname, boxname ) {
		// Just remove the form.
		var childArea = $('mbm-changeparent-wrapper-' + fullboxname);
		var buttonArea = $('mbm-buttonarea-' + fullboxname);

		if ( childArea ) {
			childArea.remove();
		}
		if ( buttonArea ) {
			buttonArea.setStyle( 'display', 'block' );
		}
	},

	serverActionCB: function ( responseText ) {
		var result = if_checkRemoteResult( responseText );
		if (!result) return;

		switch ( result.action ) {
			case 'rename':
				// Remove the name area, show the buttons.
				// Then change the IDs of the various parts.
				var nameArea = $('mbm-namearea-' + result.mailbox1);
				var buttonArea = $('mbm-buttonarea-' + result.mailbox1);
				var nameRow = $('mbm-row-' + result.mailbox1);

				var delimiter = this.mailboxCache[0]['delimiter'];
				var startIndex = result.mailbox2.lastIndexOf( delimiter );
				var mailboxName = result.mailbox2;
				var mailboxDepth = result.mailbox2.split( delimiter );
				mailboxDepth = mailboxDepth.length - 1;
				if ( startIndex != -1 ) {
					mailboxName = result.mailbox2.substr( startIndex + 1 );
				}

				if ( nameRow ) {
					nameRow.id = 'mbm-row-' + result.mailbox2;
				}
				if ( nameArea ) {
					var nameAreaHtml = "";
					for ( var j = 0; j < mailboxDepth; j++ ) {
						nameAreaHtml += "-";
					}
					nameAreaHtml += mailboxName;
					nameArea.setHTML(nameAreaHtml);
					nameArea.id = "mbm-namearea-" + result.mailbox2;
				}
				if ( buttonArea ) {
					buttonArea.setHTML( this._makeButtons( result.mailbox2, mailboxName ) );
					buttonArea.id = "mbm-buttonarea-" + result.mailbox2;
					buttonArea.setStyle( 'display', 'block' );
				}
				break;
			case 'delete':
				var nameRow = $('mbm-row-' + result.mailbox1);
				if ( nameRow ) {
					nameRow.remove();
				}
				break;
			case 'create':
			case 'move':
				// Refresh the whole list.
				this.showManager();
				break;
		}

		if ( result.mailboxes ) {
			this.mailboxCache = result.mailboxes;
		}
	}
});

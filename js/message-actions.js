/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/message-actions.js - functions to move, delete, or set flags on messages
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


function list_twiddleFlag( uid, flag, state ) {
	var postbody = "request=setFlag";
	postbody += "&flag=" + encodeURIComponent( flag );
	postbody += "&mailbox=" + MessageList.getMailbox();
	postbody += "&uid=" + encodeURIComponent( uid );
	if ( state ) {
		postbody += "&state=" + state;
	}
	new Ajax( 'ajax.php', {
		postBody: postbody,
		onComplete : function ( responseText ) {
			list_twiddleFlagCB( responseText );
		},
		onFailure : function( responseText ) {
			if_remoteRequestFailed( responseText );
		}
		} ).request();
}


function list_twiddleFlagCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

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
			var flagIcon = $( result.flag + '_' + uidsAffected[i] );
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
		Flash.flashMessage( "Updated " + uidsAffected.length + " messages." );
	}

	// Update the flag indicator ...?
	//alert( "Set " + result.uid + " " + result.flag + " to " + result.state );
}


function if_moveMessages( target ) {
	var selectedMessages = MessageList.getSelectedMessages();
	var selectedCount = selectedMessages.length;
	selectedMessages = selectedMessages.join(",");

	new Ajax( 'ajax.php', {
		postBody: 'request=moveMessage&mailbox=' + encodeURIComponent(MessageList.getMailbox()) +
			'&destbox=' + encodeURIComponent(target) +
			'&uid=' + encodeURIComponent(selectedMessages),
		onComplete : function( responseText ) {
			if_moveMessagesCB( responseText );
		},
		onFailure : function( responseText ) {
			if_remoteRequestFailed( responseText );
		}
		} ).request();
}


// Send AJAX request to delete the selected messages (which is a special case of moving)
function if_deleteMessages() {
	var selectedMessages = MessageList.getSelectedMessages();
	var selectedCount = selectedMessages.length;
	selectedMessages = selectedMessages.join(",");

	new Ajax( 'ajax.php', {
		postBody: 'request=deleteMessage&mailbox=' + encodeURIComponent(MessageList.getMailbox()) +
			'&uid=' + encodeURIComponent(selectedMessages),
		onComplete : function( responseText ) {
			if_moveMessagesCB( responseText );
		},
		onFailure : function( responseText ) {
			if_remoteRequestFailed( responseText );
		}
		} ).request();
}


function if_moveMessagesCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	Flash.flashMessage( result.message );

	// Update the lists...
	MessageList.listUpdate();
}

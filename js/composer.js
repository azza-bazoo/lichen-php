/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/composer.js - new message composer
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


// Show the form for a new message. If provided, prefill the
// recipient(s) and subject fields, and/or quote a message.
function comp_showForm( mode, quoteUID, mailto ) {
	clearTimeout( refreshTimer );

	var postbody = "request=createComposer&mailbox=" + encodeURIComponent(MessageList.getMailbox());
	if ( mode )     postbody += "&mode=" + mode;
	if ( quoteUID ) postbody += "&uid=" + encodeURIComponent(quoteUID);
	if ( mailto )   postbody += "&mailto=" + encodeURIComponent(mailto);
	if_remoteRequestStart();
	new Ajax( 'ajax.php', {
		postBody: postbody,
		onComplete : function ( responseText ) {
			comp_showCB( responseText );
		},
		onFailure : if_remoteRequestFailed
		} ).request();
}


// Callback for displaying message compose form.
function comp_showCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	if_hideWrappers();
	if_hideToolbars();

	$('comp-wrapper').setHTML( result.htmlFragment );
	$('comp-wrapper').style.display = 'block';
	$('comp-bar').style.display = 'block';
}


// Check the input data in the new message form, and then
// issue an XHR to send the message.
function comp_send( currentlyShownUID, saveDraft ) {
//	if_remoteRequestStart();

	// Temporary hack to give sending or saving feedback, until
	// interface feedback is properly rewritten.
	if ( saveDraft ) {
		Flash.flashMessage( "Saving draft ..." );
	} else {
		Flash.flashMessage( "Sending message ..." );
	}

	var parameters = "request=sendMessage&";
	if ( saveDraft ) parameters += "draft=save&";
	parameters += $('compose').toQueryString();
	new Ajax( 'ajax.php', {
		postBody: parameters,
		onComplete : function( responseText ) {
			comp_sendCB( responseText, currentlyShownUID );
		},
		onFailure : if_remoteRequestFailed
		} ).request();
}


// Callback for message sending: return to the mailbox listing
// and give the user a feedback message.
function comp_sendCB( responseText, currentlyShownUID ) {
//	$('list-status').setHTML( statusMsg );
//	$('list-status').style.display = 'block';
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	// Helpful message in result.message... display it?

	if ( result.draftMode ) {
		if ( $('comp-draftuid') ) {
			$('comp-draftuid').value = result.draftUid;
		}

		var d = new Date();
		var hr = d.getHours();
		var min = d.getMinutes();
		if ( min < 10 ) { min = '0'+min; }
		if ( hr == 0 ) { hr = '12'; }
		if ( hr > 12 ) { hr = hr-12; min += ' PM'; } else { min += ' AM'; }
		Flash.flashMessage( 'Draft saved at ' + hr + ':' + min );

	} else {
		if (currentlyShownUID) {
			if_returnToMessage();
		} else {
			if_returnToList( false );
		}
		Flash.flashMessage( result.message );
	}
}

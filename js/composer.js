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

var MessageComposer = new Class({
	initialize: function ( wrapper ) {
		this.wrapper = wrapper;
	},

	// Show the form for a new message. If provided, prefill the
	// recipient(s) and subject fields, and/or quote a message.
	showComposer: function ( mode, quoteUID, mailto ) {
		clearTimeout( refreshTimer );

		var postbody = "request=getComposeData&mailbox=" + encodeURIComponent(MessageList.getMailbox());
		if ( mode )     postbody += "&mode=" + mode;
		if ( quoteUID ) postbody += "&uid=" + encodeURIComponent(quoteUID);
		if ( mailto )   postbody += "&mailto=" + encodeURIComponent(mailto);
		if_remoteRequestStart();
		new Ajax( 'ajax.php', {
			postBody: postbody,
			onComplete : this.showComposerCB.bind( this ),
			onFailure : if_remoteRequestFailed
			} ).request();
	},

	showComposerCB: function ( responseText ) {
		var result = if_checkRemoteResult( responseText );
		if (!result) return;
	
		if_hideWrappers();
		if_hideToolbars();

		this._render( result.composedata );
		$( this.wrapper ).style.display = 'block';
		$('comp-bar').style.display = 'block';
	},

	_render: function ( compData ) {
		var composer = "";
		var action = compData['comp-mode'];

		// Right-side float here is to prevent IE7 from collapsing the div
		composer = "<div class=\"header-bar\"><img src=\"themes/" + userSettings['theme'] + "/top-corner.png\" alt=\"\" class=\"top-corner\" /><div class=\"header-right\">&nbsp;</div><div class=\"comp-header\">New message</div></div>";

		composer += "<form action=\"" + lichenURL + "\" method=\"post\" id=\"compose\" onsubmit=\"MessageCompose.sendMessage();return false\">";

		composer += "<input type=\"hidden\" name=\"comp-mode\" id=\"comp-mode\" value=\"" + compData['comp-mode'] + "\" />";

		if ( compData['comp-quoteuid'] ) {
			composer += "<input type=\"hidden\" name=\"comp-quoteuid\" id=\"comp-quoteuid\" value=\"" + compData['comp-quoteuid'] + "\" />";
			composer += "<input type=\"hidden\" name=\"comp-quotemailbox\" id=\"comp-quotemailbox\" value=\"" + compData['comp-quotemailbox'] + "\" />";
		}
		composer += "<input type=\"hidden\" name=\"comp-draftuid\" id=\"comp-draftuid\" value=\"";
		if ( action == "draft" ) {
			composer += compData['comp-draftuid'];
		}
		composer += "\" />";

		// Build identity selector.
		// TODO: Use HTML entities for this data, because it can contain <, >, and &.
		if ( compData['identities'].length == 1 ) {
			// Simple case: just display use the one identity - hidden form element..
			composer += "<input name=\"comp-identity\" id=\"comp-identity\" type=\"hidden\" value=\"" +
			       compData['identities'][0]['address'] + "\" />";
		} else {
			composer += "<label class=\"comp-label\" for=\"comp-identity\">From:</label> <select name=\"comp-identity\" id=\"comp-identity\">";
			for ( var i = 0; i < compData['identities'].length; i++ ) {
				var identity = compData['identities'][i];
				composer += "<option value=\"" + identity['address'] + "\"";
				if ( action == 'reply' || action == 'replyall' ) {
					if ( identity['address'].match( compData['comp-to'] ) != null ) {
						// Select this identity.
						composer += " selected=\"selected\"";
					}
				} else if ( action == "draft" ) {
					if ( identity['address'].match( compData['comp-from'] ) != null ) {
						// Select this identity.
						composer += " selected=\"selected\"";
					}
				} else if ( identity['isdefault'] ) {
					composer += " selected=\"selected\"";
				}
				composer += ">" + identity['name'] + " &lt;" + identity['address'] + "&gt;</option>";
			}
			composer += "</select>";
		}

		// Build to To: area, including buttons to display CC and BCC fields
		composer += "<div class=\"comp-label\"><label for=\"comp-to\">To:</label>";

		composer += "<p class=\"comp-add-fields\"><a id=\"comp-ccshow\" href=\"#\"" + ( compData['show-cc'] ? " style=\"display:none\"" : "" ) + " onclick=\"$('comp-cceditor').style.display='block';$('comp-ccshow').style.display='none';return false\">add CC</a>";
		composer += " <a id=\"comp-bccshow\" href=\"#\"" + ( compData['show-bcc'] ? " style=\"display:none\"" : "" ) + " onclick=\"$('comp-bcceditor').style.display='block';$('comp-bccshow').style.display='none';return false\">add BCC</a></p>";

		composer += "</div> <textarea name=\"comp-to\" id=\"comp-to\">" + compData['comp-to'] + "</textarea>";

		composer += "<div id=\"comp-cceditor\" style=\"display: " + ( compData['show-cc'] ? "block" : "none" ) + ";\">";
		composer += "<label class=\"comp-label\" for=\"comp-cc\">CC:</label> <textarea name=\"comp-cc\" id=\"comp-cc\">";
		composer += compData['comp-cc'];
		composer += "</textarea></div>";

		composer += "<div id=\"comp-bcceditor\" style=\"display: " + ( compData['show-bcc'] ? "block" : "none" ) + ";\">";
		composer += "<label class=\"comp-label\" for=\"comp-bcc\">BCC:</label> <textarea name=\"comp-bcc\" id=\"comp-bcc\">";
		composer += compData['comp-bcc'];
		composer += "</textarea></div>";

		// Build the subject area.
		composer += "<label class=\"comp-label\" for=\"comp-subj\">Subject:</label> <input type=\"text\" name=\"comp-subj\" id=\"comp-subj\" value=\"";
		composer += compData['comp-subj'] + "\" />";

		// Build the text area. Text only at the moment.
		composer += "<textarea name=\"comp-msg\" id=\"comp-msg\">";
		composer += compData['comp-msg'];
		composer += "</textarea>";

		if ( action == "forwardinline" ) {
			// If we have an inline-forwarded message, provide a link to forward as attachment instead.
			// TODO: If the user clicks this, it WILL NOT preserve the message content or anything!
			composer += "<p><a href=\"#\" onclick=\"MessageCompose.showComposer('forwardasattach',MessageDisplayer.getViewedUID()); return false\">&raquo; forward message as attachment</a></p>";
		}

		// Build a set of hidden elements with the current attachments.
		// At the same time, build the HTML for listing those attachments.
		var attachListHtml = "";
		for ( var i = 0; i < compData['comp-attach'].length; i++ ) {
			var attachment = compData['comp-attach'][i];

			attachListHtml += "<li>" + attachment['filename'] + " (" + attachment['type'] + ", " + attachment['size'] + ") ";
			if ( attachment['isforwardedmessage'] ) {
				attachListHtml += "<a href=\"#\" onclick=\"MessageCompose.showComposer('forwardinline',MessageDisplayer.getViewedUID()); return false\">[forward inline]</a>";
			} else {
				attachListHtml += "<a href=\"#\" onclick=\"MessageCompose.removeAttachment('" + escape( attachment['filename'] ) + "');return false\">";
				attachListHtml += "[remove]</a>";
			}
			attachListHtml += "</li>";

			// TODO: Html entities for the line below; otherwise it won't work properly.
			composer += "<input type=\"hidden\" name=\"comp-attach[]\" value=\"" + attachment['filename'] + "\" />";
		}
		
		composer += "</form>";
	
		// Build a list of attachments.
		composer += "<div class=\"sidebar-panel\" id=\"comp-attachments\">";
		composer += "<h2 class=\"sidebar-head\"><img src=\"themes/" + userSettings['theme'] + "/icons/attach.png\" alt=\"\" /> attachments</h2>";
	
		composer += "<ul id=\"comp-attachlist\">";
		composer += attachListHtml;
		composer += "</ul>";
		

		// Create the upload form.
		composer += "<form enctype=\"multipart/form-data\" action=\"ajax.php\" id=\"comp-uploadform\" method=\"post\" onsubmit=\"return asyncUploadFile($('comp-uploadform'))\">";
		composer += "<input type=\"hidden\" name=\"request\" id=\"request\" value=\"uploadAttachment\" />";
		composer += "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"" + compData['maxattachmentsize'] + "\" />";
		composer += "<label for=\"comp-attachfile\">add new</label><br />";
		composer += "<input type=\"file\" name=\"comp-attachfile\" id=\"comp-attachfile\" />";
		composer += "<div class=\"comp-attach-submit\"><input type=\"submit\" value=\"upload file\" /></div>";
		composer += "<input type=\"hidden\" name=\"upattach\" value=\"1\" />";
		composer += "</form></div>";

		$( this.wrapper ).setHTML( composer );
	},

	// Check the input data in the new message form, and then
	// issue an XHR to send the message.
	sendMessage: function( saveDraft ) {
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
			onComplete : this.sendMessageCB.bind( this ),
			onFailure : if_remoteRequestFailed
			} ).request();
	},

	// Callback for message sending: return to the mailbox listing
	// and give the user a feedback message.
	sendMessageCB: function( responseText ) {
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
			// TODO: determine if we're returning to a message list or
			// back to a particular message.
			if_returnToList( false );
			Flash.flashMessage( result.message );
		}
	},

	removeAttachment: function( filename ) {
		// Remove an attachment from the composer.
		var attachmentFilenames = $A( $('compose').getElementsByTagName('input') );
		var attachmentListElements = $A( $('comp-attachlist').getElementsByTagName('li') );

		var foundFilename = false;

		// We encoded filename in the removal link, so undo that now.
		filename = unescape( filename );

		for ( var i = 0; i < attachmentFilenames.length; i++ ) {
			var thisfile = attachmentFilenames[i];

			if ( thisfile.type == "hidden" && thisfile.value == filename ) {
				// This is the hidden form element for this file.
				// Destroy it.
				foundFilename = true;
				thisfile.remove();
			}
		}

		for ( var i = 0; i < attachmentListElements.length; i++ ) {
			var thisfile = attachmentListElements[i];

			if ( thisfile.getText().contains( filename ) ) {
				// TODO: This is not foolproof.
				thisfile.remove();
			}
		}

		// Ask the server to remove the file.
		if ( foundFilename ) {
			new Ajax( 'ajax.php', {
				postBody: 'request=removeAttachment&filename='+encodeURIComponent(filename),
				onComplete : this.attachmentDeleted.bind( this ),
				onFailure : if_remoteRequestFailed
				} ).request();
		}
	},

	// This is a stub - so errors get reported, but that's it.
	attachmentDeleted: function( responseText ) {
		var result = if_checkRemoteResult( responseText );
	}
});

var MessageCompose = new MessageComposer( 'comp-wrapper' );

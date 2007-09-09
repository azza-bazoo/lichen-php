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
		this.messageFormat = "text/plain";
	},

	// Show the form for a new message. If provided, prefill the
	// recipient(s) and subject fields, and/or quote a message.
	showComposer: function ( mode, quoteUID, mailto ) {
		var postbody = "request=getComposeData&mailbox=" + encodeURIComponent(Lichen.MessageList.getMailbox());
		if ( mode )     postbody += "&mode=" + mode;
		if ( quoteUID ) postbody += "&uid=" + encodeURIComponent(quoteUID);
		if ( mailto )   postbody += "&mailto=" + encodeURIComponent(mailto);
		if_remoteRequestStart();
		new Ajax( 'ajax.php', {
			postBody: postbody,
			onComplete: this.showComposerCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();
	},

	showComposerCB: function ( responseText ) {
		var result = if_checkRemoteResult( responseText );
		if (!result) return;
	
		this._render( result.composedata );
	},

	makeHTMLMail: function () {
		// Change the editor to HTML.
		this.messageFormat = "text/html";
		tinyMCE.execCommand( 'mceAddControl', false, 'comp-msg' );
	},

	makePlainMail: function () {
		// Change the editor to Plain.
		// TODO... strip HTML?
		this.messageFormat = "text/plain";
		tinyMCE.execCommand( 'mceRemoveControl', false, 'comp-msg' );
	},

	_render: function ( compData ) {
		// ****************************************************
		// IF YOU CHANGE THIS CODE, CHANGE THE MATCHING CODE IN
		// include/htmlrender.php AS WELL.

		var composer = "";
		var action = compData.comp_mode;

		// Right-side float here is to prevent IE7 from collapsing the div
		composer = "<div class=\"header-bar\"><img src=\"themes/" + userSettings.theme + "/top-corner.png\" alt=\"\" class=\"top-corner\" /><div class=\"header-right\">&nbsp;</div><div class=\"comp-header\">New message</div></div>";

		composer += "<form action=\"" + lichenURL + "\" method=\"post\" id=\"compose\" onsubmit=\"Lichen.MessageCompose.sendMessage();return false\">";

		composer += "<input type=\"hidden\" name=\"comp_mode\" id=\"comp_mode\" value=\"" + compData.comp_mode + "\" />";

		if ( compData.comp_quoteuid ) {
			composer += "<input type=\"hidden\" name=\"comp_quoteuid\" id=\"comp_quoteuid\" value=\"" + compData.comp_quoteuid + "\" />";
			composer += "<input type=\"hidden\" name=\"comp_quotemailbox\" id=\"comp_quotemailbox\" value=\"" + compData.comp_quotemailbox + "\" />";
		}
		composer += "<input type=\"hidden\" name=\"comp_draftuid\" id=\"comp_draftuid\" value=\"";
		if ( action == "draft" ) {
			composer += compData.comp_draftuid;
		}
		composer += "\" />";

		// Build identity selector.
		// TODO: Use HTML entities for this data, because it can contain <, >, and &.
		if ( compData.identities.length == 1 ) {
			// Simple case: just display use the one identity - hidden form element..
			composer += "<input name=\"comp_identity\" id=\"comp_identity\" type=\"hidden\" value=\"" +
			       compData.identities[0].address + "\" />";
		} else {
			composer += "<label class=\"comp-label\" for=\"comp_identity\">From:</label> <select name=\"comp_identity\" id=\"comp_identity\">";
			for ( var i = 0; i < compData.identities.length; i++ ) {
				var identity = compData.identities[i];
				composer += "<option value=\"" + identity.address + "\"";
				if ( action == 'reply' || action == 'replyall' ) {
					if ( identity.address.match( compData.comp_to ) != null ) {
						// Select this identity.
						composer += " selected=\"selected\"";
					}
				} else if ( action == "draft" ) {
					if ( identity.address.match( compData.comp_from ) != null ) {
						// Select this identity.
						composer += " selected=\"selected\"";
					}
				} else if ( identity.isdefault ) {
					composer += " selected=\"selected\"";
				}
				composer += ">" + identity.name + " &lt;" + identity.address + "&gt;</option>";
			}
			composer += "</select>";
		}

		// Build to To: area, including buttons to display CC and BCC fields
		composer += "<div class=\"comp-label\"><label for=\"comp_to\">To:</label>";

		composer += "<p class=\"comp-add-fields\"><a id=\"comp-ccshow\" href=\"#\"" + ( compData.show_cc ? " style=\"display:none\"" : "" ) + " onclick=\"$('comp-cceditor').style.display='block';$('comp-ccshow').style.display='none';return false\">add CC</a>";
		composer += " <a id=\"comp-bccshow\" href=\"#\"" + ( compData.show_bcc ? " style=\"display:none\"" : "" ) + " onclick=\"$('comp-bcceditor').style.display='block';$('comp-bccshow').style.display='none';return false\">add BCC</a></p>";

		composer += "</div> <textarea name=\"comp_to\" id=\"comp_to\">" + compData.comp_to + "</textarea>";

		composer += "<div id=\"comp-cceditor\" style=\"display: " + ( compData.show_cc ? "block" : "none" ) + ";\">";
		composer += "<label class=\"comp-label\" for=\"comp_cc\">CC:</label> <textarea name=\"comp_cc\" id=\"comp_cc\">";
		composer += compData.comp_cc;
		composer += "</textarea></div>";

		composer += "<div id=\"comp-bcceditor\" style=\"display: " + ( compData.show_bcc ? "block" : "none" ) + ";\">";
		composer += "<label class=\"comp-label\" for=\"comp_bcc\">BCC:</label> <textarea name=\"comp_bcc\" id=\"comp_bcc\">";
		composer += compData.comp_bcc;
		composer += "</textarea></div>";

		// Build the subject area.
		composer += "<label class=\"comp-label\" for=\"comp_subj\">Subject:</label> <input type=\"text\" name=\"comp_subj\" id=\"comp_subj\" value=\"";
		composer += compData.comp_subj + "\" />";

		composer += "<div><a href=\"#\" onclick=\"Lichen.MessageCompose.makeHTMLMail();return false\">HTML Message</a> | ";
		composer += "<a href=\"#\" onclick=\"Lichen.MessageCompose.makePlainMail();return false\">Plain Message</a></div>";

		// Build the text area. Text only at the moment.
		composer += "<textarea name=\"comp_msg\" id=\"comp_msg\">";
		composer += compData.comp_msg;
		composer += "</textarea>";

		if ( action == "forwardinline" ) {
			// If we have an inline-forwarded message, provide a link to forward as attachment instead.
			// TODO: If the user clicks this, it WILL NOT preserve the message content or anything!
			composer += "<p><a href=\"#\" onclick=\"Lichen.MessageCompose.showComposer('forwardasattach',Lichen.MessageDisplayer.getViewedUID()); return false\">&raquo; forward message as attachment</a></p>";
		}

		// Build a set of hidden elements with the current attachments.
		// At the same time, build the HTML for listing those attachments.
		var attachListHtml = "";
		for ( var i = 0; i < compData.comp_attach.length; i++ ) {
			var attachment = compData.comp_attach[i];

			attachListHtml += "<li>" + attachment.filename + " (" + attachment.type + ", " + attachment.size + ") ";
			if ( attachment.isforwardedmessage ) {
				attachListHtml += "<a href=\"#\" onclick=\"Lichen.MessageCompose.showComposer('forwardinline',Lichen.MessageDisplayer.getViewedUID()); return false\">[forward inline]</a>";
			} else {
				attachListHtml += "<a href=\"#\" onclick=\"Lichen.MessageCompose.removeAttachment('" + encodeURIComponent( attachment.filename ) + "');return false\">";
				attachListHtml += "[remove]</a>";
			}
			attachListHtml += "</li>";

			// TODO: Html entities for the line below; otherwise it won't work properly.
			composer += "<input type=\"hidden\" name=\"comp_attach[]\" value=\"" + attachment.filename + "\" />";
		}
		
		composer += "</form>";
	
		// Build a list of attachments.
		composer += "<div class=\"sidebar-panel\" id=\"comp-attachments\">";
		composer += "<h2 class=\"sidebar-head\"><img src=\"themes/" + userSettings.theme + "/icons/attach.png\" alt=\"\" /> attachments</h2>";
	
		composer += "<ul id=\"comp-attachlist\">";
		composer += attachListHtml;
		composer += "</ul>";
		

		// Create the upload form.
		composer += "<form enctype=\"multipart/form-data\" action=\"ajax.php\" id=\"comp-uploadform\" method=\"post\" onsubmit=\"return Lichen.MessageCompose.asyncUploadFile($('comp-uploadform'))\">";
		composer += "<input type=\"hidden\" name=\"request\" id=\"request\" value=\"uploadAttachment\" />";
		composer += "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"" + compData.maxattachmentsize + "\" />";
		composer += "<label for=\"comp_attachfile\">add new</label><br />";
		composer += "<input type=\"file\" name=\"comp_attachfile\" id=\"comp_attachfile\" />";
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
			Lichen.Flash.flashMessage( "Saving draft ..." );
		} else {
			Lichen.Flash.flashMessage( "Sending message ..." );
		}

		var parameters = "request=sendMessage&";
		if ( saveDraft ) parameters += "draft=save&";
		parameters += "format=" + encodeURIComponent( this.messageFormat ) + "&";
		parameters += $('compose').toQueryString();
		if ( this.messageFormat == "text/html" ) {
			// Seems as though comp_msg is not seen as a form element, and
			// is blank. Manually append it. Note: this is a hack, because
			// comp_msg is actually in the string after toQueryString(),
			// we rely on PHP overriding later instances of the same variable.
			parameters += "&comp_msg=" + encodeURIComponent( tinyMCE.getContent( 'comp_msg' ) );
		}
		new Ajax( 'ajax.php', {
			postBody: parameters,
			onComplete: this.sendMessageCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();
	},

	// Callback for message sending: return to the mailbox listing
	// and give the user a feedback message.
	sendMessageCB: function( responseText ) {
		var result = if_checkRemoteResult( responseText );
		if (!result) return;

		// Helpful message in result.message... display it?

		if ( result.draftMode ) {
			if ( $('comp_draftuid') ) {
				$('comp_draftuid').value = result.draftUid;
			}

			var d = new Date();
			var hr = d.getHours();
			var min = d.getMinutes();
			if ( min < 10 ) { min = '0'+min; }
			if ( hr == 0 ) { hr = '12'; }
			if ( hr > 12 ) { hr = hr-12; min += ' PM'; } else { min += ' AM'; }
			Lichen.Flash.flashMessage( 'Draft saved at ' + hr + ':' + min );

		} else {
			// TODO: determine if we're returning to a message list or
			// back to a particular message.
			this.cleanupComposer();
			Lichen.action( 'list', 'MessageList', 'listUpdate' );
			Lichen.Flash.flashMessage( result.message );
		}
	},

	cleanupComposer: function () {
		if ( this.messageFormat == "text/html" ) {
			// Delete the tinyMCE editor: http://tinymce.moxiecode.com/punbb/viewtopic.php?pid=22977
			tinyMCE.execCommand( 'mceRemoveControl', false, 'comp_msg' );
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
				onComplete: this.attachmentDeleted.bind( this ),
				onFailure: if_remoteRequestFailed
				} ).request();
		}
	},

	// This is a stub - so errors get reported, but that's it.
	attachmentDeleted: function( responseText ) {
		var result = if_checkRemoteResult( responseText );
	},

	// AJAX File upload using a iframe.
	// Based on an example at http://www.webtoolkit.info/ajax-file-upload.html
	asyncUploadFile: function( sourceForm ) {
		// Create a hidden iframe to do the work.
		var iframeName = 'asyncUpload' + Math.floor(Math.random() * 99999);

		var hiddenDiv = new Element('div');
		$(hiddenDiv).setHTML("<iframe style='display: none;' src='about:blank' id='" + iframeName +
			"' name='" + iframeName +
			"' onload='Lichen.MessageCompose.asyncUploadCompleted(\"" + iframeName +  "\")'></iframe>");

		$('comp-wrapper').adopt( hiddenDiv );

		// Now retarget the form to this new iFrame.
		$(sourceForm).setProperty( 'target', iframeName );

	//	if_remoteRequestStart();

		// Force the form to upload.
		return true;
	},

	asyncUploadCompleted: function( frameName ) {
		var hiddenIframe = $(frameName);

		// It seems IE calls this function twice, the second
		// time the iframe doesn't exist anymore, and thus it bombs.
		// So short circuit this
		if ( hiddenIframe == null ) return;

		if ( hiddenIframe.contentWindow != null ) {
			hiddenIframe = hiddenIframe.contentWindow.document;
		} else if ( hiddenIframe.contentDocument != null ) {
			hiddenIframe = hiddenIframe.contentDocument;
		} else {
			hiddenIframe = window.frames[frameName].document;
		}

		if (hiddenIframe.location.href == "about:blank") return;

		// Workaround for Firefox: point the iframe at a harmless URI
		// so it doesn't seem like it's still loading.
		hiddenIframe.location.href = "about:blank";

		// Parse the result.
		var result = if_checkRemoteResult( hiddenIframe.body.innerHTML );
		if (!result) return;

		// Destroy the iframe (by removing its parent DIV)
		// and update our list of attachments.
		$(frameName).getParent().remove();

		var displayAttach = new Element('li');
		displayAttach.setHTML( result.filename + " (" + result.type + ", " + result.size + " bytes)" +
			" (<a href=\"#\" onclick=\"Lichen.MessageCompose.removeAttachment('" + escape(result.filename) + "');return false\">remove</a>)" );
		$('comp-attachlist').adopt( displayAttach );

		var uploadedFile = new Element('input');
		uploadedFile.type = 'hidden';
		uploadedFile.name = 'comp_attach[]';
		uploadedFile.value = result.filename;
		$('compose').adopt( uploadedFile );

		$('comp-attachfile').value = "";
	}
});

//var MessageCompose = new MessageComposer( 'comp-wrapper' );

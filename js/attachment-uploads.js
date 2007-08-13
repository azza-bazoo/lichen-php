/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/attachment-uploads.js - attachment handling for composing new messages
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


// AJAX File upload using a iframe.
// Based on an example at http://www.webtoolkit.info/ajax-file-upload.html
function asyncUploadFile( sourceForm ) {
	// Create a hidden iframe to do the work.
	var iframeName = 'asyncUpload' + Math.floor(Math.random() * 99999);

	var hiddenDiv = new Element('div');
	$(hiddenDiv).setHTML("<iframe style='display: none;' src='about:blank' id='" + iframeName +
		"' name='" + iframeName +
		"' onload='asyncUploadCompleted(\"" + iframeName +  "\")'></iframe>");

	$('comp-wrapper').adopt( hiddenDiv );

	// Now retarget the form to this new iFrame.
	$(sourceForm).setProperty( 'target', iframeName );

//	if_remoteRequestStart();

	// Force the form to upload.
	return true;
}


function asyncUploadCompleted( frameName ) {
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
		" (<a href=\"#\" onclick=\"MessageCompose.removeAttachment('" + escape(result.filename) + "');return false\">remove</a>)" );
	$('comp-attachlist').adopt( displayAttach );

	var uploadedFile = new Element('input');
	uploadedFile.type = 'hidden';
	uploadedFile.name = 'comp-attach[]';
	uploadedFile.value = result.filename;
	$('compose').adopt( uploadedFile );

	$('comp-attachfile').value = "";
}


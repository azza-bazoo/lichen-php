/**

Lichen - AJAX IMAP client
version 0.4 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/settings.js - settings handling, including the panel for changing settings
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


var OptionsEditorClass = new Class({

	initialize: function ( wrapper ) {
		this.wrapper = wrapper;
//		this.currentIdentityMail = '';
		this.identityCache = null;
	},

	getWindowTitle: function () {
		return _("Lichen - Settings");
	},

	showEditor: function ( targetTab ) {
		if_remoteRequestStart();
		Lichen.busy();
		new Ajax( 'ajax.php', {
			postBody: 'request=settingsPanel&tab=' + encodeURIComponent( targetTab ),
			onComplete: this.showEditorCBStub.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();
	},

	showEditorCBStub: function ( responseText ) {
		// Stub to call showEditorCB via the interface controller, to allow it to perform the
		// transitions correctly.
		Lichen.action( 'options', 'OptionsEditor', 'showEditorCB', [responseText] );
		Lichen.notbusy();
	},

	showEditorCB: function ( responseText ) {
		var result = if_checkRemoteResult( responseText );
		if (!result) return;

		$(this.wrapper).setHTML(result.htmlFragment);

		// The mailbox manager needs a client side cache list of
		// the mailboxes. It is passed back with the HTML data.
		// Cache it in the appropriate spot - this is really a hack.
		if ( result.mailboxes ) {
			Lichen.MailboxManager.mailboxCache = result.mailboxes;
		}
		if ( result.identities ) {
			this.identityCache = result.identities;
		}
	},

	closePanel: function () {
		// Hack: update the mailbox list; because it may have changed.
		Lichen.MailboxList.listUpdate();
		Lichen.action( 'list', 'MessageList', 'listUpdate' );
	},

	generateQueryString: function( sourceForm ) {
		var inputs = $A( $(sourceForm).getElementsByTagName('input') );
		inputs.extend( $A( $(sourceForm).getElementsByTagName('select') ) );
		var results = new Array();

		for ( var i = 0; i < inputs.length; i++ ) {
			var thisValue = "";
			switch ( inputs[i].type ) {
				case "checkbox":
					if ( inputs[i].checked ) {
						thisValue = "true";
					} else {
						thisValue = "false";
					}
					break;
				default:
					thisValue = inputs[i].value;
					break;
			}
			results.push( inputs[i].id + "=" + encodeURIComponent( thisValue ) );
		}

		return results.join("&");
	},

	saveOptions: function () {
		if_remoteRequestStart();
		Lichen.busy();
		new Ajax( 'ajax.php', {
			postBody: 'request=settingsPanelSave&' + this.generateQueryString('opts-settings'),
			onComplete: this.saveOptionsCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();
	},

	saveOptionsCB: function ( responseText ) {
		Lichen.notbusy();
		var result = if_checkRemoteResult( responseText );
		if (!result) return;

		if ( result.errors && result.errors.length > 0 ) {
			alert(_("There were some errors saving your settings.\nAny valid settings were saved.\n\n") + result.errors.join("\n"));
		} else {
			this.closePanel();
		}

		opts_getCB( result );
	},

	identity_findInCache: function ( email ) {
		for ( var i = 0; i < this.identityCache.length; i++ ) {
			if ( email == this.identityCache[i].address ) {
				return this.identityCache[i];
			}
		}
		return null;
	},

	identity_add: function () {
		$('opts-identity-name').value = '';
		$('opts-identity-address').value = '';
		$('opts-identity-sig').value = '';

		if ( !$('opts-identity-new') ) {
			var newMenuOption = new Element( 'option', { 'id': 'opts-identity-new' } );
			newMenuOption.appendText( 'new identity' );
			$('opts-identity-list').adopt( newMenuOption );
			$('opts-identity-list').value = 'new identity';
		}

		// TODO: is there a cleaner way to do this?
		$('opts-identity-save').onclick = Lichen.OptionsEditor.identity_add_done.bind( this );

		return false;
	},

	identity_add_done: function () {
		var idname  = $('opts-identity-name').value;
		var idemail = $('opts-identity-address').value;
		var idsig   = $('opts-identity-sig').value;

		// TODO: check for conflicts with existing identity names
		if ( idname == "" || idemail == "" ) {
			Lichen.Flash.flashMessage( _("Can't add an identity with a blank name or blank e-mail.") );
			return false;
		}

//		if_remoteRequestStart();
		new Ajax( 'ajax.php', {
			postBody: 'request=identityEditor&action=add&idname='+encodeURIComponent( idname )+'&idemail='+encodeURIComponent( idemail )+
				'&idsig='+encodeURIComponent(idsig),
			onComplete: this.identity_actionCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();

		return false;
	},

	identity_edit: function () {
		if ( $('opts-identity-new') ) { $('opts-identity-new').remove(); }

		var identitylist = $('opts-identity-list');

		if ( identitylist.value == "" ) return false;

		var identity = this.identity_findInCache( identitylist.value ); 

		$('opts-identity-name').value    = identity.name;
		$('opts-identity-address').value = identity.address;
		$('opts-identity-sig').value     = identity.signature;

		// TODO: something more efficient than a closure
		$('opts-identity-save').onclick = function(){ return Lichen.OptionsEditor.identity_edit_done(identity.address); };

		return false;
	},

	identity_edit_done: function ( oldemail ) {
		var idname  = $('opts-identity-name').value;
		var idemail = $('opts-identity-address').value;
		var idsig   = $('opts-identity-sig').value;

		if ( idname == "" || idemail == "" ) {
			Lichen.Flash.flashMessage( _("Can't edit an identity to have a blank name or blank e-mail.") );
			return false;
		}

//		if_remoteRequestStart();
		new Ajax( 'ajax.php', {
			postBody: 'request=identityEditor&action=edit&idname='+encodeURIComponent( idname )+'&idemail='+encodeURIComponent( idemail )+
				'&oldid='+encodeURIComponent(oldemail)+'&idsig='+encodeURIComponent(idsig),
			onComplete: this.identity_actionCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();

		return false;
	},

	identity_setdefault: function () {
		var identitylist = $('opts-identity-list');

		if ( identitylist.value == "" ) return false;

//		if_remoteRequestStart();
		new Ajax( 'ajax.php', {
			postBody: 'request=identityEditor&action=setdefault&oldid='+encodeURIComponent( identitylist.value ),
			onComplete: this.identity_actionCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();

		return false;
	},

	identity_remove: function () {
		var identitylist = $('opts-identity-list');

		if ( identitylist.value == "" ) return false;

//		if_remoteRequestStart();
		new Ajax( 'ajax.php', {
			postBody: 'request=identityEditor&action=delete&oldid='+encodeURIComponent( identitylist.value ),
			onComplete: this.identity_actionCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();

		return false;
	},

	identity_actionCB: function ( responseText ) {
		var result = if_checkRemoteResult( responseText );
		if (!result) return;

		// Since the identity editor tab appears with an AJAX call
		// (for now), repeating that call will regenerate the list.
		Lichen.action( 'options', 'OptionsEditor', 'showEditor', ['identities'] );

// 		this.identity_cleareditor();
//
// 		var identitieslist = $('opts-identity-list');
// 		identitieslist.empty();
//
// 		// Use the DOM to rebuild list of options; setHTML breaks in IE6
// 		for ( var i = 0; i < result.identities.length; i++ ) {
// 			var thisId = result.identities[i];
// 			var value = thisId.address + "," + thisId.name;
// 			var display = thisId.name + " &lt;" + thisId.address + "&gt;";
// 			if ( thisId.isdefault ) display += " (default)";
//
// 			var option = new Element('option');
// 			option.value = value;
// 			option.setHTML( display );
//
// 			identitieslist.adopt(option);
// 		}
	}
});


// Ask the server for our settings.
function opts_get() {
//	if_remoteRequestStart();
	new Ajax( 'ajax.php', {
		postBody: 'request=getUserSettings',
		onComplete: opts_getCB,
		onFailure: if_remoteRequestFailed
		} ).request();
}


function opts_getCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	userSettings = result.settings;
}


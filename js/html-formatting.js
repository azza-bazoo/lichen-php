/**

Lichen - AJAX IMAP client
version 0.4 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/html-formatting.js - functions for conversion to and from HTML
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

function HTMLToText (source, textWidth) {
	// Make the browser parse our HTML source.
	var htmlSource = document.createElement('div');

	htmlSource.innerHTML = source;

	// Now walk through the DOM tree.
	var result = _htt_parseNode(htmlSource, textWidth);

	return result;
}

function _htt_parseNode(node, textWidth, indent) {
	var i = 0;

	var nodeContent = "";

	for (i = 0; i < node.childNodes.length; i++)
	{
		if (node.childNodes[i].tagName == 'A') {
			// Special handling of links...
			// TODO: Better than this.
			nodeContent += node.childNodes[i].href;
		} else if (node.childNodes[i].tagName == 'HR') {
			nodeContent += "-------------------------\n\n";
		} else if (node.childNodes[i].childNodes.length > 0) {
			// Recurse down the tree.
			nodeContent += _htt_parseNode(node.childNodes[i]);
		} else if (node.childNodes[i].nodeType == 3) {
			// Append the content of the node.
			// (This is a text node. TODO: Better detection)
			nodeContent += node.childNodes[i].nodeValue;
		}
	}

	// If this node is a formattable block (eg, heading)
	// then format the content appropriately.
	switch (node.tagName) {
		case 'H1':
		case 'H2':
		case 'H3':
		case 'H4':
		case 'H5':
		case 'H6':
			// Uppercase the content.
			nodeContent = nodeContent.toUpperCase();
			nodeContent += "\n\n";
			break;
		case 'P':
		case 'DIV':
			// Top level, insert a newline after it.
			nodeContent += "\n\n";
			break;
	}
	
	return nodeContent;
}

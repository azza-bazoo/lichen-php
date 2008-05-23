<?php

// Lichen's stub to include the appropriate HTMLPurifier files, and set up
// an appropriate environment for the little bit of HTMLPurifier that we do use.

set_include_path(dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
define( HTMLPURIFIER_PREFIX, '' );

include( 'HTMLPurifier/Config.php' );
include( 'HTMLPurifier/Context.php' );
include( 'HTMLPurifier/Lexer.php' );

HTMLPurifier_ConfigSchema::define(
	'Core', 'CollectErrors', false, 'bool', '
Whether or not to collect errors found while filtering the document. This
is a useful way to give feedback to your users. CURRENTLY NOT IMPLEMENTED.
This directive has been available since 2.0.0.
');

?>

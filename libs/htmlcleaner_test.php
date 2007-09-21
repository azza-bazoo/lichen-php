<?php

include( "htmlparser.php" );

$input = <<<ENDINPUT
<html><head></head><body><img src="bar" baz=foo what='you' raboof='this is multi' />
<img src="multi double&quot;" or=run baz="together"foo="run" />
<p>Foo, bar</p>
</body>
</html>
ENDINPUT;

// $tagName: the name of the tag (empty if text node) (lowercased)
// $textNode: text between the last tag and the next tag (by reference so it can be modified) (empty if tag is set)
// $isClosingTag: is the closing version of this tag.
// $isSelfClosed: is a self-closed tag.
// $attribtutes: array of attributes, by reference so it can be modified.
// $callbackData: data passed to cleaner, to pass along to callbacks.
//
// return true to rebuild and output tag (or just append output text)
// return false to suppress tag from output.
function callback( $tagName, &$textNode, $isClosingTag, $isSelfClosed, &$attributes, $callbackData ) {
	//echo "Tag: {$tagName}, Text: {$textNode}, Closing: {$isClosingTag}, Self Closed: {$isSelfClosed}, Attributes: ", print_r( $attributes, 1 ), "\n";
	if ( $tagName == "img" ) {
		$attributes['class'] = "bar";
	}
	return true;
}

echo "<h1>Input</h1>";
echo "<pre>";
echo htmlentities( $input );
echo "</pre>";

echo "<h1>Output</h1>";
echo "<pre>";
echo htmlentities( parseHtml( $input, "callback" ) );
echo "</pre>";

?>

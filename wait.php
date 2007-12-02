<?php

// Extremely crude loading notification hack for lichen.
// It calls this script inside an iframe when it's busy.
// When done, it will terminate the connection.
// Whilst the iframe is loading in the browser, it will trigger
// its own loading display mechanism, even though the page is
// not changing.
//
// TODO: Figure out how to give the iframe a URL that will just
// trigger the loading notification, but not cause another connection
// to the server...

sleep(1000);

?>

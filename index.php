<?php

require_once( "../arc/ARC2.php" );
require_once( "../Graphite/Graphite.php" );

$path = substr( $_SERVER["REQUEST_URI"], 10 );
print $path;

if( preg_match( '/^uri\//', $path  ) )
{
	$uri = substr( $path, 4 );

	print "<h1>URI data.</h1>";
	$b = parse_url( $uri );
	$graph = new Graphite();
	$graph->ns( "uri","http://lemur.ecs.soton.ac.uk/~cjg/uri/uri/" );
	$graph->ns( "uriv","http://lemur.ecs.soton.ac.uk/~cjg/uri/ns/" );
	$graph->addCompressedTriple( "uri:$uri", "rdf:type", "uriv:URI" );
	$graph->addCompressedTriple( $uri, "skos:notation", "$uri", "uriv:URIScheme" );
	
	print "<pre>";
	print htmlspecialchars( print_r( $b ,true ));
	print "</pre>";
	print $graph->dump();
}
else
{
	print "dang. 404 or such";
}

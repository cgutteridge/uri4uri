<?php

# http://dbpedia.org/sparql?default-graph-uri=http%3A%2F%2Fdbpedia.org&query=select+distinct+*%0D%0Awhere+{%0D%0A%3Fcountry+%3Chttp%3A%2F%2Fdbpedia.org%2Fproperty%2Fcctld%3E+%3Ftld+.%0D%0A%3Fcountry+rdfs%3Alabel+%3Flabel+.%0D%0AFILTER%28+LANG%28%3Flabel%29+%3D+%27en%27+%29%0D%0AOPTIONAL+{+%3Fcountry+georss%3Apoint+%3Fpoint+.+}%0D%0A%0D%0A%0D%0A}%0D%0A&format=json&timeout=0&debug=on

$data = json_decode( file_get_contents("dbpedia-tld.json") );
$results = array();
foreach( $data->results->bindings as $row )
{
	$ref = array();
	$tld = $row->tld->value;
	$tld = preg_replace( "/ and |,/"," ", $tld );
	$tld = trim( $tld );
	$tld = preg_replace( "/[;|\|].*/","",$tld );
	if( preg_match( "/resource\/(\..*)$/", $tld, $b ) ) { 
		$ref["uri"] = $row->country->value;
		$ref["tld_uri"] = $row->tld->value;
		$ref["tld"] = $b[1];
		if( isset( $row->point ) && isset( $row->point->value ) )
		{
			$ref["point"] = $row->point->value;
		}
		$ref["name"] = $row->label->value;
		$results[$ref["tld"]] = $ref;
		continue; 
	}
	if( preg_match( "/resource\//", $tld ) ) { continue; }
	foreach( preg_split( "/\s+/s", $tld ) as $word )
	{
		if( preg_match( "/^\./", $word ) )
		{
			$ref["uri"] = $row->country->value;
			$ref["tld"] = $word;
			if( isset( $row->point ) && isset( $row->point->value ) )
			{
				$ref["point"] = $row->point->value;
			}
			$ref["name"] = $row->label->value;
			$results[$ref["tld"]] = $ref;
		}
	}
}

print json_encode( $results );

<?php
require_once( "../arc2/ARC2.php" );
require_once( "../Graphite/Graphite.php" );

$filepath = "/home/uri4uri/htdocs";
$BASE = "http://uri4uri.net/";

$show_title = true;
#error_log( "Req: ".$_SERVER["REQUEST_URI"] );

$path = substr( $_SERVER["REQUEST_URI"], 0 );

if( $path == "" )
{
	header( "Location: $BASE" );
	exit;
}

if( $path == "/robots.txt" )
{
	header( "Content-type: text/plain" );
	print "User-agent: *\n";
	# prevent robots triggering a who-is
	print "Disallow: /uri.html/\n"; 
	print "Disallow: /uri.rdf/\n"; 
	print "Disallow: /uri.ttl/\n"; 
	print "Disallow: /uri.nt/\n"; 
	print "Disallow: /uri/\n"; 
	exit;
}

if( preg_match( "/^\/(homedev)?/", $path ) && @$_GET["uri"] )
{
	$uri4uri = "http://uri4uri.net/uri/".$_GET["uri"];
	if( preg_match( '/#/', $_GET["uri"] ) )
	{
		$uri4uri = "http://uri4uri.net/fragment/".urlencode($_GET["uri"]);
	}
	header( "Location: $uri4uri" );
	exit;
}
if( $path == "/" )
{
	$title = 'uri4uri';
	$show_title = false;
	$content = file_get_contents( "homepage.html" );
	require_once( "template.php" );
	exit;
}

if( !preg_match( "/^\/(vocab|uri|fragment|scheme|suffix|domain|mime)(\.(rdf|ttl|html|nt))?(\/(.*))?$/", $path, $b ) )
{
	serve404();
	exit;
}
@list( $dummy1, $type, $dummy2, $format, $dummy3, $id ) = $b;

if( !isset( $format ) || $format == "")
{	
	$wants = "text/turtle";

	if( isset( $_SERVER["HTTP_ACCEPT"] ) )
	{
		$o = array( 'text/html'=>0, "text/turtle"=>0.02, "application/rdf+xml"=>0.01 );
	
        	$opts = preg_split( "/\s*,\s*/", $_SERVER["HTTP_ACCEPT"] );
	
        	foreach( $opts as $opt)
        	{
                	$optparts = preg_split( "/;/", $opt );
                	$mime = array_shift( $optparts );
			if( !isset( $o[$mime] ) ) { continue; }
				
                	$o[$mime] = 1;
                	foreach( $optparts as $optpart )
                	{
                        	list( $k,$v ) = preg_split( "/=/", $optpart );
                        	if( $k == "q" ) { $o[$mime] = $v; }
                	}
        	}
	
		$top_score = 0;
		foreach( $o as $mime=>$score )
		{
			if( $score > $top_score ) 
			{
				$top_score = $score;
				$wants = $mime;
			}
		}
	}

	$format = "html";
	if( $wants == "text/turtle" ) { $format = "ttl"; }
	if( $wants == "application/rdf+xml" ) { $format = "rdf"; }

	header( "HTTP/1.1 303 C.Elseware" );
	header( "Location: $BASE$type.$format/$id" );
	exit;
}
if( $type == "uri" ) { $graph = graphURI( $id ); }
elseif( $type == "fragment" ) { $graph = graphFragment( $id ); }
elseif( $type == "scheme" ) { $graph = graphScheme( $id ); }
elseif( $type == "suffix" ) { $graph = graphSuffix( $id ); }
elseif( $type == "domain" ) { $graph = graphDomain( $id ); }
elseif( $type == "mime" ) { $graph = graphMime( $id ); }
elseif( $type == "vocab" ) { $graph = graphVocab( $id ); }
else { serve404(); exit; }

if( $format == "html" )
{
	header( "HTTP/1.1 200 OK" );
	$doc = $graph->resource("");
	$title = $doc->label();
	$content = "";
	if( $doc->has( "foaf:primaryTopic" ) )
	{
		$uri = $doc->getString( "foaf:primaryTopic" );
		#$content.= "<p>URI: <tt>$uri</tt></p>";
	}
	$content.= "<p><span style='font-weight:bold'>Download data:</span> ";
	$content.= "<a href='$BASE$type.ttl/$id'>Turtle</a>";
	$content.= " &bull; ";
	$content.= "<a href='$BASE$type.nt/$id'>N-Triples</a>";
	$content.= " &bull; ";
	$content.= "<a href='$BASE$type.rdf/$id'>RDF XML</a>";
	$content.= "</p>";

	if( $type == "vocab" )
	{
		$title = "uri4uri Vocabulary";

		$sections = array( 
			array( "Classes", "rdfs:Class", "classes" ),
			array( "Properties", "rdfs:Property", "properties" ),
			array( "Datatypes", "rdfs:Datatype", "datatypes" ),
			array( "Concepts", "skos:Concept", "concepts" ),
		);
		$l = array();
		$skips = array();
		foreach( $sections as $s )
		{
			$l[$s[2]] = $graph->allOfType( $s[1] );
			$skips []= "<a href='#".$s[2]."'>".$s[0]."</a>";
		}
		addExtraVocabTrips( $graph );
		$content.="<p><strong style='font-weight:bold'>Jump to:</strong> ".join( " &bull; ", $skips )."</p>";
		$content.= "<style type='text/css'>.class .class { margin: 4em 0;} .class .class .class { margin: 1em 0; }</style>";
		$content.= "<div class='class'><div class='class2'>";
		foreach( $sections as $s )
		{
			$html = array();
			foreach( $l[$s[2]] as $resource ) 
			{ 
				$html[$resource->toString()]= "<a name='".substr( $resource->toString(),25)."'></a>".renderResource( $graph, $resource ); 
			}
			ksort($html);
			$content.= "<a name='".$s[2]."' /><div class='class'><div class='classLabel'>".$s[0]."</div><div class='class2'>";
			$content.= join( "", $html );	
			$content.= "</div></div>";
		}

		$content.= "</div></div>";
	}
	else
	{ 
		addVocabTrips( $graph );
		addExtraVocabTrips( $graph );
		$resource = $graph->resource( $uri );
		if( $resource->has("rdf:type") )
		{
			$thingy_type =" <span class='classType'>[".$resource->all( "rdf:type" )->label()->join( ", " )."]</span>";
		}
		$content.= renderResource( $graph, $resource );
		#$content .= "<div style='font-size:80%'>".$graph->dump()."</div>";
	}
	require_once( "template.php" );
}
elseif( $format == "rdf" )
{
	header( "HTTP/1.1 200 OK" );
	header( "Content-type: application/rdf+xml" );
	print $graph->serialize( "RDFXML" );
}
elseif( $format == "nt" )
{
	header( "HTTP/1.1 200 OK" );
	header( "Content-type: text/plain" );
	print $graph->serialize( "NTriples" );
}
elseif( $format == "ttl" )
{
	header( "HTTP/1.1 200 OK" );
	header( "Content-type: text/turtle" );
	print $graph->serialize( "Turtle" );
}
else
{
	print "Weird error";
}


function initGraph()
{
	global $BASE;

	$graph = new Graphite();
	$graph->ns( "uri",$BASE."uri/" );
	$graph->ns( "uriv",$BASE."vocab#" );
	$graph->ns( "scheme",$BASE."scheme/" );
	$graph->ns( "domain",$BASE."domain/" );
	$graph->ns( "suffix",$BASE."suffix/" );
	$graph->ns( "fragment",$BASE."fragment/" );
	$graph->ns( "mime",$BASE."mime/" );
	$graph->ns( "occult", "http://data.totl.net/occult/" );
	$graph->ns( "xtypes", "http://prefix.cc/xtypes/" );
	$graph->ns( "vs","http://www.w3.org/2003/06/sw-vocab-status/ns#" );
	
	return $graph;
}

function serve404()
{
	header( "HTTP/1.1 404 Not Found" );
	$title = "404 Not Found";
	$content =  "<p>See, it's things like this that are what Ted Nelson was trying to warn you about.</p>";
	require_once( "template.php" );
}

function graphVocab( $id )
{

	$graph = initGraph();
	addBoilerplateTrips( $graph, "uriv:", "URI Vocabulary" );
	$graph->addCompressedTriple( "uriv:", "rdf:type", "owl:Ontology" );
	$graph->addCompressedTriple( "uriv:", "dcterms:title", "URI Vocabulary", "literal" );
	addVocabTrips( $graph );

	return $graph;
}

function addVocabTrips( &$graph )
{
	global $filepath;
	$lines = file( "$filepath/ns.txt" );
	$tmap = array(
		""=>"skos:Concept",
		"c"=>"rdfs:Class",	
		"p"=>"rdfs:Property",
		"d"=>"rdfs:Datatype" );
	foreach( $lines as $line )
	{
		list( $term, $type, $name ) = preg_split( "/:/", chop( $line ) );
		$graph->addCompressedTriple( "uriv:$term", "rdf:type", $tmap[$type] );
		$graph->addCompressedTriple( "uriv:$term", "rdfs:isDefinedBy", "uriv:" );
		$graph->addCompressedTriple( "uriv:$term", "rdfs:label", $name, "literal" );
	}
}

function graphFragment( $fragment )
{
	$uri = urldecode( $fragment );
	$graph = initGraph();

	if( !preg_match( "/#/" , $uri ))
	{
		serve404();
		exit;
	}

	addBoilerplateTrips( $graph, "fragment:".urlencode($uri), $uri );
	addFragmentTrips( $graph, $uri );

	return $graph;
}

function graphURI( $uri )
{
	if( preg_match( "/#/" , $uri ))
	{
		serve404();
		exit;
	}
	$graph = initGraph();
	addBoilerplateTrips( $graph, "uri:$uri", $uri );
	addURITrips( $graph, $uri );
	return $graph;
}


function graphSuffix( $suffix )
{
	$graph = initGraph();
	$uri = $graph->expandURI( "suffix:$suffix" );
	addBoilerplateTrips( $graph, "suffix:$suffix", $uri );
	addSuffixTrips( $graph, $suffix );
	return $graph;
}

function graphDomain( $domain )
{
	$graph = initGraph();
	$uri = $graph->expandURI( "domain:$domain" );
	addBoilerplateTrips( $graph, "domain:$domain", $uri );
	addDomainTrips( $graph, $domain );
	return $graph;
}

function graphMime( $mime )
{
	$graph = initGraph();
	$uri = $graph->expandURI( "mime:$mime" );
	addBoilerplateTrips( $graph, "mime:$mime", $uri );
	addMimeTrips( $graph, $mime );
	return $graph;
}

function graphScheme( $scheme )
{
	$graph = initGraph();
	$uri = $graph->expandURI( "scheme:$scheme" );
	addBoilerplateTrips( $graph, "scheme:$scheme", $uri );
	addSchemeTrips( $graph, $scheme );
	return $graph;
}

function addBoilerplateTrips( &$graph, $uri, $title )
{
	$graph->addCompressedTriple( "", "rdf:type", "foaf:Document" );
	$graph->addCompressedTriple( "", "dcterms:title", $title, "literal" );
	$graph->addCompressedTriple( "", "foaf:primaryTopic", "$uri" );
	
# wikipedia data etc. not cc0
#"	$graph->addCompressedTriple( "", "dcterms:license", "http://creativecommons.org/publicdomain/zero/1.0/" );
#	$graph->addCompressedTriple( "http://creativecommons.org/publicdomain/zero/1.0/", "rdfs:label", "CC0: Public Domain Dedication", "literal" );
	
}

function addFragmentTrips( &$graph, $uri )
{
	list( $uri_part, $fragment_part ) = preg_split( '/#/', $uri, 2 );

	$f_uri = "fragment:".urlencode( $uri );
	$graph->addCompressedTriple( $uri, "uriv:identifiedBy", $f_uri );
	$graph->addCompressedTriple( $f_uri, "rdf:type", "uriv:URI" );
	$graph->addCompressedTriple( $f_uri, "rdf:type", "uriv:FragmentURI" );
	$graph->addCompressedTriple( $f_uri, "skos:notation", $fragment_part, "uriv:FragmentDatatype" );
	$graph->addCompressedTriple( "uri:$uri_part", "uriv:fragment", $f_uri );

	addURITrips( $graph, $uri_part );

}

function addURITrips( &$graph, $uri )
{
	$b = parse_url( $uri );

	$graph->addCompressedTriple( $uri, "uriv:identifiedBy", "uri:$uri" );
	$graph->addCompressedTriple( "uri:$uri", "rdf:type", "uriv:URI" );
	$graph->addCompressedTriple( "uri:$uri", "skos:notation", $uri, "uriv:URIDatatype" );
	$graph->addCompressedTriple( "uri:$uri", "uriv:length", strlen( $uri ), "xsd:positiveInteger" );

	if( @$b["scheme"] )
	{
		$graph->addCompressedTriple( "uri:$uri", "uriv:scheme", "scheme:".$b["scheme"] );
		addSchemeTrips( $graph, $b["scheme"] );
		if( $b["scheme"] == "http" || $b["scheme"] == "https" || $b["scheme"] == "ftp" )
		{
			addHTTPSchemeTrips( $graph, $uri );
		}
	} # end scheme
	
	$hash = md5( $uri );
	$hash_number  = substr( base_convert($hash, 16, 10),0, 10);
	$graph->addCompressedTriple( "uri:$uri", "uriv:md5", $hash, "uriv:MD5HashDatatype" );

	# silly stuff
	$chances = 4;
	if( $hash_number % $chances == 0 )
	{
		$hash_number = floor( $hash_number / $chances );
		global $filepath;
		$thingys = file( "$filepath/occult.txt" );
		$row = chop($thingys[ $hash_number % sizeof( $thingys ) ]);
		list( $thing_uri, $thing_name ) = preg_split( "/\t/", $row );
		
		$graph->addCompressedTriple( "uri:$uri", "occult:correspondsTo", $thing_uri );
		$graph->addCompressedTriple( $thing_uri, "rdfs:label", $thing_name, "literal" );
	}
}

function addHTTPSchemeTrips( &$graph, $uri )
{
	$b = parse_url( $uri );

	if( @$b["host"] )
	{
		$graph->addCompressedTriple( "uri:$uri", "uriv:host", "domain:".$b["host"] );
		addDomainTrips( $graph, $b["host"] );
		if( @$b["scheme"] == "http" || @$b["scheme"] == "https" )
		{
			$homepage = $b["scheme"]."://".$b["host"];
			if( @$b["port"] )
			{
				$homepage.= ":".$b["port"];
			}
			$homepage.="/";

			$graph->addCompressedTriple( "domain:".$b["host"], "foaf:homepage", $homepage);
			$graph->addCompressedTriple( $homepage, "rdf:type", "foaf:Document" );
		}
	}


	if( @$b["port"] )
	{
		$graph->addCompressedTriple( "uri:$uri", "uriv:port", $b["port"], "xsd:positiveInteger" );
	}
	else
	{
		$graph->addCompressedTriple( "uri:$uri", "uriv:port", "uriv:noPortSpecified" );
	}
	
	if( @$b["user"] )
	{
		$graph->addCompressedTriple( "uri:$uri", "uriv:user", $b["user"], "literal" );
		if( @$b["pass"] )
		{
			$graph->addCompressedTriple( "uri:$uri", "uriv:pass", $b["pass"], "literal" );
		}
		$graph->addCompressedTriple( "uri:$uri", "uriv:account", "uri:$uri#account-".$b["user"] );
		$graph->addCompressedTriple( "uri:$uri#account-".$b["user"], "rdf:type", "foaf:OnlineAccount" );
		$graph->addCompressedTriple( "uri:$uri#account-".$b["user"], "rdfs:label", $b["user"], "xsd:string" );
	}

	if( @$b["path"] )
	{
		$graph->addCompressedTriple( "uri:$uri", "uriv:path", $b["path"], "uriv:PathDatatype" );
		if( preg_match( "/\.([^#\.\/]+)($|#)/", $b["path"], $bits  ) )
		{
			$graph->addCompressedTriple( "uri:$uri", "uriv:suffix", "suffix:".$bits["1"] );
			addSuffixTrips( $graph, $bits[1] );
		}
		if( preg_match( "/\/([^#\/]+)($|#)/", $b["path"], $bits  ) )
		{
			$graph->addCompressedTriple( "uri:$uri", "uriv:filename", $bits["1"], "uriv:FilenameDatatype" );
		}
	}

	if( @$b["query"] )
	{
		$graph->addCompressedTriple( "uri:$uri", "uriv:queryString", $b["query"], "uriv:QueryStringDatatype" );
		$graph->addCompressedTriple( "uri:$uri", "uriv:query", "uri:$uri#query" );
		$graph->addCompressedTriple( "uri:$uri#query", "rdf:type", "uriv:Query" );
		$graph->addCompressedTriple( "uri:$uri#query", "rdf:type", "rdf:Seq" );
		$i = 0;
		foreach( preg_split( "/&/", $b["query"] ) as $kv )
		{
			++$i;
			$graph->addCompressedTriple( "uri:$uri#query", "rdf:_$i", "uri:$uri#query-$i" );
			$graph->addCompressedTriple( "uri:$uri#query-$i", "rdf:type", "uriv:QueryKVP" );
			if( preg_match( '/=/', $kv ) )
			{
				list( $key, $value ) = preg_split( '/=/', $kv, 2 );
				$graph->addCompressedTriple( "uri:$uri#query-$i", "uriv:key", $key, "uriv:QueryKey" );
				$graph->addCompressedTriple( "uri:$uri#query-$i", "uriv:value", $value, "uriv:QueryValue" );
			}
		}
	}
}	
		



function addDomainTrips( &$graph, $domain )
{	
	$actual_domain = $domain;
	$nowww_actual_domain = $domain;
	if(substr(strtolower($nowww_actual_domain), 0, 4) == "www."){  $nowww_actual_domain = substr($actual_domain, 4);}

	require_once( "whois.php" );
	$whoisservers = whoisservers();
	global $filepath;

	global $schemes;
	if( !isset( $schemes ) )
	{
		$schemes = json_decode( file_get_contents( "$filepath/schemes.json" ), true );
	}
	
	global $tlds;
	if( !isset( $tlds ) )
	{
		$tlds = json_decode( file_get_contents( "$filepath/tld.json" ), true );
	}
	global $zones;
	if( !isset( $zones ) )
	{
		$zones = json_decode( file_get_contents( "$filepath/zones.json" ), true );
	}

	$graph->addCompressedTriple( "domain:".$domain, "rdf:type", "uriv:Domain" );
#	if(ValidateDomain($domain)) 	
#	{
#		$graph->addCompressedTriple( "domain:".$domain, "rdf:type", "uriv:Domain-Valid" );
#	}	
#	else
#	{
#		$graph->addCompressedTriple( "domain:".$domain, "rdf:type", "uriv:Domain-Invalid" );
#	}
	$graph->addCompressedTriple( "domain:".$domain, "rdfs:label", $domain, "literal" );
	$graph->addCompressedTriple( "domain:".$domain, "skos:notation", $domain, "uriv:DomainDatatype" );

	# Super Domains
	while( preg_match( "/\./", $domain ) )
	{
		$old_domain = $domain;
		$domain = preg_replace( "/^[^\.]*\./", "", $domain );
			
		$graph->addCompressedTriple( "domain:".$domain, "uriv:subDom", "domain:".$old_domain );
		$graph->addCompressedTriple( "domain:".$domain, "rdf:type", "uriv:Domain" );
#		if(ValidateDomain($domain)) 	
#		{
#			$graph->addCompressedTriple( "domain:".$domain, "rdf:type", "uriv:Domain-Valid" );
#		}	
#		else
#		{
#			$graph->addCompressedTriple( "domain:".$domain, "rdf:type", "uriv:Domain-Invalid" );
#		}
		$graph->addCompressedTriple( "domain:".$domain, "rdfs:label", $domain, "literal" );
		$graph->addCompressedTriple( "domain:".$domain, "skos:notation", $domain, "uriv:DomainDatatype" );

		if( isset( $whoisservers["$domain"] ) )
		{
			$graph->addCompressedTriple( "domain:".$domain, "uriv:whoIsServer", "domain:".$whoisservers["$domain"] );
			$graph->addCompressedTriple( "domain:".$whoisservers["$domain"], "rdf:type", "uriv:WhoisServer" );
			
			$lookup = LookupDomain($nowww_actual_domain,$whoisservers[$domain] );
			if( @$lookup )
			{
				$graph->addCompressedTriple( "domain:$nowww_actual_domain", "uriv:whoIsRecord", $lookup, "literal" );
			}
		}
	}

	# TLD Shenanigans...

	$graph->addCompressedTriple( "domain:".$domain, "rdf:type", "uriv:TopLevelDomain" );
	if( isset( $tlds[".$domain"] ) )
	{
		$tld =  $tlds[".$domain"] ;
		foreach( $tld as $place )
		{
			$graph->addCompressedTriple( $place["uri"], "http://dbpedia.org/property/cctld", "domain:$domain" );
			$graph->addCompressedTriple( $place["uri"], "rdfs:label", $place["name"], "literal" );
			$graph->addCompressedTriple( $place["uri"], "rdf:type", "http://dbpedia.org/ontology/Country" );
			$graph->addCompressedTriple( $place["uri"], "foaf:page", db2wiki( $place["uri"] ) );
			$graph->addCompressedTriple( db2wiki( $place["uri"] ), "rdf:type", "foaf:Document" );
			if( isset( $place["tld_uri"] ) )
			{
				$graph->addCompressedTriple( "domain:".$domain, "owl:sameAs", $place["tld_uri"] );
				$graph->addCompressedTriple( "domain:".$domain, "foaf:page", db2wiki( $place["tld_uri"] ) );
				$graph->addCompressedTriple( db2wiki( $place["tld_uri"] ), "rdf:type", "foaf:Document" );
			}
			if( isset( $place["point"] ) )
			{
				list( $lat, $long ) = preg_split( "/\s+/", trim( $place["point"] ) );
				$lat = sprintf( "%0.5f",$lat );
				$long = sprintf( "%0.5f",$long );
				$graph->addCompressedTriple( $place["uri"], "geo:lat", $lat, "xsd:float" );
				$graph->addCompressedTriple( $place["uri"], "geo:long", $long, "xsd:float" );
			}
		}
	}
	if( isset( $zones["$domain"] ) )
	{
		$zone = $zones["$domain"] ;
		$graph->addCompressedTriple( "domain:$domain", "uriv:delegationRecordPage", "http://www.iana.org".$zone["url"] );
		$graph->addCompressedTriple( "domain:$domain", "foaf:page", "http://www.iana.org".$zone["url"] );
		$graph->addCompressedTriple( "http://www.iana.org".$zone["url"], "rdf:type", "foaf:Document" );
		$typemap = array(
"country-code"=>"TopLevelDomain-CountryCode",
"generic"=>"TopLevelDomain-Generic",
"generic-restricted"=>"TopLevelDomain-GenericRestricted",
"infrastructure"=>"TopLevelDomain-Infrastructure",
"sponsored"=>"TopLevelDomain-Sponsored",
"test"=>"TopLevelDomain-Test" );
		$graph->addCompressedTriple( "domain:$domain", "rdf:type", "uriv:".$typemap[$zone["type"]] );
		$graph->addCompressedTriple( "domain:$domain", "uriv:sponsor", "domain:$domain#sponsor" );
		$graph->addCompressedTriple( "domain:$domain#sponsor", "rdf:type", "foaf:Organization" );
		$graph->addCompressedTriple( "domain:$domain#sponsor", "rdfs:label", $zone["sponsor"], "literal" );
	}
}

function addSuffixTrips( &$graph, $suffix )
{
	global $filepath;
	$graph->addCompressedTriple( "suffix:$suffix", "rdf:type", "uriv:Suffix" );
	$graph->addCompressedTriple( "suffix:$suffix", "rdfs:label", ".".$suffix, "literal" );
	$graph->addCompressedTriple( "suffix:$suffix", "skos:notation", $suffix, "uriv:SuffixDatatype" );

	$exts = json_decode( file_get_contents( "$filepath/extensions.json" ), true );
	if( isset($exts[$suffix]) )
	{
		foreach( $exts[$suffix] as $format_uri=>$format_info )
		{
			$graph->addCompressedTriple( "suffix:$suffix", "uriv:usedForFormat", $format_uri );
			$graph->addCompressedTriple( $format_uri, "rdfs:label", $format_info["label"], "literal", "en" );
			$graph->addCompressedTriple( $format_uri, "rdf:type", "uriv:Format" );
			if( isset( $format_info["desc"] ) && $format_info["desc"]!="" )
			{
				$desc = preg_replace( "/\. .*$/", ".", $format_info["desc"] );
				$graph->addCompressedTriple( $format_uri, "dcterms:description", $desc, "literal", "en" );
			}
			$graph->addCompressedTriple( $format_uri, "foaf:page", db2wiki( $format_uri ) );
			$graph->addCompressedTriple( db2wiki( $format_uri ), "rdf:type", "foaf:Document" );
		}
	}


	$lines = file( "$filepath/mime.types" );
	foreach( $lines as $line )
	{
		$line = chop( $line );
		if( preg_match( "/^#/", $line ) ) { continue; }
		if( preg_match( "/^([^\t]+)\t+([^\t]+)/", $line, $b ) )
		{
			list( $null, $mime, $types ) = $b;
			foreach( preg_split( "/ /", $types ) as $type )
			{
				if( $type == $suffix )
				{
					$graph->addCompressedTriple( "mime:$mime", "uriv:usedForSuffix", "suffix:$suffix" );
					$graph->addCompressedTriple( "mime:$mime", "rdf:type", "uriv:Mimetype" );
					$graph->addCompressedTriple( "mime:$mime", "rdfs:label", $mime, "literal" );
					$graph->addCompressedTriple( "mime:$mime", "skos:notation", $mime, "uriv:MimetypeDatatype" );
				}
			}
		}	
	}
}

function addMimeTrips( &$graph, $mime, $rec=true )
{
	global $filepath;
	$graph->addCompressedTriple( "mime:$mime", "rdf:type", "uriv:Mimetype" );
	$graph->addCompressedTriple( "mime:$mime", "rdfs:label", $mime, "literal" );
	$graph->addCompressedTriple( "mime:$mime", "skos:notation", $mime, "uriv:MimetypeDatatype" );

	$suffix = json_decode( file_get_contents( "$filepath/mime.json" ), true );
	if( isset($suffix[$mime]) )
	{
		foreach( $suffix[$mime] as $format_uri=>$format_info )
		{
			$graph->addCompressedTriple( "mime:$mime", "uriv:usedForFormat", $format_uri );
			$graph->addCompressedTriple( $format_uri, "rdfs:label", $format_info["label"], "literal", "en" );
			$graph->addCompressedTriple( $format_uri, "rdf:type", "uriv:Format" );
			if( isset( $format_info["desc"] ))
			{
				$desc = preg_replace( "/\. .*$/", ".", $format_info["desc"] );
				$graph->addCompressedTriple( $format_uri, "dcterms:description", $desc, "literal", "en" );
			}
			$graph->addCompressedTriple( $format_uri, "foaf:page", db2wiki( $format_uri ) );
			$graph->addCompressedTriple( db2wiki( $format_uri ), "rdf:type", "foaf:Document" );
		}
	}


	$lines = file( "$filepath/mime.types" );
	foreach( $lines as $line )
	{
		$line = chop( $line );
		if( preg_match( "/^#/", $line ) ) { continue; }
		if( preg_match( "/^([^\t]+)\t+([^\t]+)/", $line, $b ) )
		{
			list( $null, $amime, $types ) = $b;
			if( $amime == $mime )
			{
				foreach( preg_split( "/ /", $types ) as $suffix )
				{
					$graph->addCompressedTriple( "mime:$mime", "uriv:usedForSuffix", "suffix:$suffix" );
					$graph->addCompressedTriple( "suffix:$suffix", "rdfs:label", ".".$suffix, "literal" );
					$graph->addCompressedTriple( "suffix:$suffix", "skos:notation", $suffix, "uriv:SuffixDatatype" );
				}
			}
		}	
	}
}


function addSchemeTrips( &$graph, $scheme )
{
	global $filepath;
	$schemes = json_decode( file_get_contents( "$filepath/schemes.json" ), true );
	$graph->addCompressedTriple( "scheme:".$scheme, "rdf:type", "uriv:URIScheme" );
	$graph->addCompressedTriple( "scheme:".$scheme, "skos:notation", $scheme, "uriv:URISchemeDatatype" );

	$s = @$schemes[$scheme];
	if( !isset( $s ) ) { return; }

	$graph->addCompressedTriple( "scheme:".$scheme, "rdfs:label", $s["name"], "literal" );
	$tmap = array( 
		"permenent"=>"stable",
		"provisional"=>"testing",
		"historical"=>"archaic"
	);
	$graph->addCompressedTriple( "scheme:".$scheme, "vs:term_status", $tmap[$s["type"]], "literal" );
	if( @$s["url"] )
	{
		$graph->addCompressedTriple( "scheme:".$scheme, "foaf:page", $s["url"] );
		$graph->addCompressedTriple( "scheme:".$scheme, "uriv:IANAPage", $s["url"] );
		$graph->addCompressedTriple( $s["uri"], "rdf:type", "foaf:Document" );
	}
	foreach( $s["refs"] as $url=>$label )
	{
		$graph->addCompressedTriple( "scheme:".$scheme, "foaf:page", $url );
		$graph->addCompressedTriple( "scheme:".$scheme, "uriv:IANARef", $url );
		$graph->addCompressedTriple( $url, "rdf:type", "foaf:Document" );
		$graph->addCompressedTriple( $url, "rdfs:label", $label, "literal" );
	}
}

function addExtraVocabTrips( &$graph )
{
	$lines = array( 
"rdf:type	p	type",
"foaf:primaryTopic	p	primary topic",
"skos:notation	p	notation",
"vs:term_status	p	term status",
"foaf:page	p	page",
"foaf:homepage	p	homepage",
"http://dbpedia.org/property/cctld	p	country code top-level domain",
"owl:sameAs	p	is the same as",
"geo:lat	p	latitude",
"geo:long	p	longitude",
"occult:correspondsTo	p	corresponds to",
"dcterms:description	p	description",

"xsd:float	d	Floating-point number",
"xsd:positiveInteger	d	Postitive Integer",

"foaf:Document	c	Document",
"foaf:Organization	c	Organization",
"skos:Concept	c	Concept",
"http://dbpedia.org/ontology/Country	c	Country",
"rdfs:Class	c	Class",
"rdfs:Property	c	Property",
"rdfs:Datatype	c	Datatype",
);
	$tmap = array(
		""=>"skos:Concept",
		"c"=>"rdfs:Class",	
		"p"=>"rdfs:Property",
		"d"=>"rdfs:Datatype" );
	foreach( $lines as $line )
	{
		list( $term, $type, $name ) = preg_split( "/	/", chop( $line ) );
		$graph->addCompressedTriple( "$term", "rdf:type", $tmap[$type] );
		$graph->addCompressedTriple( "$term", "rdfs:isDefinedBy", "uriv:" );
		$graph->addCompressedTriple( "$term", "rdfs:label", $name, "literal" );
	}
}

function renderResource( $graph, $resource )
{
	$type = $resource->nodeType();
	$r = "";

	$r.="<div class='class'>";
	if( $resource->hasLabel() )
	{
		$r.= "<div class='classLabel'>".$resource->label();
		if( $resource->has("rdf:type") )
		{
			$r.=" <span class='classType'>[".$resource->all( "rdf:type" )->prettyLink()->join( ", " )."]</span>";
		}
		$r.= "</div>";
	}
	$r.="<div class='class2'>";
	$r.="<div class='uri'><span style='font-weight:bold'>URI: </span><span style='font-family:monospace'>".$resource->link()."</span></div>";
	$short = $long = "";
	foreach( $resource->relations() as $rel )
	{
		if( $rel == "http://www.w3.org/2000/01/rdf-schema#label" ) { continue; }
		if( $rel == "http://www.w3.org/2000/01/rdf-schema#isDefinedBy" ) { continue; }
		if( $rel == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type" ) { continue; }
		$follow_inverse = false;
		if( $rel == "http://dbpedia.org/property/cctld" ) { $follow_inverse = true; }
		if( $rel == "http://uri4uri.net/vocab#subDom" ) { $follow_inverse = true; }
		if( $rel == "http://uri4uri.net/vocab#usedForSuffix" ) { $follow_inverse = true; }
		if( $rel == "http://uri4uri.net/vocab#fragment" ) { $follow_inverse = true; }
		if( $rel == "http://uri4uri.net/vocab#identifiedBy" ) { $follow_inverse = true; }
#$r .= "<div>($rel)  ".$rel->nodeType()."</div>";
#$r.="<p>$rel :: $follow_inverse</p>";
		if( !$follow_inverse && $rel->nodeType() == "#inverseRelation" ) { continue; }
		if( $follow_inverse && $rel->nodeType() != "#inverseRelation" ) { continue; }
		#if( $rel->label() == "has type" ) { continue; } # hacky!

		$label = $rel->label();
		if( preg_match( '/^http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#_(\d+)$/', $rel, $b ) )
		{
			$label = "#".$b[1];
		}
		if( $rel->nodeType() == "#inverseRelation" ) { $label = "is $label of"; }
		$pred = "<a href='$rel' class='predicate'>$label</a>";

		foreach( $resource->all( $rel ) as $r2 )
		{
			$type = $r2->nodeType();
			if( $rel == "http://uri4uri.net/vocab#whoIsRecord" ) 
			{
				$short.= "<div class='relation'>$pred: \"<span class='pre literal'>".htmlspecialchars($r2)."</span>\"</div>";
				continue;
			}
			if( $type == "#literal" )
			{
				$short.= "<div class='relation'>$pred: \"<span class='literal'>".htmlspecialchars($r2)."</span>\"</div>";
				continue;
			}
			if( substr( $type, 0, 4 ) == "http" )
			{
				$rt = $graph->resource($type);
				$short.= "<div class='relation'>$pred: \"<span class='literal'>".htmlspecialchars($r2)."</span>\" <span class='datatype'>[".$rt->prettyLink()."]</span></div>";
				continue;
			}
			if( $r2->isType( "foaf:Document" ) )
			{
				$short.= "<div class='relation'>$pred: ".$r2->prettyLink()."</div>";
				continue;
			}
			$long.= "<table class='relation'><tr>";
			$long.= "<th>$pred:</th>";
			$long.= "<td class='object'>".renderResource( $graph, $r2 )."</td>";
			$long.= "</tr></table>";	
		}

	}
	$r .= $short.$long;
	#$r .= "<div style='font-size:80%'>".$resource->dump()."</div>";

	if( $resource->has( "geo:lat" ) && $resource->has( "geo:long" ) )
	{
		global $mapid;
		if( !@$mapid )
		{
			$mapid = 0;
      			$r.= '<script src="http://openlayers.org/api/OpenLayers.js"></script>';
		}
		$mapid++;

		$r.= '<div style="border:solid 1px #ccc;width:100%; height:200px; margin-top:1em !important" id="map'.$mapid.'"></div>
<script>
$(document).ready( function() {
	var map = new OpenLayers.Map("map'.$mapid.'");
	var wms = new OpenLayers.Layer.OSM();
	map.addLayer(wms);
	var lonLat = new OpenLayers.LonLat( '.$resource->getString( "geo:long" ).','.$resource->getString( "geo:lat" ).')
         	.transform(
            	new OpenLayers.Projection("EPSG:4326"), // transform from WGS 1984
            	map.getProjectionObject() // to Spherical Mercator Projection
          	);
	var zoom = 3;
	var markers = new OpenLayers.Layer.Markers( "Markers" );
	map.addLayer(markers);
	markers.addMarker(new OpenLayers.Marker(lonLat));
	map.setCenter( lonLat, zoom ); 
} );
</script>
';

	}

	$r .= "</div>";
	$r .= "</div>";
	

	return $r;
}

function db2wiki( $dbpedia_uri )
{
	$db_pre = "http://dbpedia.org/resource/";
	$wiki_pre = "http://en.wikipedia.org/wiki/";
	return $wiki_pre.substr( $dbpedia_uri, strlen( $db_pre ) );
}

<?php
if( !@$donegraphite )
{
	require_once( "../arc/ARC2.php" );
	require_once( "../Graphite/Graphite.php" );
}
$filepath = "/home/cjg/public_html/uri";
$BASE = "http://lemur.ecs.soton.ac.uk/~cjg/uri/";

#error_log( "Req: ".$_SERVER["REQUEST_URI"] );

$path = substr( $_SERVER["REQUEST_URI"], 10 );

if( $path == "/" )
{
	print "Homepage";
	exit;
}

if( !preg_match( "/^(vocab|uri|fragment|scheme|suffix|domain|mime)(\.(rdf|ttl|html|nt))?\/(.*)$/", $path, $b ) )
{
	serve404();
	exit;
}
@list( $dummy1, $type, $dummy2, $format, $id ) = $b;

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
	header( "Location: http://lemur.ecs.soton.ac.uk/~cjg/uri/$type.$format/$id" );
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
	print "<h1>".$doc->label()."</h1>";
	if( $doc->has( "foaf:primaryTopic" ) )
	{
		$uri = $doc->getString( "foaf:primaryTopic" );
		print "<p>About: <tt>$uri</tt></p>";
	}
	print $graph->dump();
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
	$graph->ns( "uriv",$BASE."vocab/" );
	$graph->ns( "scheme",$BASE."scheme/" );
	$graph->ns( "domain",$BASE."domain/" );
	$graph->ns( "suffix",$BASE."suffix/" );
	$graph->ns( "fragment",$BASE."fragment/" );
	$graph->ns( "mime",$BASE."mime/" );
	$graph->ns( "vs","http://www.w3.org/2003/06/sw-vocab-status/ns#" );
	
	return $graph;
}

function serve404()
{
	header( "HTTP/1.1 404 Not Found" );
	print "<h1>404 Not Found</h1>";
	print "<p>See, this is what Ted Nelson was trying to warn you about.</p>";
}

function graphVocab( $id )
{
	global $filepath;

	$lines = file( "$filepath/ns.txt" );
	$tmap = array(
		""=>"skos:Concept",
		"c"=>"rdfs:Class",	
		"p"=>"rdfs:Property",
		"d"=>"rdfs:Datatype" );
	$graph = initGraph();
	$graph->addCompressedTriple( "vocab:", "rdf:type", "owl:Ontology" );
	$graph->addCompressedTriple( "vocab:", "dcterms:title", "URI Namespace Vocabulary", "literal" );
	$graph->addCompressedTriple( "", "dcterms:title", "URI Namespace Vocabulary", "literal" );
	foreach( $lines as $line )
	{
		list( $term, $type, $name ) = preg_split( "/:/", chop( $line ) );
		$graph->addCompressedTriple( "vocab:$term", "rdf:type", $tmap[$type] );
		$graph->addCompressedTriple( "vocab:$term", "rdfs:isDefinedBy", "vocab:" );
		$graph->addCompressedTriple( "vocab:$term", "rdfs:label", $name, "literal" );
	}

	return $graph;
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

	addBoilerplateTrips( $graph, "fragment:".urlencode($uri), "$uri - URI with Fragment" );
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
	addBoilerplateTrips( $graph, "suffix:$suffix", "$suffix - Suffix" );
	addSuffixTrips( $graph, $suffix );
	return $graph;
}

function graphDomain( $domain )
{
	$graph = initGraph();
	addBoilerplateTrips( $graph, "domain:$domain", "$domain - Internet Domain" );
	addDomainTrips( $graph, $domain );
	return $graph;
}

function graphMime( $mime )
{
	$graph = initGraph();
	addBoilerplateTrips( $graph, "mime:$mime", "$mime - Mimetype" );
	addMimeTrips( $graph, $mime );
	return $graph;
}

function graphScheme( $scheme )
{
	$graph = initGraph();
	addBoilerplateTrips( $graph, "scheme:$scheme", "$scheme - URI Scheme" );
	addSchemeTrips( $graph, $scheme );
	return $graph;
}

function addBoilerplateTrips( &$graph, $uri, $title )
{
	$graph->addCompressedTriple( "", "rdf:type", "foaf:Document" );
	$graph->addCompressedTriple( "", "dcterms:title", $title, "literal" );
	$graph->addCompressedTriple( "", "foaf:primaryTopic", "$uri" );
	
# wikipedia data etc. not cc0
#	$graph->addCompressedTriple( "", "dcterms:license", "http://creativecommons.org/publicdomain/zero/1.0/" );
#	$graph->addCompressedTriple( "http://creativecommons.org/publicdomain/zero/1.0/", "rdfs:label", "CC0: Public Domain Dedication", "literal" );
	
}

function addFragmentTrips( &$graph, $uri )
{
	list( $uri_part, $fragment_part ) = preg_split( '/#/', $uri, 2 );

	$graph->addCompressedTriple( $uri, "uriv:identifiedBy", "uri:$uri" );
	$graph->addCompressedTriple( "uri:$uri", "rdf:type", "uriv:URI" );
	$graph->addCompressedTriple( "uri:$uri", "rdf:type", "uriv:FragmentURI" );
	$graph->addCompressedTriple( $uri, "uriv:fragement", $fragment_part, "literal" );

	addURITrips( $graph, $uri_part );

}

function addURITrips( &$graph, $uri )
{
	$b = parse_url( $uri );

	$graph->addCompressedTriple( $uri, "uriv:identifiedBy", "uri:$uri" );
	$graph->addCompressedTriple( "uri:$uri", "rdf:type", "uriv:URI" );
	$graph->addCompressedTriple( $uri, "skos:notation", $uri, "uriv:URIDatatype" );
	$graph->addCompressedTriple( $uri, "uriv:length", strlen( $uri ), "xsd:positiveInteger" );

	if( @$b["scheme"] )
	{
		$graph->addCompressedTriple( "uri:$uri", "uriv:scheme", "scheme:".$b["scheme"] );
		addSchemeTrips( $graph, $b["scheme"] );
		if( $b["scheme"] == "http" || $b["scheme"] == "https" || $b["scheme"] == "ftp" )
		{
			addHTTPSchemeTrips( $graph, $uri );
		}
	} # end scheme

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
			list( $key, $value ) = preg_split( '/=/', $kv, 2 );
			$graph->addCompressedTriple( "uri:$uri#query-$i", "uriv:key", $key, "uriv:QueryKey" );
			$graph->addCompressedTriple( "uri:$uri#query-$i", "uriv:value", $value, "uriv:QueryValue" );
		}
	}
}	
		



function addDomainTrips( &$graph, $domain )
{	
	$actual_domain = $domain;
	$nowww_actual_domain = $domain;
	if(substr(strtolower($nowww_actual_domain), 0, 4) == "www."){  $nowww_actual_domain = substr($actual_domain, 4);}

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
			$graph->addCompressedTriple( $place["uri"], "rdfs:label", $place["name"]."@en" );
			if( isset( $place["tld_uri"] ) )
			{
				$graph->addCompressedTriple( "domain:".$domain, "owl:sameAS", $place["tld_uri"] );
			}
			if( isset( $place["point"] ) )
			{
				list( $lat, $long ) = preg_split( "/\s+/", trim( $place["point"] ) );
				$graph->addCompressedTriple( $place["uri"], "geo:lat", $lat );
				$graph->addCompressedTriple( $place["uri"], "geo:long", $long );
			}
		}
	}
	if( isset( $zones["$domain"] ) )
	{
		$zone = $zones["$domain"] ;
		$graph->addCompressedTriple( "domain:$domain", "uriv:delegationRecordPage", "http://www.iana.org".$zone["url"] );
		$graph->addCompressedTriple( "domain:$domain", "foaf:page", "http://www.iana.org".$zone["url"] );
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
		$graph->addCompressedTriple( "domain:$domain#sponsor", "rdfs:label", $zone["sponsor"] );
	}
}

function addSuffixTrips( &$graph, $suffix )
{
	global $filepath;
	$graph->addCompressedTriple( "suffix:$suffix", "rdf:type", "uriv:Suffix" );
	$graph->addCompressedTriple( "suffix:$suffix", "rdfs:label", ".".$suffix, "literal" );
	$graph->addCompressedTriple( "suffix:$suffix", "skos:notation", $suffix, "uriv:SuffixDatatype" );
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
					$graph->addCompressedTriple( "mime:$mime", "uriv:usedForSufffix", "suffix:$suffix" );
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
	}
	foreach( $s["refs"] as $url=>$label )
	{
		$graph->addCompressedTriple( "scheme:".$scheme, "foaf:page", $url );
		$graph->addCompressedTriple( "scheme:".$scheme, "uriv:IANARef", $url );
		$graph->addCompressedTriple( $url, "rdfs:label", $label, "literal" );
	}
}

/*************************************************************************
php easy :: whois lookup script
==========================================================================
Author:      php easy code, www.phpeasycode.com
Web Site:    http://www.phpeasycode.com
Contact:     webmaster@phpeasycode.com
(heavily hacked by Christopher Gutteridge to do stuff on this site)
*************************************************************************/

// For the full list of TLDs/Whois servers see http://www.iana.org/domains/root/db/ and http://www.whois365.com/en/listtld/

function whoisservers()
{
	return array(
"ac.uk" => "whois.ja.net",
	"ac" =>"whois.nic.ac",
	"ae" =>"whois.nic.ae",
	"aero"=>"whois.aero",
	"af" =>"whois.nic.af",
	"ag" =>"whois.nic.ag",
	"al" =>"whois.ripe.net",
	"am" =>"whois.amnic.net",
	"arpa" =>"whois.iana.org",
	"as" =>"whois.nic.as",
	"asia" =>"whois.nic.asia",
	"at" =>"whois.nic.at",
	"au" =>"whois.aunic.net",
	"az" =>"whois.ripe.net",
	"ba" =>"whois.ripe.net",
	"be" =>"whois.dns.be",
	"bg" =>"whois.register.bg",
	"bi" =>"whois.nic.bi",
	"biz" =>"whois.biz",
	"bj" =>"whois.nic.bj",
	"br" =>"whois.registro.br",
	"bt" =>"whois.netnames.net",
	"by" =>"whois.ripe.net",
	"bz" =>"whois.belizenic.bz",
	"ca" =>"whois.cira.ca",
	"cat" =>"whois.cat",
	"cc" =>"whois.nic.cc",
	"cd" =>"whois.nic.cd",
	"ch" =>"whois.nic.ch",
	"ci" =>"whois.nic.ci",
	"ck" =>"whois.nic.ck",
	"cl" =>"whois.nic.cl",
	"cn" =>"whois.cnnic.net.cn",
	"com" =>"whois.verisign-grs.com",
	"coop" =>"whois.nic.coop",
	"cx" =>"whois.nic.cx",
	"cy" =>"whois.ripe.net",
	"cz" =>"whois.nic.cz",
	"de" =>"whois.denic.de",
	"dk" =>"whois.dk-hostmaster.dk",
	"dm" =>"whois.nic.cx",
	"dz" =>"whois.ripe.net",
	"edu" =>"whois.educause.edu",
	"ee" =>"whois.eenet.ee",
	"eg" =>"whois.ripe.net",
	"es" =>"whois.ripe.net",
	"eu" =>"whois.eu",
	"fi" =>"whois.ficora.fi",
	"fo" =>"whois.ripe.net",
	"fr" =>"whois.nic.fr",
	"gb" =>"whois.ripe.net",
	"gd" =>"whois.adamsnames.com",
	"ge" =>"whois.ripe.net",
	"gg" =>"whois.channelisles.net",
	"gi" =>"whois2.afilias-grs.net",
	"gl" =>"whois.ripe.net",
	"gm" =>"whois.ripe.net",
	"gov" =>"whois.nic.gov",
	"gr" =>"whois.ripe.net",
	"gs" =>"whois.nic.gs",
	"gw" =>"whois.nic.gw",
	"gy" =>"whois.registry.gy",
	"hk" =>"whois.hkirc.hk",
	"hm" =>"whois.registry.hm",
	"hn" =>"whois2.afilias-grs.net",
	"hr" =>"whois.ripe.net",
	"hu" =>"whois.nic.hu",
	"ie" =>"whois.domainregistry.ie",
	"il" =>"whois.isoc.org.il",
	"in" =>"whois.inregistry.net",
	"info" =>"whois.afilias.net",
	"int" =>"whois.iana.org",
	"io" =>"whois.nic.io",
	"iq" =>"vrx.net",
	"ir" =>"whois.nic.ir",
	"is" =>"whois.isnic.is",
	"it" =>"whois.nic.it",
	"je" =>"whois.channelisles.net",
	"jobs" =>"jobswhois.verisign-grs.com",
	"jp" =>"whois.jprs.jp",
	"ke" =>"whois.kenic.or.ke",
	"kg" =>"www.domain.kg",
	"ki" =>"whois.nic.ki",
	"kr" =>"whois.nic.or.kr",
	"kz" =>"whois.nic.kz",
	"la" =>"whois.nic.la",
	"li" =>"whois.nic.li",
	"lt" =>"whois.domreg.lt",
	"lu" =>"whois.dns.lu",
	"lv" =>"whois.nic.lv",
	"ly" =>"whois.nic.ly",
	"ma" =>"whois.iam.net.ma",
	"mc" =>"whois.ripe.net",
	"md" =>"whois.ripe.net",
	"me" =>"whois.meregistry.net",
	"mg" =>"whois.nic.mg",
	"mil" =>"whois.nic.mil",
	"mn" =>"whois.nic.mn",
	"mobi" =>"whois.dotmobiregistry.net",
	"ms" =>"whois.adamsnames.tc",
	"mt" =>"whois.ripe.net",
	"mu" =>"whois.nic.mu",
	"museum" =>"whois.museum",
	"mx" =>"whois.nic.mx",
	"my" =>"whois.mynic.net.my",
	"na" =>"whois.na-nic.com.na",
	"name" =>"whois.nic.name",
	"net" =>"whois.verisign-grs.net",
	"nf" =>"whois.nic.nf",
	"nl" =>"whois.domain-registry.nl",
	"no" =>"whois.norid.no",
	"nu" =>"whois.nic.nu",
	"nz" =>"whois.srs.net.nz",
	"org" =>"whois.pir.org",
	"pl" =>"whois.dns.pl",
	"pm" =>"whois.nic.pm",
	"pr" =>"whois.uprr.pr",
	"pro" =>"whois.registrypro.pro",
	"pt" =>"whois.dns.pt",
	"re" =>"whois.nic.re",
	"ro" =>"whois.rotld.ro",
	"ru" =>"whois.ripn.net",
	"sa" =>"whois.nic.net.sa",
	"sb" =>"whois.nic.net.sb",
	"sc" =>"whois2.afilias-grs.net",
	"se" =>"whois.iis.se",
	"sg" =>"whois.nic.net.sg",
	"sh" =>"whois.nic.sh",
	"si" =>"whois.arnes.si",
	"sk" =>"whois.ripe.net",
	"sm" =>"whois.ripe.net",
	"st" =>"whois.nic.st",
	"su" =>"whois.ripn.net",
	"tc" =>"whois.adamsnames.tc",
	"tel" =>"whois.nic.tel",
	"tf" =>"whois.nic.tf",
	"th" =>"whois.thnic.net",
	"tj" =>"whois.nic.tj",
	"tk" =>"whois.dot.tk",
	"tl" =>"whois.nic.tl",
	"tm" =>"whois.nic.tm",
	"tn" =>"whois.ripe.net",
	"to" =>"whois.tonic.to",
	"tp" =>"whois.nic.tl",
	"tr" =>"whois.nic.tr",
	"travel" =>"whois.nic.travel",
	"tv" => "tvwhois.verisign-grs.com",
	"tw" =>"whois.twnic.net.tw",
	"ua" =>"whois.net.ua",
	"ug" =>"whois.co.ug",
	"uk" =>"whois.nic.uk",
	"us" =>"whois.nic.us",
	"uy" =>"nic.uy",
	"uz" =>"whois.cctld.uz",
	"va" =>"whois.ripe.net",
	"vc" =>"whois2.afilias-grs.net",
	"ve" =>"whois.nic.ve",
	"vg" =>"whois.adamsnames.tc",
	"wf" =>"whois.nic.wf",
	"ws" =>"whois.website.ws",
	"yt" =>"whois.nic.yt",
	"yu" =>"whois.ripe.net");
}

function LookupDomain($domain, $whoisserver){
	$domain_parts = explode(".", $domain);
	$tld = strtolower(array_pop($domain_parts));
	$result = QueryWhoisServer($whoisserver, $domain);
	if(!$result) {
		return;
	}
	else {
		while(strpos($result, "Whois Server:") !== FALSE){
			preg_match("/Whois Server: (.*)/", $result, $matches);
			$secondary = $matches[1];
			if($secondary) {
				$result = QueryWhoisServer($secondary, $domain);
				$whoisserver = $secondary;
			}
		}
	}
	return "$result";
}

function LookupIP($ip) {
	$whoisservers = array(
		//"whois.afrinic.net", // Africa - returns timeout error :-(
		"whois.lacnic.net", // Latin America and Caribbean - returns data for ALL locations worldwide :-)
		"whois.apnic.net", // Asia/Pacific only
		"whois.arin.net", // North America only
		"whois.ripe.net" // Europe, Middle East and Central Asia only
	);
	$results = array();
	foreach($whoisservers as $whoisserver) {
		$result = QueryWhoisServer($whoisserver, $ip);
		if($result && !in_array($result, $results)) {
			$results[$whoisserver]= $result;
		}
	}
	$res = "RESULTS FOUND: " . count($results);
	foreach($results as $whoisserver=>$result) {
		$res .= "\n\n-------------\nLookup results for " . $ip . " from " . $whoisserver . " server:\n\n" . $result;
	}
	return $res;
}

function ValidateIP($ip) {
	$ipnums = explode(".", $ip);
	if(count($ipnums) != 4) {
		return false;
	}
	foreach($ipnums as $ipnum) {
		if(!is_numeric($ipnum) || ($ipnum > 255)) {
			return false;
		}
	}
	return $ip;
}

function ValidateDomain($domain) {
	if(!preg_match("/^([-a-z0-9]{2,100})\.([a-z\.]{2,8})$/i", $domain)) {
		return false;
	}
	return $domain;
}

function QueryWhoisServer($whoisserver, $domain) {
	$port = 43;
	$timeout = 5;
	$fp = @fsockopen($whoisserver, $port, $errno, $errstr, $timeout) or die("Socket Error " . $errno . " - " . $errstr);
	//if($whoisserver == "whois.verisign-grs.com") $domain = "=".$domain; // whois.verisign-grs.com requires the equals sign ("=") or it returns any result containing the searched string.
	fputs($fp, $domain . "\r\n");
	$out = "";
	while(!feof($fp)){
		$out .= fgets($fp);
	}
	fclose($fp);

	$res = "";
	if((strpos(strtolower($out), "error") === FALSE) && (strpos(strtolower($out), "not allocated") === FALSE)) {
		$rows = explode("\n", $out);
		foreach($rows as $row) {
			$row = trim($row);
			if(($row != '') && ($row{0} != '#') && ($row{0} != '%')) {
				$res .= $row."\n";
			}
		}
	}
	return $res;
}

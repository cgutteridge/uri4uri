<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("../lib/arc2/ARC2.php");
require_once("../lib/Graphite/Graphite.php");

$filepath = "/var/www/uri4uri/htdocs/data";
$BASE = "";
$PREFIX = "http://purl.org/uri4uri";
$PREFIX_OLD = "http://uri4uri.net";
$ARCHIVE_BASE = "//web.archive.org/web/20220000000000/";

$show_title = true;
#error_log("Req: ".$_SERVER["REQUEST_URI"]);

$path = substr($_SERVER["REQUEST_URI"], 0);

if($path == "")
{
	header("Location: $BASE/");
	exit;
}

if($path == "/robots.txt")
{
	header("Content-type: text/plain");
	print "User-agent: *\n";
	# prevent robots triggering a who-is
	print "Disallow: /uri.html/\n"; 
	print "Disallow: /uri.rdf/\n"; 
	print "Disallow: /uri.ttl/\n"; 
	print "Disallow: /uri.nt/\n"; 
	print "Disallow: /uri/\n"; 
	exit;
}

if(preg_match("/^\/?/", $path) && @$_GET["uri"])
{
	$uri4uri = "$BASE/uri/".urlencode($_GET["uri"]);
	header("Location: $uri4uri");
	exit;
}
if($path == "/aprilfools")
{
	$title = 'uri4uri';
	$show_title = false;
	$content = file_get_contents("ui/aprilfools.html");
	require_once("ui/template.php");
	exit;
}
if($path == "/")
{
	$title = 'uri4uri';
	$show_title = false;
	$content = file_get_contents("ui/homepage.html");
	require_once("ui/template.php");
	exit;
}
if(!preg_match("/^\/(vocab|uri|scheme|suffix|domain|mime)(\.(rdf|debug|ttl|html|nt|jsonld))?(\/(.*))?$/", $path, $b))
{
	serve404();
	exit;
}
@list($dummy1, $type, $dummy2, $format, $dummy3, $id) = $b;

if(!isset($format) || $format == "")
{	
	$wants = "text/turtle";

	if(isset($_SERVER["HTTP_ACCEPT"]))
	{
		$o = array('text/html'=>0, "text/turtle"=>0.02, "application/ld+json"=>0.015, "application/rdf+xml"=>0.01);
	
		$opts = preg_split("/\s*,\s*/", $_SERVER["HTTP_ACCEPT"]);
	
		foreach($opts as $opt)
		{
			$optparts = preg_split("/;/", $opt);
			$mime = array_shift($optparts);
			if(!isset($o[$mime])) { continue; }
				
			$o[$mime] = 1;
			foreach($optparts as $optpart)
			{
				list($k,$v) = preg_split("/=/", $optpart);
				if($k == "q") { $o[$mime] = $v; }
			}
		}
	
		$top_score = 0;
		foreach($o as $mime=>$score)
		{
			if($score > $top_score) 
			{
				$top_score = $score;
				$wants = $mime;
			}
		}
	}

	$format = "html";
	if($wants == "text/turtle") { $format = "ttl"; }
	if($wants == "application/rdf+xml") { $format = "rdf"; }
	if($wants == "application/ld+json") { $format = "jsonld"; }

	if($type == "uri") { $id = rawurlencode(rawurldecode($id)); }
	http_response_code(303);
	header("Location: $BASE/$type.$format/$id");
	exit;
}
if($type == "uri") { $graph = graphURI($id); }
elseif($type == "scheme") { $graph = graphScheme($id); }
elseif($type == "suffix") { $graph = graphSuffix($id); }
elseif($type == "domain") { $graph = graphDomain($id); }
elseif($type == "mime") { $graph = graphMime($id); }
elseif($type == "vocab") { $graph = graphVocab($id); }
else { serve404(); exit; }

if($format == "html")
{
	http_response_code(200);
	$document_url = $PREFIX.$_SERVER["REQUEST_URI"];
	$doc = $graph->resource($document_url);
	$title = $doc->label();
	$content = "";
	if($doc->has("foaf:primaryTopic"))
	{
		$uri = $doc->getString("foaf:primaryTopic");
		#$content.= "<p>URI: <tt>$uri</tt></p>";
	}
	$content.= "<p><span style='font-weight:bold'>Download data:</span> ";
	$content.= "<a href='$BASE/$type.ttl/$id'>Turtle</a>";
	$content.= " &bull; ";
	$content.= "<a href='$BASE/$type.nt/$id'>N-Triples</a>";
	$content.= " &bull; ";
	$content.= "<a href='$BASE/$type.rdf/$id'>RDF/XML</a>";
	$content.= " &bull; ";
	$content.= "<a href='$BASE/$type.jsonld/$id'>JSON-LD</a>";
	$content.= "</p>";

	if($type == "vocab")
	{
		$title = "uri4uri Vocabulary";

		$sections = array(
			array("Classes", "rdfs:Class", "classes"),
			array("Properties", "rdf:Property", "properties"),
			array("Datatypes", "rdfs:Datatype", "datatypes"),
			array("Concepts", "skos:Concept", "concepts"),
		);
		$l = array();
		$skips = array();
		foreach($sections as $s)
		{
			$l[$s[2]] = $graph->allOfType($s[1]);
			$skips []= "<a href='#".$s[2]."'>".$s[0]."</a>";
		}
		addExtraVocabTrips($graph);
		$content.="<p><strong style='font-weight:bold'>Jump to:</strong> ".join(" &bull; ", $skips)."</p>";
		$content.= "<style type='text/css'>.class .class { margin: 4em 0;} .class .class .class { margin: 1em 0; }</style>";
		$content.= "<div class='class'><div class='class2'>";
		$prefix_length = strlen("$PREFIX/vocab#");
		foreach($sections as $s)
		{
			$html = array();
			foreach($l[$s[2]] as $resource) 
			{ 
				$html[$resource->toString()]= "<a name='".substr($resource->toString(),$prefix_length)."'></a>".renderResource($graph, $resource); 
			}
			ksort($html);
			$content.= "<a name='".$s[2]."' /><div class='class'><div class='classLabel'>".$s[0]."</div><div class='class2'>";
			$content.= join("", $html);	
			$content.= "</div></div>";
		}

		$content.= "</div></div>";
	}
	else
	{ 
		addVocabTrips($graph);
		addExtraVocabTrips($graph);
		$resource = $graph->resource($uri);
		if($resource->has("rdf:type"))
		{
			$thingy_type =" <span class='classType'>[".$resource->all("rdf:type")->label()->join(", ")."]</span>";
		}
		$content.= renderResource($graph, $resource);
		#$content .= "<div style='font-size:80%'>".$graph->dump()."</div>";
	}
	require_once("ui/template.php");
}
elseif($format == "rdf")
{
	http_response_code(200);
	header("Content-type: application/rdf+xml");
	print $graph->serialize("RDFXML");
}
elseif($format == "nt")
{
	http_response_code(200);
	header("Content-type: text/plain");
	print $graph->serialize("NTriples");
}
elseif($format == "ttl")
{
	http_response_code(200);
	header("Content-type: text/turtle");
	print $graph->serialize("Turtle");
}
elseif($format == "jsonld")
{
	http_response_code(200);
	header("Content-type: application/ld+json");
	print $graph->serialize("JSONLD");
}
elseif($format == "debug")
{
	http_response_code(200);
	header("Content-type: text/plain");
	print_r($graph->toArcTriples());
}
else
{
	print "Unknown format (this can't happen)"; 
}
exit;


function initGraph()
{
	global $PREFIX;
	global $PREFIX_OLD;

	$graph = new Graphite();
	$graph->ns("uri","$PREFIX/uri/");
	$graph->ns("uriv","$PREFIX/vocab#");
	$graph->ns("scheme","$PREFIX/scheme/");
	$graph->ns("domain","$PREFIX/domain/");
	$graph->ns("suffix","$PREFIX/suffix/");
	$graph->ns("mime","$PREFIX/mime/");
	$graph->ns("olduri","$PREFIX_OLD/uri/");
	$graph->ns("olduriv","$PREFIX_OLD/vocab#");
	$graph->ns("oldscheme","$PREFIX_OLD/scheme/");
	$graph->ns("olddomain","$PREFIX_OLD/domain/");
	$graph->ns("oldsuffix","$PREFIX_OLD/suffix/");
	$graph->ns("oldmime","$PREFIX_OLD/mime/");
	$graph->ns("occult", "http://data.totl.net/occult/");
	$graph->ns("xtypes", "http://prefix.cc/xtypes/");
	$graph->ns("vs","http://www.w3.org/2003/06/sw-vocab-status/ns#");
	
	return $graph;
}

function serve404()
{
	http_response_code(404);
	$title = "404 Not Found";
	$content = "<p>See, it's things like this that are what Ted Nelson was trying to warn you about.</p>";
	require_once("ui/template.php");
}

function graphVocab($id)
{

	$graph = initGraph();
	addBoilerplateTrips($graph, "uriv:", "URI Vocabulary");
	$graph->addCompressedTriple("uriv:", "rdf:type", "owl:Ontology");
	$graph->addCompressedTriple("uriv:", "dcterms:title", "URI Vocabulary", "literal");
	addVocabTrips($graph);

	return $graph;
}

function linkOldConcept(&$graph, $term, $type)
{
	static $linkmap = array(
		"c"=>"owl:equivalentClass",	
		"p"=>"owl:equivalentProperty",
		"d"=>"owl:equivalentClass");
	$oldterm = "old$term";
	$graph->addCompressedTriple($term, "dcterms:replaces", $oldterm);
	$graph->addCompressedTriple($term, "skos:exactMatch", $oldterm);
	if(isset($linkmap[$type]))
	{
		$graph->addCompressedTriple($term, $linkmap[$type], $oldterm);
	}
}

function addVocabTrips(&$graph)
{
	global $filepath;
	$lines = file("$filepath/ns.tsv");
	static $tmap = array(
		""=>"skos:Concept",
		"c"=>"rdfs:Class",	
		"p"=>"rdf:Property",
		"d"=>"rdfs:Datatype");
	foreach($lines as $line)
	{
		list($term, $type, $name) = preg_split("/:/", chop($line));
		$term = "uriv:$term";
		$graph->addCompressedTriple($term, "rdf:type", $tmap[$type]);
		$graph->addCompressedTriple($term, "rdfs:isDefinedBy", "uriv:");
		$graph->addCompressedTriple($term, "rdfs:label", $name, "literal");
		linkOldConcept($graph, $term, $type);
	}
}


function graphURI($uri)
{
	$uri = rawurldecode($uri);
	$uriuri = "uri:".rawurlencode($uri);
	$graph = initGraph();
	addBoilerplateTrips($graph, $uriuri, $uri);
	addURITrips($graph, $uri);
	return $graph;
}


function graphSuffix($suffix)
{
	$graph = initGraph();
	$uri = $graph->expandURI("suffix:$suffix");
	addBoilerplateTrips($graph, "suffix:$suffix", $uri);
	addSuffixTrips($graph, $suffix);
	return $graph;
}

function graphDomain($domain)
{
	$graph = initGraph();
	$uri = $graph->expandURI("domain:$domain");
	addBoilerplateTrips($graph, "domain:$domain", $uri);
	addDomainTrips($graph, $domain);
	return $graph;
}

function graphMime($mime)
{
	$graph = initGraph();
	$uri = $graph->expandURI("mime:$mime");
	addBoilerplateTrips($graph, "mime:$mime", $uri);
	addMimeTrips($graph, $mime);
	return $graph;
}

function graphScheme($scheme)
{
	$graph = initGraph();
	$uri = $graph->expandURI("scheme:$scheme");
	addBoilerplateTrips($graph, "scheme:$scheme", $uri);
	addSchemeTrips($graph, $scheme);
	return $graph;
}

function addBoilerplateTrips(&$graph, $uri, $title)
{
	global $PREFIX;
	global $PREFIX_OLD;
	$document_url = $PREFIX.$_SERVER["REQUEST_URI"];
	$graph->addCompressedTriple($document_url, "rdf:type", "foaf:Document");
	$graph->addCompressedTriple($document_url, "dcterms:title", $title, "literal");
	$graph->addCompressedTriple($document_url, "foaf:primaryTopic", "$uri");
	
	linkOldConcept($graph, $uri, "");
	
# wikipedia data etc. not cc0
#"	$graph->addCompressedTriple("", "dcterms:license", "http://creativecommons.org/publicdomain/zero/1.0/");
#	$graph->addCompressedTriple("http://creativecommons.org/publicdomain/zero/1.0/", "rdfs:label", "CC0: Public Domain Dedication", "literal");
	
}


function addURITrips(&$graph, $uri)
{
	$uriuri = "uri:".rawurlencode($uri);
	$b = parse_url($uri);

	if(isset($b["fragment"]))
	{
		list($uri_part, $dummy) = preg_split('/#/', $uri);
		$graph->addCompressedTriple($uriuri, "rdf:type", "uriv:FragmentURI");
		$graph->addCompressedTriple($uriuri, "uriv:fragment", $b["fragment"], "xsd:string");
		$graph->addCompressedTriple($uriuri, "uriv:fragmentOf", "uri:".urlencode($uri_part));
	}

	$graph->addCompressedTriple($uri, "uriv:identifiedBy", $uriuri);
	$graph->addCompressedTriple($uriuri, "rdf:type", "uriv:URI");
	$graph->addCompressedTriple($uriuri, "skos:notation", $uri, "xsd:anyURI");

	if(@$b["scheme"])
	{
		$graph->addCompressedTriple($uriuri, "uriv:scheme", "scheme:".$b["scheme"]);
		addSchemeTrips($graph, $b["scheme"]);
		if($b["scheme"] == "http" || $b["scheme"] == "https" || $b["scheme"] == "ftp")
		{
			addHTTPSchemeTrips($graph, $uri);
		}
	} # end scheme
	
}

function addHTTPSchemeTrips(&$graph, $uri)
{
	$uriuri = "uri:".rawurlencode($uri);
	$b = parse_url($uri);

	if(@$b["host"])
	{
		$graph->addCompressedTriple($uriuri, "uriv:host", "domain:".$b["host"]);
		addDomainTrips($graph, $b["host"]);
		if(@$b["scheme"] == "http" || @$b["scheme"] == "https")
		{
			$homepage = $b["scheme"]."://".$b["host"];
			if(@$b["port"])
			{
				$homepage.= ":".$b["port"];
			}
			$homepage.="/";

			$graph->addCompressedTriple("domain:".$b["host"], "foaf:homepage", $homepage);
			$graph->addCompressedTriple($homepage, "rdf:type", "foaf:Document");
		}
	}


	if(@$b["port"])
	{
		$graph->addCompressedTriple($uriuri, "uriv:port", $b["port"], "xsd:positiveInteger");
	}
	else
	{
		$graph->addCompressedTriple($uriuri, "uriv:port", "uriv:noPortSpecified");
	}
	
	if(@$b["user"])
	{
		$graph->addCompressedTriple($uriuri, "uriv:user", $b["user"], "literal");
		if(@$b["pass"])
		{
			$graph->addCompressedTriple($uriuri, "uriv:pass", $b["pass"], "literal");
		}
		$graph->addCompressedTriple($uriuri, "uriv:account", "$uriuri#account-".$b["user"]);
		$graph->addCompressedTriple("$uriuri#account-".$b["user"], "rdf:type", "foaf:OnlineAccount");
		$graph->addCompressedTriple("$uriuri#account-".$b["user"], "rdfs:label", $b["user"], "xsd:string");
	}

	if(@$b["path"])
	{
		$graph->addCompressedTriple($uriuri, "uriv:path", $b["path"], "xsd:string");
		if(preg_match("/\.([^#\.\/]+)($|#)/", $b["path"], $bits ))
		{
			$graph->addCompressedTriple($uriuri, "uriv:suffix", "suffix:".$bits["1"]);
			addSuffixTrips($graph, $bits[1]);
		}
		if(preg_match("/\/([^#\/]+)($|#)/", $b["path"], $bits ))
		{
			$graph->addCompressedTriple($uriuri, "uriv:filename", $bits["1"], "xsd:string");
		}
	}

	if(@$b["query"])
	{
		$graph->addCompressedTriple($uriuri, "uriv:queryString", $b["query"], "xsd:string");
		$graph->addCompressedTriple($uriuri, "uriv:query", "$uriuri#query");
		$graph->addCompressedTriple("$uriuri#query", "rdf:type", "uriv:Query");
		$graph->addCompressedTriple("$uriuri#query", "rdf:type", "rdf:Seq");
		$i = 0;
		foreach(preg_split("/&/", $b["query"]) as $kv)
		{
			++$i;
			$graph->addCompressedTriple("$uriuri#query", "rdf:_$i", "$uriuri#query-$i");
			$graph->addCompressedTriple("$uriuri#query-$i", "rdf:type", "uriv:QueryKVP");
			if(preg_match('/=/', $kv))
			{
				list($key, $value) = preg_split('/=/', $kv, 2);
				$graph->addCompressedTriple("$uriuri#query-$i", "uriv:key", $key, "xsd:string");
				$graph->addCompressedTriple("$uriuri#query-$i", "uriv:value", $value, "xsd:string");
			}
		}
	}
}	
		



function addDomainTrips(&$graph, $domain)
{	
	$actual_domain = $domain;
	$nowww_actual_domain = $domain;
	if(substr(strtolower($nowww_actual_domain), 0, 4) == "www."){ $nowww_actual_domain = substr($actual_domain, 4);}

	require_once("../lib/whois.php");
	$whoisservers = whoisservers();
	global $filepath;

	global $schemes;
	if(!isset($schemes))
	{
		$schemes = json_decode(file_get_contents("$filepath/schemes.json"), true);
	}
	
	global $tlds;
	if(!isset($tlds))
	{
		$tlds = json_decode(file_get_contents("$filepath/tld.json"), true);
	}
	global $zones;
	if(!isset($zones))
	{
		$zones = json_decode(file_get_contents("$filepath/zones.json"), true);
	}

	$graph->addCompressedTriple("domain:".$domain, "rdf:type", "uriv:Domain");
#	if(ValidateDomain($domain)) 	
#	{
#		$graph->addCompressedTriple("domain:".$domain, "rdf:type", "uriv:Domain-Valid");
#	}	
#	else
#	{
#		$graph->addCompressedTriple("domain:".$domain, "rdf:type", "uriv:Domain-Invalid");
#	}
	$graph->addCompressedTriple("domain:".$domain, "rdfs:label", $domain, "literal");
	$graph->addCompressedTriple("domain:".$domain, "skos:notation", $domain, "uriv:DomainDatatype");

	# Super Domains
	while(preg_match("/\./", $domain))
	{
		$old_domain = $domain;
		$domain = preg_replace("/^[^\.]*\./", "", $domain);
			
		$graph->addCompressedTriple("domain:".$domain, "uriv:subDom", "domain:".$old_domain);
		$graph->addCompressedTriple("domain:".$domain, "rdf:type", "uriv:Domain");
#		if(ValidateDomain($domain)) 	
#		{
#			$graph->addCompressedTriple("domain:".$domain, "rdf:type", "uriv:Domain-Valid");
#		}	
#		else
#		{
#			$graph->addCompressedTriple("domain:".$domain, "rdf:type", "uriv:Domain-Invalid");
#		}
		$graph->addCompressedTriple("domain:".$domain, "rdfs:label", $domain, "literal");
		$graph->addCompressedTriple("domain:".$domain, "skos:notation", $domain, "uriv:DomainDatatype");

		if(isset($whoisservers["$domain"]))
		{
			$graph->addCompressedTriple("domain:".$domain, "uriv:hasWhoIsServer", "domain:".$whoisservers["$domain"]);
			$graph->addCompressedTriple("domain:".$whoisservers["$domain"], "rdf:type", "uriv:WhoisServer");
			
			$lookup = LookupDomain($nowww_actual_domain,$whoisservers[$domain]);
			if(@$lookup)
			{
				$graph->addCompressedTriple("domain:$nowww_actual_domain", "uriv:whoIsRecord", $lookup, "xsd:string");
			}
		}
	}

	# TLD Shenanigans...

	$graph->addCompressedTriple("domain:".$domain, "rdf:type", "uriv:TopLevelDomain");
	if(isset($tlds[".$domain"]))
	{
		$tld = $tlds[".$domain"] ;
		foreach($tld as $place)
		{
			$graph->addCompressedTriple($place["uri"], "http://dbpedia.org/property/cctld", "domain:$domain");
			$graph->addCompressedTriple($place["uri"], "rdfs:label", $place["name"], "xsd:string");
			$graph->addCompressedTriple($place["uri"], "rdf:type", "http://dbpedia.org/ontology/Country");
			$graph->addCompressedTriple($place["uri"], "foaf:page", db2wiki($place["uri"]));
			$graph->addCompressedTriple(db2wiki($place["uri"]), "rdf:type", "foaf:Document");
			if(isset($place["tld_uri"]))
			{
				$graph->addCompressedTriple("domain:".$domain, "owl:sameAs", $place["tld_uri"]);
				$graph->addCompressedTriple("domain:".$domain, "foaf:page", db2wiki($place["tld_uri"]));
				$graph->addCompressedTriple(db2wiki($place["tld_uri"]), "rdf:type", "foaf:Document");
			}
			if(isset($place["point"]))
			{
				list($lat, $long) = preg_split("/\s+/", trim($place["point"]));
				$lat = sprintf("%0.5f",$lat);
				$long = sprintf("%0.5f",$long);
				$graph->addCompressedTriple($place["uri"], "geo:lat", $lat, "xsd:float");
				$graph->addCompressedTriple($place["uri"], "geo:long", $long, "xsd:float");
			}
		}
	}
	if(isset($zones["$domain"]))
	{
		$zone = $zones["$domain"] ;
		$graph->addCompressedTriple("domain:$domain", "uriv:delegationRecordPage", "http://www.iana.org".$zone["url"]);
		$graph->addCompressedTriple("domain:$domain", "foaf:page", "http://www.iana.org".$zone["url"]);
		$graph->addCompressedTriple("http://www.iana.org".$zone["url"], "rdf:type", "foaf:Document");
		$typemap = array(
"country-code"=>"TopLevelDomain-CountryCode",
"generic"=>"TopLevelDomain-Generic",
"generic-restricted"=>"TopLevelDomain-GenericRestricted",
"infrastructure"=>"TopLevelDomain-Infrastructure",
"sponsored"=>"TopLevelDomain-Sponsored",
"test"=>"TopLevelDomain-Test");
		$graph->addCompressedTriple("domain:$domain", "rdf:type", "uriv:".$typemap[$zone["type"]]);
		$graph->addCompressedTriple("domain:$domain", "uriv:sponsor", "domain:$domain#sponsor");
		$graph->addCompressedTriple("domain:$domain#sponsor", "rdf:type", "foaf:Organization");
		$graph->addCompressedTriple("domain:$domain#sponsor", "rdfs:label", $zone["sponsor"], "xsd:string");
	}
}

function addSuffixTrips(&$graph, $suffix)
{
	global $filepath;
	$graph->addCompressedTriple("suffix:$suffix", "rdf:type", "uriv:Suffix");
	$graph->addCompressedTriple("suffix:$suffix", "rdfs:label", ".".$suffix, "xsd:string");
	$graph->addCompressedTriple("suffix:$suffix", "skos:notation", $suffix, "uriv:SuffixDatatype");

	$exts = json_decode(file_get_contents("$filepath/extensions.json"), true);
	if(isset($exts[$suffix]))
	{
		foreach($exts[$suffix] as $format_uri=>$format_info)
		{
			$graph->addCompressedTriple("suffix:$suffix", "uriv:usedForFormat", $format_uri);
			$graph->addCompressedTriple($format_uri, "rdfs:label", $format_info["label"], "literal", "en");
			$graph->addCompressedTriple($format_uri, "rdf:type", "uriv:Format");
			if(isset($format_info["desc"]) && $format_info["desc"]!="")
			{
				$desc = preg_replace("/\. .*$/", ".", $format_info["desc"]);
				$graph->addCompressedTriple($format_uri, "dcterms:description", $desc, "literal", "en");
			}
			$graph->addCompressedTriple($format_uri, "foaf:page", db2wiki($format_uri));
			$graph->addCompressedTriple(db2wiki($format_uri), "rdf:type", "foaf:Document");
		}
	}


	$lines = file("$filepath/mime.types");
	foreach($lines as $line)
	{
		$line = chop($line);
		if(preg_match("/^#/", $line)) { continue; }
		if(preg_match("/^([^\t]+)\t+([^\t]+)/", $line, $b))
		{
			list($null, $mime, $types) = $b;
			foreach(preg_split("/ /", $types) as $type)
			{
				if($type == $suffix)
				{
					$graph->addCompressedTriple("mime:$mime", "uriv:usedForSuffix", "suffix:$suffix");
					$graph->addCompressedTriple("mime:$mime", "rdf:type", "uriv:Mimetype");
					$graph->addCompressedTriple("mime:$mime", "rdfs:label", $mime, "literal");
					$graph->addCompressedTriple("mime:$mime", "skos:notation", $mime, "uriv:MimetypeDatatype");
				}
			}
		}	
	}
}

function addMimeTrips(&$graph, $mime, $rec=true)
{
	global $filepath;
	$graph->addCompressedTriple("mime:$mime", "rdf:type", "uriv:Mimetype");
	$graph->addCompressedTriple("mime:$mime", "rdfs:label", $mime, "literal");
	$graph->addCompressedTriple("mime:$mime", "skos:notation", $mime, "uriv:MimetypeDatatype");

	$suffix = json_decode(file_get_contents("$filepath/mime.json"), true);
	if(isset($suffix[$mime]))
	{
		foreach($suffix[$mime] as $format_uri=>$format_info)
		{
			$graph->addCompressedTriple("mime:$mime", "uriv:usedForFormat", $format_uri);
			$graph->addCompressedTriple($format_uri, "rdfs:label", $format_info["label"], "literal", "en");
			$graph->addCompressedTriple($format_uri, "rdf:type", "uriv:Format");
			if(isset($format_info["desc"]))
			{
				$desc = preg_replace("/\. .*$/", ".", $format_info["desc"]);
				$graph->addCompressedTriple($format_uri, "dcterms:description", $desc, "literal", "en");
			}
			$graph->addCompressedTriple($format_uri, "foaf:page", db2wiki($format_uri));
			$graph->addCompressedTriple(db2wiki($format_uri), "rdf:type", "foaf:Document");
		}
	}


	$lines = file("$filepath/mime.types");
	foreach($lines as $line)
	{
		$line = chop($line);
		if(preg_match("/^#/", $line)) { continue; }
		if(preg_match("/^([^\t]+)\t+([^\t]+)/", $line, $b))
		{
			list($null, $amime, $types) = $b;
			if($amime == $mime)
			{
				foreach(preg_split("/ /", $types) as $suffix)
				{
					$graph->addCompressedTriple("mime:$mime", "uriv:usedForSuffix", "suffix:$suffix");
					$graph->addCompressedTriple("suffix:$suffix", "rdfs:label", ".".$suffix, "literal");
					$graph->addCompressedTriple("suffix:$suffix", "skos:notation", $suffix, "uriv:SuffixDatatype");
				}
			}
		}	
	}
}


function addSchemeTrips(&$graph, $scheme)
{
	global $filepath;
	$schemes = json_decode(file_get_contents("$filepath/schemes.json"), true);
	$graph->addCompressedTriple("scheme:".$scheme, "rdf:type", "uriv:URIScheme");
	$graph->addCompressedTriple("scheme:".$scheme, "skos:notation", $scheme, "uriv:URISchemeDatatype");

	$s = @$schemes[$scheme];
	if(!isset($s)) { return; }

	$graph->addCompressedTriple("scheme:".$scheme, "rdfs:label", $s["name"], "literal");
	$tmap = array(
		"permenent"=>"stable",
		"provisional"=>"testing",
		"historical"=>"archaic"
	);
	$graph->addCompressedTriple("scheme:".$scheme, "vs:term_status", $tmap[$s["type"]], "literal");
	if(@$s["url"])
	{
		$graph->addCompressedTriple("scheme:".$scheme, "foaf:page", $s["url"]);
		$graph->addCompressedTriple("scheme:".$scheme, "uriv:IANAPage", $s["url"]);
		$graph->addCompressedTriple($s["uri"], "rdf:type", "foaf:Document");
	}
	foreach($s["refs"] as $url=>$label)
	{
		$graph->addCompressedTriple("scheme:".$scheme, "foaf:page", $url);
		$graph->addCompressedTriple("scheme:".$scheme, "uriv:IANARef", $url);
		$graph->addCompressedTriple($url, "rdf:type", "foaf:Document");
		$graph->addCompressedTriple($url, "rdfs:label", $label, "literal");
	}
}

function addExtraVocabTrips(&$graph)
{
	global $filepath;
	$lines = file("$filepath/nsextras.tsv");
	$tmap = array(
		""=>"skos:Concept",
		"c"=>"rdfs:Class",	
		"p"=>"rdf:Property",
		"d"=>"rdfs:Datatype");
	foreach($lines as $line)
	{
		list($term, $type, $name) = preg_split("/	/", chop($line));
		$graph->addCompressedTriple("$term", "rdf:type", $tmap[$type]);
		$graph->addCompressedTriple("$term", "rdfs:isDefinedBy", "uriv:");
		$graph->addCompressedTriple("$term", "rdfs:label", $name, "literal");
	}
}

function substituteLink($uri)
{
	global $BASE;
	global $PREFIX;
	global $PREFIX_OLD;
	global $ARCHIVE_BASE;
	if(substr($uri, 0, strlen($PREFIX)) === $PREFIX)
	{
		return $BASE.substr($uri, strlen($PREFIX));
	}
	if(substr($uri, 0, strlen($PREFIX_OLD)) === $PREFIX_OLD)
	{
		return $ARCHIVE_BASE.$uri;
	}
	return $uri;
}

function resourceLink($resource, $attributes = "")
{
	$uri = $resource->url();
	$uri_href = substituteLink($uri);
	return "<a title='".htmlspecialchars(urldecode($uri))."' href='".htmlspecialchars($uri_href)."'$attributes>".htmlspecialchars($uri)."</a>";
}

function prettyResourceLink($resource, $attributes = "")
{
	$uri = $resource->url();
	$uri_href = substituteLink($uri);
	$label = $uri;
	if($resource->hasLabel()) { $label = $resource->label(); }
	else if(preg_match('/^http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#_(\d+)$/', $uri, $b))
	{
		$label = "#".$b[1];
	}
	return "<a title='".htmlspecialchars(urldecode($uri))."' href='".htmlspecialchars($uri_href)."'$attributes>".htmlspecialchars($label)."</a>";
}

function renderResource($graph, $resource)
{
	global $PREFIX;
	$type = $resource->nodeType();
	$r = "";

	$r.="<div class='class'>";
	if($resource->hasLabel())
	{
		$r.= "<div class='classLabel'>".$resource->label();
		if($resource->has("rdf:type"))
		{
			$r.=" <span class='classType'>[".$resource->all("rdf:type")->map(function($r) { return prettyResourceLink($r); })->join(", ")."]</span>";
		}
		$r.= "</div>";
	}
	$r.="<div class='class2'>";
	$r.="<div class='uri'><span style='font-weight:bold'>URI: </span><span style='font-family:monospace'>".resourceLink($resource)."</span></div>";
	$short = $long = "";
	foreach($resource->relations() as $rel)
	{
		if($rel == "http://www.w3.org/2000/01/rdf-schema#label") { continue; }
		if($rel == "http://www.w3.org/2000/01/rdf-schema#isDefinedBy") { continue; }
		if($rel == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type") { continue; }
		$follow_inverse = false;
		if($rel == "http://dbpedia.org/property/cctld") { $follow_inverse = true; }
		if($rel == "$PREFIX/vocab#subDom") { $follow_inverse = true; }
		if($rel == "$PREFIX/vocab#usedForSuffix") { $follow_inverse = true; }
		#if($rel == "$PREFIX/vocab#fragmentOf") { $follow_inverse = true; }
		if($rel == "$PREFIX/vocab#identifiedBy") { $follow_inverse = true; }
#$r .= "<div>($rel) ".$rel->nodeType()."</div>";
#$r.="<p>$rel :: $follow_inverse</p>";
		if(!$follow_inverse && $rel->nodeType() == "#inverseRelation") { continue; }
		if($follow_inverse && $rel->nodeType() != "#inverseRelation") { continue; }
		#if($rel->label() == "has type") { continue; } # hacky!

		$label = $rel->label();
		if(preg_match('/^http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#_(\d+)$/', $rel, $b))
		{
			$label = "#".$b[1];
		}
		$pred = prettyResourceLink($rel, " class='predicate'");
		if($rel->nodeType() == "#inverseRelation") { $pred = "is \"$pred\" of"; }

		foreach($resource->all($rel) as $r2)
		{
			$type = $r2->nodeType();
			if($rel == "$PREFIX/vocab#whoIsRecord") 
			{
				$short.= "<div class='relation'>$pred: \"<span class='pre literal'>".htmlspecialchars($r2)."</span>\"</div>";
				continue;
			}
			if($type == "#literal")
			{
				$short.= "<div class='relation'>$pred: \"<span class='literal'>".htmlspecialchars($r2)."</span>\"</div>";
				continue;
			}
			if(substr($type, 0, 4) == "http")
			{
				$rt = $graph->resource($type);
				$short.= "<div class='relation'>$pred: \"<span class='literal'>".htmlspecialchars($r2)."</span>\" <span class='datatype'>[".prettyResourceLink($rt)."]</span></div>";
				continue;
			}
			if($r2 instanceof Graphite_Resource && $r2->isType("foaf:Document"))
			{
				$short.= "<div class='relation'>$pred: ".prettyResourceLink($r2)."</div>";
				continue;
			}
			$long.= "<table class='relation'><tr>";
			$long.= "<th>$pred:</th>";
			$long.= "<td class='object'>".renderResource($graph, $r2)."</td>";
			$long.= "</tr></table>";	
		}

	}
	$r .= $short.$long;
	#$r .= "<div style='font-size:80%'>".$resource->dump()."</div>";

	if($resource->has("geo:lat") && $resource->has("geo:long"))
	{
		global $mapid;
		if(!@$mapid)
		{
			$mapid = 0;
			$r.= '<script src="http://openlayers.org/api/OpenLayers.js"></script>';
		}
		$mapid++;

		$r.= '<div style="border:solid 1px #ccc;width:100%; height:200px; margin-top:1em !important" id="map'.$mapid.'"></div>
<script>
$(document).ready(function() {
	var map = new OpenLayers.Map("map'.$mapid.'");
	var wms = new OpenLayers.Layer.OSM();
	map.addLayer(wms);
	var lonLat = new OpenLayers.LonLat('.$resource->getString("geo:long").','.$resource->getString("geo:lat").')
	 	.transform(
			new OpenLayers.Projection("EPSG:4326"), // transform from WGS 1984
			map.getProjectionObject() // to Spherical Mercator Projection
		);
	var zoom = 3;
	var markers = new OpenLayers.Layer.Markers("Markers");
	map.addLayer(markers);
	markers.addMarker(new OpenLayers.Marker(lonLat));
	map.setCenter(lonLat, zoom); 
});
</script>
';

	}

	$r .= "</div>";
	$r .= "</div>";
	

	return $r;
}

function db2wiki($dbpedia_uri)
{
	$db_pre = "http://dbpedia.org/resource/";
	$wiki_pre = "http://en.wikipedia.org/wiki/";
	return $wiki_pre.substr($dbpedia_uri, strlen($db_pre));
}

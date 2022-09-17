<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("../lib/arc2/ARC2.php");
require_once("../lib/Graphite/Graphite.php");

$filepath = __DIR__."/data";
$BASE = "";
$PREFIX = "http://purl.org/uri4uri";
$PREFIX_OLD = "http://uri4uri.net";
$ARCHIVE_BASE = "//web.archive.org/web/20220000000000/";

$show_title = true;
#error_log("Req: ".$_SERVER["REQUEST_URI"]);

if(!function_exists('str_starts_with'))
{
  function str_starts_with($haystack, $needle)
  {
    return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
  }
}

$path = substr($_SERVER["REQUEST_URI"], 0);

if($path == "")
{
  header("Location: $BASE/");
  exit;
}

if($path == "/robots.txt")
{
  header("Content-type: text/plain");
  echo "User-agent: *\n";
  # prevent robots triggering a who-is
  echo "Disallow: /domain\n";
  echo "\n";
  echo "User-agent: ia_archiver\n";
  echo "Allow: /\n"; 
  exit;
}

function urlencode_minimal($str)
{
  return preg_replace_callback("/[^!$&-;=@A-Z_a-z~\u{00A0}-\u{D7FF}\u{F900}-\u{FDCF}\u{FDF0}-\u{FFEF}]+/u", function($matches)
  {
    return rawurlencode($matches[0]);
  }, $str);
}

function urlencode_utf8($str)
{
  return preg_replace_callback("/[\u{0080}-\u{FFFF}]+/u", function($matches)
  {
    return rawurlencode($matches[0]);
  }, $str);
}

function parse_url_fixed($uri)
{
  $has_query = strpos($uri, '?') !== false;
  $has_fragment = strpos($uri, '#') !== false;
  
  if(!$has_query && !$has_fragment)
  {
    // Fix "a:0" treated as host+port
    $uri = "$uri?";
  }
  
  $result = parse_url($uri);
  
  if(isset($result['host']) && isset($result['port']) && !isset($result['scheme']) && substr($uri, 0, 2) !== '//')
  {
    // Fix "a:0/" treated as host+port
    $result['scheme'] = $result['host'];
    unset($result['host']);
    $result['path'] = "$result[port]$result[path]"; // 0 will be trimmed however
    unset($result['port']);
  }
  
  if($has_query && !isset($result['query']))
  {
    // Include empty but existing query
    $result['query'] = '';
  }
  
  if($has_fragment && !isset($result['fragment']))
  {
    // Include empty but existing fragment
    $result['fragment'] = '';
  }
  
  return $result;
}

if(preg_match("/^\/?/", $path) && @$_GET["uri"])
{
  $uri4uri = "$BASE/uri/".urlencode_minimal($_GET["uri"]);
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
@list(, $type, , $format, , $id) = $b;

$decoded_id = rawurldecode($id);
$reencoded_id = urlencode_minimal($decoded_id);
if(urlencode_utf8($id) !== urlencode_utf8($reencoded_id))
{
  if(empty($format))
  {
    http_response_code(301);
    header("Location: $BASE/$type/$reencoded_id");
  }else{
    http_response_code(301);
    header("Location: $BASE/$type.$format/$reencoded_id");
  }
  exit;
}
$id = $decoded_id;

if(empty($format))
{
  $wants = "text/html";

  if(isset($_SERVER["HTTP_ACCEPT"]))
  {
    static $weights = array('text/html' => 0, "text/turtle" => 0.02, "application/ld+json" => 0.015, "application/rdf+xml" => 0.01);
  
    $opts = explode(',', $_SERVER["HTTP_ACCEPT"]);
    $accepts = array();
    foreach($opts as $opt)
    {
      $optparts = explode(';', trim($opt));
      $mime = array_shift($optparts);
      if(!isset($weights[$mime])) continue;
        
      $q = 1;
      foreach($optparts as $optpart)
      {
        @list($k, $v) = explode('=', $optpart);
        if($k === 'q')
        {
          $q = floatval($v);
          break;          
        }          
      }
      $accepts[$mime] = array($q, $weights[$mime]);
    }
  
    if(!empty($accepts))
    {
      uasort($accepts, function($a, $b)
      {
        if($a[0] == $b[0])
        {
          return ($a[1] < $b[1]) ? 1 : -1;
        }
        return ($a[0] < $b[0]) ? 1 : -1;
      });
      foreach($accepts as $mime => $weight)
      {
        $wants = $mime;
        break;
      }
    }
  }

  $format = "html";
  if($wants == "text/turtle") { $format = "ttl"; }
  if($wants == "application/rdf+xml") { $format = "rdf"; }
  if($wants == "application/ld+json") { $format = "jsonld"; }

  http_response_code(303);
  header("Location: $BASE/$type.$format/$reencoded_id");
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
  if($doc->has("foaf:primaryTopic"))
  {
    $uri = $doc->getString("foaf:primaryTopic");
  }
  ob_start();
  echo "<p><span style='font-weight:bold'>Download data:</span> ";
  $id_href = htmlspecialchars($reencoded_id);
  echo "<a href='$BASE/$type.ttl/$id_href'>Turtle</a>";
  echo " &bull; ";
  echo "<a href='$BASE/$type.nt/$id_href'>N-Triples</a>";
  echo " &bull; ";
  echo "<a href='$BASE/$type.rdf/$id_href'>RDF/XML</a>";
  echo " &bull; ";
  echo "<a href='$BASE/$type.jsonld/$id_href'>JSON-LD</a>";
  echo "</p>";

  $visited = array();
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
    echo "<p><strong style='font-weight:bold'>Jump to:</strong> ".join(" &bull; ", $skips)."</p>";
    echo "<style type='text/css'>.class .class { margin: 4em 0;} .class .class .class { margin: 1em 0; }</style>";
    echo "<div class='class'><div class='class2'>";
    $prefix_length = strlen("$PREFIX/vocab#");
    foreach($sections as $s)
    {
      $html = array();
      foreach($l[$s[2]] as $resource) 
      { 
        $html[$resource->toString()]= "<a name='".substr($resource->toString(),$prefix_length)."'></a>".renderResource($graph, $resource, $visited); 
      }
      ksort($html);
      echo "<a name='".$s[2]."' /><div class='class'><div class='classLabel'>".$s[0]."</div><div class='class2'>";
      echo join("", $html);  
      echo "</div></div>";
    }

    echo "</div></div>";
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
    renderResource($graph, $resource, $visited, $document_url);
  }
  $content = ob_get_contents();
  ob_end_clean();
  $head_content = "  <script type='application/ld+json'>".$graph->serialize("JSONLD")."</script>";
  require_once("ui/template.php");
}
elseif($format == "rdf")
{
  http_response_code(200);
  header("Content-type: application/rdf+xml");
  echo $graph->serialize("RDFXML");
}
elseif($format == "nt")
{
  http_response_code(200);
  header("Content-type: text/plain");
  echo $graph->serialize("NTriples");
}
elseif($format == "ttl")
{
  http_response_code(200);
  header("Content-type: text/turtle");
  echo $graph->serialize("Turtle");
}
elseif($format == "jsonld")
{
  http_response_code(200);
  header("Content-type: application/ld+json");
  echo $graph->serialize("JSONLD");
}
elseif($format == "debug")
{
  http_response_code(200);
  header("Content-type: text/plain");
  print_r($graph->toArcTriples());
}
else
{
  echo "Unknown format (this can't happen)"; 
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

function linkOldConcept($graph, $term, $type)
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

function addVocabTrips($graph)
{
  global $filepath;
  $lines = file("$filepath/ns.csv");
  static $tmap = array(
    ""=>"skos:Concept",
    "c"=>"rdfs:Class",  
    "p"=>"rdf:Property",
    "d"=>"rdfs:Datatype");
  foreach($lines as $line)
  {
    list($term, $type, $status, $name) = explode(",", rtrim($line));
    $term = "uriv:$term";
    $graph->addCompressedTriple($term, "rdf:type", $tmap[$type]);
    $graph->addCompressedTriple($term, "rdfs:isDefinedBy", "uriv:");
    $graph->addCompressedTriple($term, "rdfs:label", $name, "literal");
    if($status === 'old')
    {
      linkOldConcept($graph, $term, $type);
    }
  }
}


function graphURI($uri)
{
  $uriuri = "uri:".urlencode_minimal($uri);
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
  addDomainTrips($graph, $domain, true);
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

function addBoilerplateTrips($graph, $uri, $title)
{
  global $PREFIX;
  global $PREFIX_OLD;
  $document_url = $PREFIX.$_SERVER["REQUEST_URI"];
  $graph->addCompressedTriple($document_url, "rdf:type", "foaf:Document");
  $graph->addCompressedTriple($document_url, "dcterms:title", $title, "literal");
  $graph->addCompressedTriple($document_url, "foaf:primaryTopic", "$uri");
  
  linkOldConcept($graph, $uri, "");
  
# wikipedia data etc. not cc0
#"  $graph->addCompressedTriple("", "dcterms:license", "http://creativecommons.org/publicdomain/zero/1.0/");
#  $graph->addCompressedTriple("http://creativecommons.org/publicdomain/zero/1.0/", "rdfs:label", "CC0: Public Domain Dedication", "literal");
  
}

function processSparqlQuery($graph, $sparql)
{
  $lines = explode("\n", $sparql);
  foreach($lines as &$line)
  {
    $line = trim($line);
  }
  foreach($graph->ns as $prefix => $ns)
  {
    array_unshift($lines, "PREFIX $prefix: <$ns>");
  }
  return implode("\n", $lines);
}

function addWikidataResult($graph, $sparql)
{
  $sparql = processSparqlQuery($graph, $sparql);
  $url = 'https://query.wikidata.org/sparql?query='.rawurlencode($sparql);
  $graph->load($url);
}

function addDBPediaResult($graph, $sparql)
{
  $sparql = processSparqlQuery($graph, $sparql);
  $url = 'https://dbpedia.org/sparql/?query='.rawurlencode($sparql);
  $graph->load($url);
}

function addURITrips($graph, $uri)
{
  $uriuri = "uri:".urlencode_minimal($uri);
  $b = parse_url_fixed($uri);

  if(isset($b["fragment"]))
  {
    list($uri_part, $dummy) = preg_split('/#/', $uri);
    $graph->addCompressedTriple($uriuri, "rdf:type", "uriv:FragmentURI");
    $graph->addCompressedTriple($uriuri, "uriv:fragment", $b["fragment"], "xsd:string");
    $graph->addCompressedTriple($uriuri, "uriv:fragmentOf", "uri:".urlencode_minimal($uri_part));
  }

  if(isset($b["scheme"]))
  {
    $graph->addCompressedTriple($uriuri, "rdf:type", "uriv:URI");
  }else{
    $graph->addCompressedTriple($uriuri, "rdf:type", "uriv:RelativeURI");
  }
  $graph->addCompressedTriple($uriuri, "skos:notation", $uri, "xsd:anyURI");

  if(@$b["scheme"])
  {
    $graph->addCompressedTriple($uri, "uriv:identifiedBy", $uriuri);
    $graph->addCompressedTriple($uriuri, "uriv:scheme", "scheme:".$b["scheme"]);
    addSchemeTrips($graph, $b["scheme"]);
    if($b["scheme"] == "http" || $b["scheme"] == "https")
    {
      if(!empty($b["host"]))
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
  } # end scheme
  
  if(!empty($b["host"]))
  {
    $graph->addCompressedTriple($uriuri, "uriv:host", "domain:".$b["host"]);
    addDomainTrips($graph, $b["host"], false);
  }
  
  if(@$b["port"])
  {
    $graph->addCompressedTriple($uriuri, "uriv:port", $b["port"], "xsd:positiveInteger");
  }
  else if(!empty($b["host"]))
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

  if(isset($b["query"]))
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

function get_updated_json_file($file, &$renew)
{
  if(file_exists($file))
  {
    if(time() - filemtime($file) >= (48 * 60 + rand(-120, 120)) * 60)
    {
      touch($file);
      $renew = true;
    }else{
      $renew = false;
    }
    $data = json_decode(file_get_contents($file), true);
    if($data === null)
    {
      $renew = true;
    }else{
      return $data;
    }
  }
  return array();
}

function flush_output()
{
  header('Content-Encoding: none');
  header('Content-Length: '.ob_get_length());
  header('Connection: close');
  ob_end_flush();
  ob_flush();
  flush();
}

function get_schemes()
{
  static $cache_file = __DIR__.'/data/schemes.json';
  
  $data = get_updated_json_file($cache_file, $renew);
  if($renew)
  {
    ob_start();
    register_shutdown_function(function($cache_file)
    {
      flush_output();
      
      $info = stream_context_create(array('http' => array('user_agent' => 'uri4uri PHP/'.PHP_VERSION, 'header' => 'Connection: close\r\n')));
      libxml_set_streams_context($info);
      $xml = new DOMDocument;
      $xml->preserveWhiteSpace = false;
      if($xml->load('https://www.iana.org/assignments/uri-schemes/uri-schemes.xml') === false)
      {
        return;
      }
      $xpath = new DOMXPath($xml);
      $xpath->registerNamespace('reg', 'http://www.iana.org/assignments');
      
      $schemes = array();
      
      foreach($xpath->query('//reg:record') as $record)
      {
        $scheme = array();
        $id = trim($xpath->query('reg:value/text()', $record)->item(0)->wholeText);
        $scheme['id'] = $id;
        $scheme['type'] = strtolower(trim($xpath->query('reg:status/text()', $record)->item(0)->wholeText));
        $scheme['name'] = trim($xpath->query('reg:description/text()', $record)->item(0)->wholeText);
        $refs = array();
        foreach($xpath->query('reg:xref', $record) as $xref)
        {
          $type = $xpath->query('@type', $xref)->item(0)->nodeValue;
          $data = $xpath->query('@data', $xref)->item(0)->nodeValue;
          if($type === 'rfc')
          {
            $refs["http://www.rfc-editor.org/rfc/$data.txt"] = strtoupper($data);
          }else if($type === 'person')
          {
            foreach($xpath->query("//reg:person[@id = '$data']") as $person)
            {
              $name = trim($xpath->query('reg:name/text()', $person)->item(0)->wholeText);
              $uri = str_replace('&', '@', trim($xpath->query('reg:uri/text()', $person)->item(0)->wholeText));
              $refs[$uri] = $name;
              break;
            }
          }
        }
        $scheme['refs'] = $refs;
        foreach($xpath->query('reg:file[@type="template"]/text()', $record) as $template)
        {
          $template = trim($template->wholeText);
          $scheme['template'] = "http://www.iana.org/assignments/uri-schemes/$template";
          break;
        }
        $schemes[$id] = $scheme;
      }
      
      ksort($schemes);
      
      if(file_exists($cache_file))
      {
        file_put_contents($cache_file, json_encode($schemes, JSON_UNESCAPED_SLASHES));
      }
    }, $cache_file);
  }
  
  return $data;
}

function addDomainTrips($graph, $domain, $do_whois)
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
#  if(ValidateDomain($domain))   
#  {
#    $graph->addCompressedTriple("domain:".$domain, "rdf:type", "uriv:Domain-Valid");
#  }  
#  else
#  {
#    $graph->addCompressedTriple("domain:".$domain, "rdf:type", "uriv:Domain-Invalid");
#  }
  $graph->addCompressedTriple("domain:".$domain, "rdfs:label", $domain, "literal");
  $graph->addCompressedTriple("domain:".$domain, "skos:notation", $domain, "uriv:DomainDatatype");

  # Super Domains
  while(preg_match("/\./", $domain))
  {
    $old_domain = $domain;
    $domain = preg_replace("/^[^\.]*\./", "", $domain);
      
    $graph->addCompressedTriple("domain:".$domain, "uriv:subDom", "domain:".$old_domain);
    $graph->addCompressedTriple("domain:".$domain, "rdf:type", "uriv:Domain");
#    if(ValidateDomain($domain))   
#    {
#      $graph->addCompressedTriple("domain:".$domain, "rdf:type", "uriv:Domain-Valid");
#    }  
#    else
#    {
#      $graph->addCompressedTriple("domain:".$domain, "rdf:type", "uriv:Domain-Invalid");
#    }
    $graph->addCompressedTriple("domain:".$domain, "rdfs:label", $domain, "literal");
    $graph->addCompressedTriple("domain:".$domain, "skos:notation", $domain, "uriv:DomainDatatype");

    if($do_whois && isset($whoisservers["$domain"]))
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

function addSuffixTrips($graph, $suffix)
{
  global $PREFIX;
  $graph->addCompressedTriple("suffix:$suffix", "rdf:type", "uriv:Suffix");
  $graph->addCompressedTriple("suffix:$suffix", "rdfs:label", ".".$suffix, "xsd:string");
  $graph->addCompressedTriple("suffix:$suffix", "skos:notation", $suffix, "uriv:SuffixDatatype");

  $suffix_lower = strtolower($suffix);
  $suffix_upper = strtoupper($suffix);
  
  $suffix_uri = "$PREFIX/suffix/$suffix";

  $query = <<<EOF
CONSTRUCT {
  <$suffix_uri> uriv:usedForFormat ?format .
  ?format a uriv:Format .
  ?format rdfs:label ?formatLabel .
  ?format skos:altLabel ?formatAltLabel .
  ?format dct:description ?formatDescription .
  ?format foaf:page ?page .
  ?format owl:sameAs ?db_res .
  ?page a foaf:Document .
  ?mime uriv:usedForSuffix <$suffix_uri> .
  ?mime uriv:usedForFormat ?format .
  ?mime a uriv:Mimetype .
  ?mime rdfs:label ?mime_str .
  ?mime skos:notation ?mime_notation .
} WHERE {
  { ?format wdt:P1195 "$suffix_lower" . } UNION { ?format wdt:P1195 "$suffix_upper" . }
  OPTIONAL {
    { ?format wdt:P973 ?page . }
    UNION { ?format wdt:P856 ?page . }
    UNION { ?format wdt:P1343/wdt:P953 ?page . }
    UNION {
      ?page schema:about ?format .
      ?page schema:isPartOf <https://en.wikipedia.org/> .
      BIND(URI(REPLACE(STR(?page), "^https?://en.wikipedia.org/wiki/", "http://dbpedia.org/resource/")) AS ?db_res)
    }
    UNION {
      ?format ?prop ?page_id .
      ?prop_res wikibase:directClaim ?prop .
      ?prop_res wikibase:propertyType wikibase:ExternalId .
      ?prop_res wdt:P1896 ?source_website .
      ?prop_res wdt:P1630 ?formatter .
      BIND(URI(REPLACE(STR(?page_id), "^(.*)$", STR(?formatter))) AS ?page)
    }
  }
  OPTIONAL {
    ?format wdt:P1163 ?mime_str .
    FILTER (isLiteral(?mime_str) && STR(?mime_str) != "application/octet-stream")
    BIND(STRDT(?mime_str, uriv:MimetypeDatatype) AS ?mime_notation)
    BIND(URI(CONCAT("$PREFIX/mime/", ?mime_str)) AS ?mime)
  }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en" . }
}
EOF;
  addWikidataResult($graph, $query);
  
  /*$suffix_res = $graph->resource($suffix_uri);
  foreach($suffix_res->all('uriv:usedForFormat') as $format)
  {
    $query = <<<EOF
CONSTRUCT {
  <$format> owl:sameAs ?format .
} WHERE {
  <$format> (owl:sameAs|^owl:sameAs)+ ?format .
  FILTER REGEX(STR(?format), "^http://dbpedia.org/")
}
EOF;
    addDBPediaResult($graph, $query);
  }*/
}

function addMimeTrips($graph, $mime, $rec=true)
{
  global $PREFIX;
  $graph->addCompressedTriple("mime:$mime", "rdf:type", "uriv:Mimetype");
  $graph->addCompressedTriple("mime:$mime", "rdfs:label", $mime, "literal");
  $graph->addCompressedTriple("mime:$mime", "skos:notation", $mime, "uriv:MimetypeDatatype");
  
  $query = <<<EOF
CONSTRUCT {
  <$PREFIX/mime/$mime> uriv:usedForFormat ?format .
  ?format a uriv:Format .
  ?format rdfs:label ?formatLabel .
  ?format skos:altLabel ?formatAltLabel .
  ?format dct:description ?formatDescription .
  ?format foaf:page ?page .
  ?format owl:sameAs ?db_res .
  ?page a foaf:Document .
  <$PREFIX/mime/$mime> uriv:usedForSuffix ?suffix .
  ?suffix uriv:usedForFormat ?format .
  ?suffix a uriv:Suffix .
  ?suffix rdfs:label ?suffix_label .
  ?suffix skos:notation ?suffix_notation .
} WHERE {
  ?format wdt:P1163 "$mime" .
  OPTIONAL {
    { ?format wdt:P973 ?page . }
    UNION { ?format wdt:P856 ?page . }
    UNION { ?format wdt:P1343/wdt:P953 ?page . }
    UNION {
      ?page schema:about ?format .
      ?page schema:isPartOf <https://en.wikipedia.org/>
      BIND(URI(REPLACE(STR(?page), "^https?://en.wikipedia.org/wiki/", "http://dbpedia.org/resource/")) AS ?db_res)
    }
    UNION {
      ?format ?prop ?page_id .
      ?prop_res wikibase:directClaim ?prop .
      ?prop_res wikibase:propertyType wikibase:ExternalId .
      ?prop_res wdt:P1896 ?source_website .
      ?prop_res wdt:P1630 ?formatter .
      BIND(URI(REPLACE(STR(?page_id), "^(.*)$", STR(?formatter))) AS ?page)
    }
  }
  OPTIONAL {
    ?format wdt:P1195 ?suffix_strcs .
    FILTER isLiteral(?suffix_strcs)
    BIND(LCASE(STR(?suffix_strcs)) AS ?suffix_str)
    BIND(CONCAT(".", ?suffix_str) AS ?suffix_label)
    BIND(STRDT(?suffix_str, uriv:SuffixDatatype) AS ?suffix_notation)
    BIND(URI(CONCAT("$PREFIX/suffix/", ?suffix_str)) AS ?suffix)
  }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en" . }
}
EOF;
  addWikidataResult($graph, $query);
  
  @list(, $suffix_type) = explode("+", $mime, 2);
  if(!empty($suffix_type))
  {
    static $suffix_map = array("ber"=>"application/ber-stream", "der"=>"application/der-stream", "wbxml"=>"application/vnd.wap.wbxml");
    $base_mime = @$suffix_map[$suffix_type] ?? "application/$suffix_type";
    $graph->addCompressedTriple("mime:$mime", "skos:broader", "mime:$base_mime");
    $graph->addCompressedTriple("mime:$base_mime", "rdf:type", "uriv:Mimetype");
    $graph->addCompressedTriple("mime:$base_mime", "rdfs:label", $base_mime, "literal");
    $graph->addCompressedTriple("mime:$base_mime", "skos:notation", $base_mime, "uriv:MimetypeDatatype");
  }
}

function addSchemeTrips($graph, $scheme)
{
  $schemes = get_schemes();
  $graph->addCompressedTriple("scheme:$scheme", "rdf:type", "uriv:URIScheme");
  $graph->addCompressedTriple("scheme:$scheme", "skos:notation", $scheme, "uriv:URISchemeDatatype");

  if(!isset($schemes[$scheme]))
  {
    $graph->addCompressedTriple("scheme:$scheme", "vs:term_status", "unstable", "literal");
    return;
  }
  $s = $schemes[$scheme];

  $graph->addCompressedTriple("scheme:$scheme", "rdfs:label", $s["name"], "literal");
  $tmap = array(
    "permanent" => "stable",
    "provisional" => "testing",
    "historical" => "archaic"
  );
  $graph->addCompressedTriple("scheme:$scheme", "vs:term_status", $tmap[$s["type"]], "literal");
  if(isset($s['template']))
  {
    $graph->addCompressedTriple("scheme:$scheme", "rdfs:seeAlso", $s['template']);
    $graph->addCompressedTriple($s['template'], "rdf:type", "foaf:Document");
  }
  foreach($s['refs'] as $url => $label)
  {
    $graph->addCompressedTriple("scheme:$scheme", "uriv:IANARef", $url);
    if(str_starts_with($url, 'http://www.rfc-editor.org/rfc/'))
    {
      $graph->addCompressedTriple("scheme:$scheme", "foaf:page", $url);
      $graph->addCompressedTriple($url, "rdf:type", "foaf:Document");
      $graph->addCompressedTriple($url, "rdfs:label", $label, "literal");
    }else{
      $graph->addCompressedTriple($url, "rdf:type", "foaf:Agent");
      $graph->addCompressedTriple($url, "rdfs:label", $label, "literal");
    }
  }
}

function addExtraVocabTrips($graph)
{
  global $filepath;
  $lines = file("$filepath/nsextras.csv");
  $tmap = array(
    ""=>"skos:Concept",
    "c"=>"rdfs:Class",  
    "p"=>"rdf:Property",
    "d"=>"rdfs:Datatype");
  foreach($lines as $line)
  {
    list($term, $type, $name) = explode(",", rtrim($line));
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

function renderResource($graph, $resource, &$visited_nodes, $parent = null, $followed_relations = array())
{
  global $PREFIX;
  $type = $resource->nodeType();
  $visited_nodes[$resource->toString()] = $resource;
  echo "<div class='class'>";
  if($resource->hasLabel())
  {
    echo "<div class='classLabel'>".$resource->label();
    if($resource->has("rdf:type"))
    {
      echo " <span class='classType'>[".$resource->all("rdf:type")->map(function($r) { return prettyResourceLink($r); })->join(", ")."]</span>";
    }
    echo "</div>";
  }
  echo "<div class='class2'>";
  echo "<div class='uri'><span style='font-weight:bold'>URI: </span><span style='font-family:monospace'>".resourceLink($resource)."</span></div>";
  foreach($resource->relations() as $rel)
  {
    if($rel == "http://www.w3.org/2000/01/rdf-schema#label") { continue; }
    if($rel == "http://www.w3.org/2000/01/rdf-schema#isDefinedBy") { continue; }
    if($rel == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type") { continue; }
    if($rel == "http://www.w3.org/2004/02/skos/core#exactMatch") { continue; }
    if($rel == "http://purl.org/dc/terms/replaces") { continue; }

    $label = $rel->label();
    if(preg_match('/^http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#_(\d+)$/', $rel, $b))
    {
      $label = "#".$b[1];
    }
    $pred = prettyResourceLink($rel, " class='predicate'");
    if($rel->nodeType() == "#inverseRelation") { $pred = "is \"$pred\" of"; }
    
    $rel_key = $rel->nodeType().$rel->toString();
    $rel_followed = isset($followed_relations[$rel_key]);
    
    $res_keys = array();
    $res_map = array();
    foreach($resource->all($rel) as $r2)
    {
      $key = $r2->toString();
      if($key === $parent) continue;
      $res_keys[] = $key;
      $res_map[$key] = $r2;
    }
    natsort($res_keys);

    $close_element = null;
    foreach($res_keys as $key)
    {
      $r2 = $res_map[$key];
      $type = $r2->nodeType();
      if($rel == "$PREFIX/vocab#whoIsRecord") 
      {
        $value = "\"<span class='pre literal'>".htmlspecialchars($r2)."</span>\"";
      }else if($type == "#literal")
      {
        $value = "\"<span class='literal'>".htmlspecialchars($r2)."</span>\"";
      }else if(substr($type, 0, 4) == "http")
      {
        $rt = $graph->resource($type);
        $value = "\"<span class='literal'>".htmlspecialchars($r2)."</span>\" <span class='datatype'>[".prettyResourceLink($rt)."]</span>";
      }else if($rel_followed || isset($visited_nodes[$r2->toString()]) || ($r2 instanceof Graphite_Resource && $r2->isType("foaf:Document")))
      {
        $value = prettyResourceLink($r2);
      }else{
        if($close_element !== 'table')
        {
          if($close_element) echo "</$close_element>";
          echo "<table class='relation'>";
          $close_element = 'table';
        }
        echo "<tr>";
        echo "<th>$pred:</th>";
        $followed_inner = $followed_relations;
        $followed_inner[$rel_key] = $rel;
        echo "<td class='object'>";
        renderResource($graph, $r2, $visited_nodes, $resource->toString(), $followed_inner);
        echo "</td></tr>";
        continue;
      }
      if($close_element !== 'ul></div')
      {
        if($close_element) echo "</$close_element>";
        echo "<div class='relation'>$pred: <ul class='value-list'>";
        $close_element = 'ul></div';
      }
      echo "<li>$value</li>";
    }
    if($close_element) echo "</$close_element>";
  }

  if($resource->has("geo:lat") && $resource->has("geo:long"))
  {
    global $mapid;
    if(!@$mapid)
    {
      $mapid = 0;
      echo '<script src="http://openlayers.org/api/OpenLayers.js"></script>';
    }
    $mapid++;

    echo '<div style="border:solid 1px #ccc;width:100%; height:200px; margin-top:1em !important" id="map'.$mapid.'"></div>
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

  echo "</div>";
  echo "</div>";
}

function db2wiki($dbpedia_uri)
{
  $db_pre = "http://dbpedia.org/resource/";
  $wiki_pre = "http://en.wikipedia.org/wiki/";
  return $wiki_pre.substr($dbpedia_uri, strlen($db_pre));
}

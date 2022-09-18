<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("../lib/arc2/ARC2.php");
require_once("../lib/Graphite/Graphite.php");

require_once(".helpers.php");
require_once(".external.php");
require_once(".graph.php");
require_once(".render.php");

$filepath = __DIR__."/data";
$BASE = '';
$PREFIX = 'http://purl.org/uri4uri';
$PREFIX_OLD = 'http://uri4uri.net';
$ARCHIVE_BASE = '//web.archive.org/web/20220000000000/';

$show_title = true;
#error_log("Req: ".$_SERVER['REQUEST_URI']);

$path = substr($_SERVER['REQUEST_URI'], 0);

if($path == '')
{
  header("Location: $BASE/");
  exit;
}

if($path == "/robots.txt")
{
  header("Content-type: text/plain");
  echo "User-agent: *\n";
  echo "Allow: /\n"; 
  exit;
}

$construct_label_for = function($entity, $target = null)
{
  $label = $entity.'Label';
  $alt_label = $entity.'AltLabel';
  $description = $entity.'Description';
  if(empty($target)) $target = $entity;
  return <<<EOF
  $target rdfs:label $label .
  $target skos:altLabel $alt_label .
  $target dct:description $description .
EOF;
};

$construct_page_for = function($entity, $target = null)
{
  $page = $entity.'_page';
  $db = $entity.'_db';
  if(empty($target)) $target = $entity;
  return <<<EOF
  $target foaf:page $page .
  $target owl:sameAs $db .
  $page a foaf:Document .
EOF;
};

$match_page_for = function($entity, $ids = true)
{
  $page = $entity.'_page';
  $page_id = $entity.'_page_id';
  $prop = $entity.'_prop';
  $prop_res = $entity.'_prop_res';
  $formatter = $entity.'_formatter';
  $db = $entity.'_db';
  $ids_query = '';
  if($ids)
  {
    $ids_query = <<<EOF

    UNION {
      $entity $prop $page_id .
      $prop_res wikibase:directClaim $prop .
      $prop_res wikibase:propertyType wikibase:ExternalId .
      $prop_res wdt:P1896 [] .
      $prop_res wdt:P1630 $formatter .
      BIND(URI(REPLACE(STR($page_id), "^(.*)$", STR($formatter))) AS $page)
    }
EOF;
  }
  return <<<EOF
  OPTIONAL {
    { $entity wdt:P973 $page . }
    UNION { $entity wdt:P856 $page . }
    UNION { $entity wdt:P1343/wdt:P953 $page . }
    UNION {
      $page schema:about $entity .
      $page schema:isPartOf <https://en.wikipedia.org/>
      BIND(URI(REPLACE(STR($page), "^https?://en.wikipedia.org/wiki/", "http://dbpedia.org/resource/")) AS $db)
    }$ids_query
  }
EOF;
};

if(preg_match("/^\/?/", $path) && @$_GET['uri'])
{
  $uri4uri = "$BASE/uri/".urlencode_minimal($_GET['uri']);
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
if($type === 'domain')
{
  $decoded_id = idn_to_utf8($decoded_id, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
}
if($type !== 'uri')
{
  $decoded_id = strtolower($decoded_id);
}
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
  $wants = 'text/html';

  if(isset($_SERVER['HTTP_ACCEPT']))
  {
    static $weights = array(
      'text/html' => 0,
      'text/turtle' => 0.02,
      'application/ld+json' => 0.015,
      'application/rdf+xml' => 0.01
    );
  
    $opts = explode(',', $_SERVER['HTTP_ACCEPT']);
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

  $format = 'html';
  if($wants == 'text/turtle') $format = 'ttl';
  if($wants == 'application/rdf+xml') $format = 'rdf';
  if($wants == 'application/ld+json') $format = 'jsonld';

  http_response_code(303);
  header("Location: $BASE/$type.$format/$reencoded_id");
  exit;
}
if($type == 'uri') $graph = graphURI($id);
elseif($type == 'scheme') $graph = graphScheme($id);
elseif($type == 'suffix') $graph = graphSuffix($id);
elseif($type == 'domain') $graph = graphDomain($id);
elseif($type == 'mime') $graph = graphMime($id);
elseif($type == 'vocab') $graph = graphVocab($id);
else { serve404(); exit; }

if($format == 'html')
{
  http_response_code(200);
  $document_url = $PREFIX.$_SERVER['REQUEST_URI'];
  $doc = $graph->resource($document_url);
  $title = $doc->label();
  if($doc->has('foaf:primaryTopic'))
  {
    $uri = $doc->getString('foaf:primaryTopic');
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
  if($type == 'vocab')
  {
    $title = "uri4uri Vocabulary";

    $sections = array(
      array("Classes", 'rdfs:Class', 'classes'),
      array("Properties", 'rdf:Property', 'properties'),
      array("Datatypes", 'rdfs:Datatype', 'datatypes'),
      array("Concepts", 'skos:Concept', 'concepts'),
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
      $resources = array();
      foreach($l[$s[2]] as $resource) 
      { 
        $resources[$resource->toString()] = $resource; 
      }
      ksort($resources);
      echo "<a name='".$s[2]."'><div class='class'><div class='classLabel'>".$s[0]."</div><div class='class2'>";
      foreach($resources as $resource) 
      { 
        echo "<a name='".substr($resource->toString(),$prefix_length)."'></a>";
        renderResource($graph, $resource, $visited); 
      }
      echo "</div></div>";
    }

    echo "</div></div>";
  }
  else
  { 
    addVocabTrips($graph);
    addExtraVocabTrips($graph);
    $resource = $graph->resource($uri);
    if($resource->has('rdf:type'))
    {
      $thingy_type =" <span class='classType'>[".$resource->all('rdf:type')->label()->join(", ")."]</span>";
    }
    renderResource($graph, $resource, $visited, $document_url);
  }
  $content = ob_get_contents();
  ob_end_clean();
  $head_content = "  <script type='application/ld+json'>".$graph->serialize('JSONLD')."</script>";
  require_once("ui/template.php");
}
elseif($format == 'rdf')
{
  http_response_code(200);
  header("Content-type: application/rdf+xml");
  echo $graph->serialize('RDFXML');
}
elseif($format == 'nt')
{
  http_response_code(200);
  header("Content-type: text/plain");
  echo $graph->serialize('NTriples');
}
elseif($format == 'ttl')
{
  http_response_code(200);
  header("Content-type: text/turtle");
  echo $graph->serialize('Turtle');
}
elseif($format == 'jsonld')
{
  http_response_code(200);
  header("Content-type: application/ld+json");
  echo $graph->serialize('JSONLD');
}
elseif($format == 'debug')
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

function serve404()
{
  http_response_code(404);
  $title = "404 Not Found";
  $content = "<p>See, it's things like this that are what Ted Nelson was trying to warn you about.</p>";
  require_once("ui/template.php");
}

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(".helpers.php");
require_once(".external.php");
require_once(".graph.php");
require_once(".render.php");

$filepath = __DIR__."/data";
$BASE_PATH = '';
$BASE = (@$_SERVER['HTTPS'] === 'on' ? "https" : "http")."://$_SERVER[HTTP_HOST]";
$PREFIX = 'https://w3id.org/uri4uri';
$PREFIX_OLD = 'http://uri4uri.net';
$ARCHIVE_BASE = '//web.archive.org/web/20220000000000/';

$path = substr($_SERVER['REQUEST_URI'], 0);

if($path === '')
{
  http_response_code(301);
  header("Location: $BASE_PATH/");
  exit;
}

if($path === "/robots.txt")
{
  header("Content-Type: text/plain");
  echo "User-agent: *\n";
  echo "Allow: /\n"; 
  exit;
}

if($path === '/.well-known/void')
{
  http_response_code(302);
  header("Location: $BASE_PATH/void");
  exit;
}

if(str_starts_with($path, '/?') && count($_GET) === 1)
{
  foreach($_GET as $key => $value)
  {
    $target_uri = "$BASE_PATH/$key/".urlencode_minimal($value);
    header("Location: $target_uri");
    break;
  }
  exit;
}

$page_title = "";
$page_show_title = true;
$page_url = null;
$page_content = "";

if(str_starts_with($realpath = realpath("ui$path.html"), __DIR__.'/ui/') && file_exists($realpath))
{
  $page_show_title = false;
  $page_content = file_get_contents($realpath);
  require_once("ui/template.php");
  exit;
}
if($path == "/")
{
  $page_show_title = false;
  $page_content = file_get_contents("ui/homepage.html");
  require_once("ui/template.php");
  exit;
}
if(!preg_match('/^\/(vocab|void|triples|'.implode('|', Triples::types()).')(?:\.(rdf|debug|ttl|html|nt|jsonld))?(?:(\/)([^\?]*))?(\?.*)?$/', $path, $b))
{
  serve404();
  exit;
}
@list(, $type, $format, $separator, $id, $query) = $b;

$decoded_id = rawurldecode($id);
$decoded_id = normalizeEntityId($type, $decoded_id);
$reencoded_id = urlencode_minimal($decoded_id);
if(urlencode_utf8($id) !== urlencode_utf8($reencoded_id) || (empty($separator) && $type !== 'vocab' && $type !== 'void' && $type !== 'triples'))
{
  http_response_code(301);
  if(empty($format))
  {
    header("Location: $BASE_PATH/$type/$reencoded_id$query");
  }else{
    header("Location: $BASE_PATH/$type.$format/$reencoded_id$query");
  }
  exit;
}
if($type === 'vocab' || $type === 'void' || $type === 'triples')
{
  if(!empty($id))
  {
    $id = "#$id";
  }
  if(!empty($separator))
  {
    http_response_code(301);
    if(empty($format))
    {
      header("Location: $BASE_PATH/$type$query$id");
    }else{
      header("Location: $BASE_PATH/$type.$format$query$id");
    }
    exit;
  }
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
  if($reencoded_id !== '')
  {
    $reencoded_id = "/$reencoded_id";
  }
  header("Location: $BASE_PATH/$type.$format$reencoded_id$query");
  exit;
}

if($type === 'vocab') $graph = graphVocab($id);
else if($type === 'void') $graph = graphVoid($id);
else if($type === 'triples') $graph = graphTriples(@$_GET['subject'], @$_GET['predicate'], @$_GET['object']);
elseif($id === '') $graph = graphAll($type);
else $graph = graphEntity($type, $id);

if($format == 'html')
{
  http_response_code(200);
  $document_url = $BASE.$_SERVER['REQUEST_URI'];
  $doc = $graph->resource($document_url);
  $page_title = $doc->label();
  if($doc->has('foaf:primaryTopic'))
  {
    $page_url = $doc->getString('foaf:primaryTopic');
  }
  ob_start();
  echo "<nav><span style='font-weight:bold'>Download data:</span> ";
  $id_href = htmlspecialchars($reencoded_id);
  echo "<a href='$BASE_PATH/$type.ttl/$id_href'>Turtle</a>";
  echo " &bull; ";
  echo "<a href='$BASE_PATH/$type.nt/$id_href'>N-Triples</a>";
  echo " &bull; ";
  echo "<a href='$BASE_PATH/$type.rdf/$id_href'>RDF/XML</a>";
  echo " &bull; ";
  echo "<a href='$BASE_PATH/$type.jsonld/$id_href'>JSON-LD</a>";
  echo "</nav>";

  $visited = array();
  if($type === 'vocab' || $type === 'triples')
  {
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
    if($type === 'vocab')
    {
      addExtraVocabTriples($graph);
      echo "<nav><strong style='font-weight:bold'>Jump to:</strong> ".join(" &bull; ", $skips)."</nav>";
      $prefix_length = strlen("$PREFIX/vocab#");
    }else if($type === 'triples')
    {
      ?><figure>
<figcaption>Search</figcaption>
<p>Specify a <a href="https://www.hydra-cg.com/spec/latest/core/#templated-links">triple pattern</a> to retrieve the available resources, or use <a href="http://client.linkeddatafragments.org/#datasources=<?=rawurlencode("$BASE/void")?>&query=CONSTRUCT%20WHERE%20%7B%0A%20%20%3Fs%20skos%3Anotation%20%2280%22%5E%5Exsd%3AunsignedShort.%0A%7D%0A">SPARQL</a>.</p>
<form>
<label>Subject: <input autocomplete="off" spellcheck="false" type="url" style='padding:2px;width:90%;border-radius:5px' name='subject' id='subject' value="<?=htmlspecialchars(@$_GET['subject'])?>"></label><br>
<label>Predicate: <input autocomplete="off" spellcheck="false" type="url" placeholder="skos:notation" style='padding:2px;width:90%;border-radius:5px' name='predicate' id='predicate' value="<?=htmlspecialchars(@$_GET['predicate'])?>"></label><br>
<label>Object: <input autocomplete="off" spellcheck="false" placeholder="&quot;80&quot;^^xsd:unsignedShort" style='padding:2px;width:90%;border-radius:5px' name='object' id='object' value="<?=htmlspecialchars(@$_GET['object'])?>"></label><br>
<button type="submit">Search!</button>
</form>
</figure><?php
      $prefix_length = 0;
    }
    foreach($sections as $s)
    {
      $resources = array();
      foreach($l[$s[2]] as $resource) 
      { 
        $resources[$resource->toString()] = $resource; 
      }
      if(count($resources) == 0) continue;
      ksort($resources);
      echo "<figure>";
      echo "<figcaption id='".$s[2]."'>$s[0]</figcaption>";
      foreach($resources as $resource) 
      { 
        echo "<div id='".substr($resource->toString(),$prefix_length)."'>";
        renderResource($graph, $resource, $visited); 
        echo "</div>\n";
      }
      echo "</figure>";
    }
  }
  else
  {
    addVocabTriples($graph);
    addExtraVocabTriples($graph);
    $resource = $graph->resource($page_url);
    if($resource->has('rdf:type'))
    {
      $page_thingy_type = getResourceTypeString($graph, $resource);
    }
    if($resource->has('dct:description'))
    {
      $page_description = $resource->getString('dct:description');
    }
    renderResource($graph, $resource, $visited, resourceKey($doc));
  }
  $page_content = ob_get_contents();
  ob_end_clean();
  $page_head_content = "  <script type='application/ld+json'>".$graph->serialize('JSONLD')."</script>";
  require_once("ui/template.php");
}
elseif($format == 'rdf')
{
  http_response_code(200);
  header("Content-Type: application/rdf+xml");
  header("Content-Disposition: inline; filename=\"$id.rdf\"");
  echo $graph->serialize('RDFXML');
}
elseif($format == 'nt')
{
  http_response_code(200);
  header("Content-Type: application/n-triples");
  header("Content-Disposition: inline; filename=\"$id.nt\"");
  echo $graph->serialize('NTriples');
}
elseif($format == 'ttl')
{
  http_response_code(200);
  header("Content-Type: text/turtle");
  header("Content-Disposition: inline; filename=\"$id.ttl\"");
  echo $graph->serialize('Turtle');
}
elseif($format == 'jsonld')
{
  http_response_code(200);
  header("Content-Type: application/ld+json");
  header("Content-Disposition: inline; filename=\"$id.jsonld\"");
  echo $graph->serialize('JSONLD');
}
elseif($format == 'debug')
{
  http_response_code(200);
  header("Content-Type: text/plain");
  header("Content-Disposition: inline; filename=\"$id.txt\"");
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
  $page_title = "404 Not Found";
  $page_show_title = true;
  $page_url = null;
  $page_content = "<p>See, it's things like this that are what Ted Nelson was trying to warn you about.</p>";
  require_once("ui/template.php");
}

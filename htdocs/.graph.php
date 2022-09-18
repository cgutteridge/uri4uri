<?php

require_once("../lib/arc2/ARC2.php");
require_once("../lib/Graphite/Graphite.php");

function initGraph()
{
  global $PREFIX;
  global $PREFIX_OLD;

  $graph = new Graphite();
  $graph->ns('uriv', "$PREFIX/vocab#");
  $graph->ns('uri', "$PREFIX/uri/");
  $graph->ns('scheme', "$PREFIX/scheme/");
  $graph->ns('domain', "$PREFIX/domain/");
  $graph->ns('suffix', "$PREFIX/suffix/");
  $graph->ns('mime', "$PREFIX/mime/");
  $graph->ns('urnns', "$PREFIX/urn/");
  $graph->ns('olduri', "$PREFIX_OLD/uri/");
  $graph->ns('olduriv', "$PREFIX_OLD/vocab#");
  $graph->ns('oldscheme', "$PREFIX_OLD/scheme/");
  $graph->ns('olddomain', "$PREFIX_OLD/domain/");
  $graph->ns('oldsuffix', "$PREFIX_OLD/suffix/");
  $graph->ns('oldmime', "$PREFIX_OLD/mime/");
  $graph->ns('vs', "http://www.w3.org/2003/06/sw-vocab-status/ns#");
  
  return $graph;
}

$SPARQL = new class
{
  public function CONSTRUCT_LABEL($entity, $target = null)
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
  }
  
  public function CONSTRUCT_PAGE($entity, $target = null)
  {
    $page = $entity.'_page';
    $db = $entity.'_db';
    if(empty($target)) $target = $entity;
    return <<<EOF
  $target foaf:page $page .
  $target owl:sameAs $db .
  $page a foaf:Document .
EOF;
  }
  
  public function MATCH_PAGE($entity, $ids = true)
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
  }
};

function graphVocab($id)
{
  $graph = initGraph();
  addBoilerplateTrips($graph, 'uriv:', "URI Vocabulary");
  $graph->addCompressedTriple('uriv:', 'rdf:type', 'owl:Ontology');
  $graph->addCompressedTriple('uriv:', 'dcterms:title', "URI Vocabulary", 'literal');
  addVocabTrips($graph);

  return $graph;
}

function linkOldConcept($graph, $term, $type)
{
  static $linkmap = array(
    'c'=>'owl:equivalentClass',  
    'p'=>'owl:equivalentProperty',
    'd'=>'owl:equivalentClass');
  $oldterm = "old$term";
  $graph->addCompressedTriple($term, 'dcterms:replaces', $oldterm);
  $graph->addCompressedTriple($term, 'skos:exactMatch', $oldterm);
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
    ''=>'skos:Concept',
    'c'=>'rdfs:Class',  
    'p'=>'rdf:Property',
    'd'=>'rdfs:Datatype');
  foreach($lines as $line)
  {
    list($term, $type, $status, $name) = explode(",", rtrim($line));
    $term = "uriv:$term";
    $graph->addCompressedTriple($term, 'rdf:type', $tmap[$type]);
    $graph->addCompressedTriple($term, 'rdfs:isDefinedBy', 'uriv:');
    $graph->addCompressedTriple($term, 'rdfs:label', $name, 'literal');
    if($status === 'old')
    {
      linkOldConcept($graph, $term, $type);
    }
  }
}


function graphURI($uri)
{
  $uriuri = 'uri:'.urlencode_minimal($uri);
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

function graphURNNamespace($ns)
{
  $graph = initGraph();
  $uri = $graph->expandURI("urnns:$ns");
  addBoilerplateTrips($graph, "urnns:$ns", $uri, false);
  addURNNamespaceTrips($graph, $ns);
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

function addBoilerplateTrips($graph, $uri, $title, $link_old = true)
{
  global $PREFIX;
  global $PREFIX_OLD;
  $document_url = $PREFIX.$_SERVER['REQUEST_URI'];
  $graph->addCompressedTriple($document_url, 'rdf:type', 'foaf:Document');
  $graph->addCompressedTriple($document_url, 'dcterms:title', $title, 'literal');
  $graph->addCompressedTriple($document_url, 'foaf:primaryTopic', $uri);
  
  if($link_old) linkOldConcept($graph, $uri, '');
}

function addURITrips($graph, $uri)
{
  $uriuri = 'uri:'.urlencode_minimal($uri);
  $b = parse_url_fixed($uri);

  if(isset($b['fragment']))
  {
    list($uri_part, $dummy) = preg_split('/#/', $uri);
    $graph->addCompressedTriple($uriuri, 'rdf:type', 'uriv:FragmentURI');
    $graph->addCompressedTriple($uriuri, 'uriv:fragment', $b['fragment'], 'xsd:string');
    $graph->addCompressedTriple($uriuri, 'uriv:fragmentOf', 'uri:'.urlencode_minimal($uri_part));
  }

  if(isset($b['scheme']))
  {
    $graph->addCompressedTriple($uriuri, 'rdf:type', 'uriv:URI');
  }else{
    $graph->addCompressedTriple($uriuri, 'rdf:type', 'uriv:RelativeURI');
  }
  $graph->addCompressedTriple($uriuri, 'skos:notation', $uri, 'xsd:anyURI');

  if(@$b['scheme'])
  {
    $graph->addCompressedTriple($uri, 'uriv:identifiedBy', $uriuri);
    $graph->addCompressedTriple($uriuri, 'uriv:scheme', "scheme:$b[scheme]");
    addSchemeTrips($graph, $b['scheme']);
    if($b['scheme'] == 'http' || $b['scheme'] == 'https')
    {
      if(!empty($b['host']))
      {
        $homepage = "$b[scheme]://$b[host]";
        if(@$b['port'])
        {
          $homepage.= ":$b[port]";
        }
        $homepage.='/';
  
        $graph->addCompressedTriple("domain:$b[host]", 'foaf:homepage', $homepage);
        $graph->addCompressedTriple($homepage, 'rdf:type', 'foaf:Document');
      }
    }else if($b['scheme'] == 'urn' && isset($b['path']))
    {
      list($urnns) = explode(':', $b['path'], 2);
      $urnns = strtolower($urnns);
      $graph->addCompressedTriple($uriuri, 'uriv:urnNamespace', "urnns:$urnns");
      addURNNamespaceTrips($graph, $urnns);
    }
  }
  
  if(!empty($b['host']))
  {
    $graph->addCompressedTriple($uriuri, 'uriv:host', "domain:$b[host]");
    addDomainTrips($graph, $b['host']);
  }
  
  if(isset($b['port']))
  {
    $graph->addCompressedTriple($uriuri, 'uriv:port', $b['port'], 'xsd:positiveInteger');
  }
  else if(!empty($b['host']))
  {
    $graph->addCompressedTriple($uriuri, 'uriv:port', 'uriv:noPortSpecified');
  }
  
  if(isset($b['user']))
  {
    $graph->addCompressedTriple($uriuri, 'uriv:user', $b['user'], 'literal');
    if(isset($b['pass']))
    {
      $graph->addCompressedTriple($uriuri, 'uriv:pass', $b['pass'], 'literal');
    }
    $graph->addCompressedTriple($uriuri, 'uriv:account', "$uriuri#account-".$b['user']);
    $graph->addCompressedTriple("$uriuri#account-".$b['user'], 'rdf:type', 'foaf:OnlineAccount');
    $graph->addCompressedTriple("$uriuri#account-".$b['user'], 'rdfs:label', $b['user'], 'xsd:string');
  }

  if(isset($b['path']))
  {
    $graph->addCompressedTriple($uriuri, 'uriv:path', $b['path'], 'xsd:string');
    if(preg_match("/\.([^\.\/]+)$/", $b['path'], $bits))
    {
      $graph->addCompressedTriple($uriuri, 'uriv:suffix', "suffix:$bits[1]");
      addSuffixTrips($graph, $bits[1]);
    }
    if(preg_match("/\/([^\/]+)$/", $b['path'], $bits))
    {
      $graph->addCompressedTriple($uriuri, 'uriv:filename', $bits[1], 'xsd:string');
    }
  }

  if(isset($b['query']))
  {
    $graph->addCompressedTriple($uriuri, 'uriv:queryString', $b['query'], 'xsd:string');
    $graph->addCompressedTriple($uriuri, 'uriv:query', "$uriuri#query");
    $graph->addCompressedTriple("$uriuri#query", 'rdf:type', 'uriv:Query');
    $graph->addCompressedTriple("$uriuri#query", 'rdf:type', 'rdf:Seq');
    $i = 0;
    foreach(explode('&', $b['query']) as $kv)
    {
      ++$i;
      $graph->addCompressedTriple("$uriuri#query", "rdf:_$i", "$uriuri#query-$i");
      $graph->addCompressedTriple("$uriuri#query-$i", 'rdf:type', 'uriv:QueryKVP');
      if(strpos($kv, '=') !== false)
      {
        list($key, $value) = explode('=', $kv, 2);
        $graph->addCompressedTriple("$uriuri#query-$i", 'uriv:key', $key, 'literal');
        $graph->addCompressedTriple("$uriuri#query-$i", 'uriv:value', $value, 'literal');
      }
    }
  }
}

function addDomainTrips($graph, $domain)
{
  global $SPARQL;
  
  $domain_idn = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

  $graph->addCompressedTriple("domain:$domain", 'rdf:type', 'uriv:Domain');
  $graph->addCompressedTriple("domain:$domain", 'rdfs:label', $domain, 'literal');
  $graph->addCompressedTriple("domain:$domain", 'skos:notation', $domain, 'uriv:DomainDatatype');
  if($domain_idn !== $domain)
  {
    $graph->addCompressedTriple("domain:$domain", 'skos:notation', $domain_idn, 'uriv:DomainDatatype');
  }
  $graph->addCompressedTriple("domain:$domain", 'uriv:whoIsRecord', "https://www.iana.org/whois?q=$domain_idn");
  
  $special_domains = get_special_domains();
  $special_type = null;
  if(isset($special_domains["$domain_idn."]))
  {
    addIanaRecord($graph, "domain:$domain", $special_domains["$domain_idn."]);
    $special_type = 'uriv:Domain-Special';
  }

  # Super Domains
  if(strpos($domain, ".") !== false)
  {
    $old_domain = $domain;
    list($domain_name, $domain) = explode(".", $domain, 2);
    $graph->addCompressedTriple("domain:$domain", 'uriv:subDom', "domain:$old_domain");
    $inner_special_type = addDomainTrips($graph, $domain);
    $special_type = $special_type ?? $inner_special_type;
    if(!empty($special_type))
    {
      $graph->addCompressedTriple("domain:$old_domain", 'rdf:type', $special_type);
    }
    return $special_type;
  }
  if(!empty($special_type))
  {
    $graph->addCompressedTriple("domain:$domain", 'rdf:type', $special_type);
  }

  # TLD Shenanigans...

  $graph->addCompressedTriple("domain:$domain", 'rdf:type', 'uriv:TopLevelDomain');
  
  $domain_node = "<{$graph->expandURI("domain:$domain")}>";
  $query = <<<EOF
CONSTRUCT {
  $domain_node owl:sameAs ?domain .
  {$SPARQL->CONSTRUCT_PAGE('?domain', $domain_node)}
  {$SPARQL->CONSTRUCT_LABEL('?domain')}
  ?country <http://dbpedia.org/property/cctld> $domain_node .
  ?country a <http://dbpedia.org/ontology/Country> .
  {$SPARQL->CONSTRUCT_LABEL('?country')}
  {$SPARQL->CONSTRUCT_PAGE('?country')}
  ?country geo:lat ?lat .
  ?country geo:long ?long .
} WHERE {
  ?domain wdt:P5914 "$domain_idn" .
  {$SPARQL->MATCH_PAGE('?domain')}
  OPTIONAL {
    ?domain wdt:P17 ?country .
    {$SPARQL->MATCH_PAGE('?country', false)}
    OPTIONAL {
      ?country p:P625 ?coords .
      ?coords psv:P625 ?coord_node .
      ?coord_node wikibase:geoLatitude ?lat .  
      ?coord_node wikibase:geoLongitude ?long .
    }
  }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en" . }
}
EOF;
  addWikidataResult($graph, $query);
  
  $tlds = get_tlds();
  if(isset($tlds[$domain_idn]))
  {
    $tld = $tlds[$domain_idn];
    $graph->addCompressedTriple("domain:$domain", 'uriv:delegationRecordPage', "http://www.iana.org$tld[url]");
    $graph->addCompressedTriple("domain:$domain", 'foaf:page', "http://www.iana.org$tld[url]");
    $graph->addCompressedTriple("http://www.iana.org$tld[url]", 'rdf:type', 'foaf:Document');
    static $typemap = array(
"country-code"=>"TopLevelDomain-CountryCode",
"generic"=>"TopLevelDomain-Generic",
"generic-restricted"=>"TopLevelDomain-GenericRestricted",
"infrastructure"=>"TopLevelDomain-Infrastructure",
"sponsored"=>"TopLevelDomain-Sponsored",
"test"=>"TopLevelDomain-Test");
    $graph->addCompressedTriple("domain:$domain", 'rdf:type', 'uriv:'.$typemap[$tld['type']]);
    $graph->addCompressedTriple("domain:$domain", 'uriv:sponsor', "domain:$domain#sponsor");
    $graph->addCompressedTriple("domain:$domain#sponsor", 'rdf:type', 'foaf:Organization');
    $graph->addCompressedTriple("domain:$domain#sponsor", 'rdfs:label', $tld['sponsor'], 'xsd:string');
  }
  return $special_type;
}

function addSuffixTrips($graph, $suffix)
{
  global $SPARQL;
  $graph->addCompressedTriple("suffix:$suffix", 'rdf:type', 'uriv:Suffix');
  $graph->addCompressedTriple("suffix:$suffix", 'rdfs:label', ".".$suffix, 'xsd:string');
  $graph->addCompressedTriple("suffix:$suffix", 'skos:notation', $suffix, 'uriv:SuffixDatatype');

  $suffix_lower = strtolower($suffix);
  $suffix_upper = strtoupper($suffix);
  
  $suffix_node = "<{$graph->expandURI("suffix:$suffix")}>";
  $query = <<<EOF
CONSTRUCT {
  $suffix_node uriv:usedForFormat ?format .
  ?format a uriv:Format .
  {$SPARQL->CONSTRUCT_LABEL('?format')}
  {$SPARQL->CONSTRUCT_PAGE('?format')}
  ?mime uriv:usedForSuffix $suffix_node .
  ?mime uriv:usedForFormat ?format .
  ?mime a uriv:Mimetype .
  ?mime rdfs:label ?mime_str .
  ?mime skos:notation ?mime_notation .
} WHERE {
  { ?format wdt:P1195 "$suffix_lower" . } UNION { ?format wdt:P1195 "$suffix_upper" . }
  {$SPARQL->MATCH_PAGE('?format')}
  OPTIONAL {
    ?format wdt:P1163 ?mime_str .
    FILTER (isLiteral(?mime_str) && STR(?mime_str) != "application/octet-stream")
    BIND(STRDT(?mime_str, uriv:MimetypeDatatype) AS ?mime_notation)
    BIND(URI(CONCAT("{$graph->expandURI("mime:")}", ?mime_str)) AS ?mime)
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

function addIanaRecord($graph, $subject, $info)
{
  if(!is_array($info))
  {
    return;
  }
  if(empty($info))
  {
    $graph->addCompressedTriple($subject, 'vs:term_status', "unstable", 'literal');
    return;
  }
  
  static $tmap = array(
    "permanent" => "stable",
    "provisional" => "testing",
    "historical" => "archaic"
  );
  
  if(isset($info['name']))
  {
    $graph->addCompressedTriple($subject, 'rdfs:label', $info['name'], 'literal');
  }
  if(isset($info['type']))
  {
    $graph->addCompressedTriple($subject, 'vs:term_status', $tmap[$info['type']], 'literal');
  }else{
    $graph->addCompressedTriple($subject, 'vs:term_status', "stable", 'literal');
  }
  if(isset($info['template']))
  {
    $graph->addCompressedTriple($subject, 'rdfs:seeAlso', $info['template']);
    $graph->addCompressedTriple($info['template'], 'rdf:type', 'foaf:Document');
  }
  foreach($info['refs'] as $url => $label)
  {
    $graph->addCompressedTriple($subject, 'uriv:IANARef', $url);
    if(str_starts_with($url, 'http://www.rfc-editor.org/rfc/'))
    {
      $graph->addCompressedTriple($subject, 'foaf:page', $url);
      $graph->addCompressedTriple($url, 'rdf:type', 'foaf:Document');
    }else{
      $graph->addCompressedTriple($url, 'rdf:type', 'foaf:Agent');
    }
    if(!empty($label))
    {
      $graph->addCompressedTriple($url, 'rdfs:label', $label, 'literal');
    }
  }
}

function addMimeTrips($graph, $mime, $rec=true)
{
  global $SPARQL;
  $graph->addCompressedTriple("mime:$mime", 'rdf:type', 'uriv:Mimetype');
  $graph->addCompressedTriple("mime:$mime", 'rdfs:label', $mime, 'literal');
  $graph->addCompressedTriple("mime:$mime", 'skos:notation', $mime, 'uriv:MimetypeDatatype');
  
  $mime_types = get_mime_types();
  addIanaRecord($graph, "mime:$mime", @$mime_types[$mime]);
  
  $filter_query = '';
  if($mime === 'text/plain' || $mime === 'application/octet-stream')
  {
    $filter_query = <<<EOF
    ?format p:P1163 ?mime_prop .
    ?mime_prop ps:P1163 "$mime" .
    ?mime_prop prov:wasDerivedFrom ?mime_source .
    FILTER NOT EXISTS {
      ?format wdt:P1163 ?other_mime .
      FILTER(STR(?other_mime) != "$mime")
    }
EOF;
  }else{
    $filter_query = <<<EOF
    ?format wdt:P1163 "$mime" .
EOF;
  }
  
  $mime_node = "<{$graph->expandURI("mime:$mime")}>";
  $query = <<<EOF
CONSTRUCT {
  $mime_node uriv:usedForFormat ?format .
  ?format a uriv:Format .
  {$SPARQL->CONSTRUCT_LABEL('?format')}
  {$SPARQL->CONSTRUCT_PAGE('?format')}
  $mime_node uriv:usedForSuffix ?suffix .
  ?suffix uriv:usedForFormat ?format .
  ?suffix a uriv:Suffix .
  ?suffix rdfs:label ?suffix_label .
  ?suffix skos:notation ?suffix_notation .
} WHERE {
  $filter_query
  {$SPARQL->MATCH_PAGE('?format')}
  OPTIONAL {
    ?format wdt:P1195 ?suffix_strcs .
    FILTER isLiteral(?suffix_strcs)
    BIND(LCASE(STR(?suffix_strcs)) AS ?suffix_str)
    BIND(CONCAT(".", ?suffix_str) AS ?suffix_label)
    BIND(STRDT(?suffix_str, uriv:SuffixDatatype) AS ?suffix_notation)
    BIND(URI(CONCAT("{$graph->expandURI("suffix:")}", ?suffix_str)) AS ?suffix)
  }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en" . }
}
EOF;
  addWikidataResult($graph, $query);
  
  @list(, $suffix_type) = explode("+", $mime, 2);
  if(!empty($suffix_type))
  {
    static $suffix_map = array(
      'ber' => 'application/ber-stream',
      'der' => 'application/der-stream',
      'wbxml' => 'application/vnd.wap.wbxml'
    );
    $base_mime = @$suffix_map[$suffix_type] ?? "application/$suffix_type";
    $graph->addCompressedTriple("mime:$mime", 'skos:broader', "mime:$base_mime");
    $graph->addCompressedTriple("mime:$base_mime", 'rdf:type', 'uriv:Mimetype');
    $graph->addCompressedTriple("mime:$base_mime", 'rdfs:label', $base_mime, 'literal');
    $graph->addCompressedTriple("mime:$base_mime", 'skos:notation', $base_mime, 'uriv:MimetypeDatatype');
  }
}

function addSchemeTrips($graph, $scheme)
{
  global $SPARQL;
  $graph->addCompressedTriple("scheme:$scheme", 'rdf:type', 'uriv:URIScheme');
  $graph->addCompressedTriple("scheme:$scheme", 'skos:notation', $scheme, 'uriv:URISchemeDatatype');

  $schemes = get_schemes();
  addIanaRecord($graph, "scheme:$scheme", @$schemes[$scheme]);
  
  $scheme_node = "<{$graph->expandURI("scheme:$scheme")}>";
  $query = <<<EOF
CONSTRUCT {
  {$SPARQL->CONSTRUCT_LABEL('?scheme', $scheme_node)}
  $scheme_node owl:sameAs ?scheme .
  {$SPARQL->CONSTRUCT_PAGE('?scheme', $scheme_node)}
  ?technology uriv:usesScheme $scheme_node .
  {$SPARQL->CONSTRUCT_LABEL('?technology')}
  {$SPARQL->CONSTRUCT_PAGE('?technology')}
} WHERE {
  OPTIONAL {
    ?scheme wdt:P4742 "$scheme" .
    ?scheme wdt:P31 wd:Q37071 .
    {$SPARQL->MATCH_PAGE('?scheme')}
  }
  OPTIONAL {
    ?technology wdt:P4742 "$scheme" .
    FILTER NOT EXISTS {
      ?technology wdt:P31 wd:Q37071 .
    }
    {$SPARQL->MATCH_PAGE('?technology')}
  }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en" . }
}
EOF;
  addWikidataResult($graph, $query);
}

function addURNNamespaceTrips($graph, $ns)
{
  global $SPARQL;
  $graph->addCompressedTriple("urnns:$ns", 'rdf:type', 'uriv:URNNamespace');
  if(str_starts_with($ns, 'x-'))
  {
    $graph->addCompressedTriple("urnns:$ns", 'rdf:type', 'uriv:URNNamespace-Experimental');
  }else if(str_starts_with($ns, 'urn-'))
  {
    $graph->addCompressedTriple("urnns:$ns", 'rdf:type', 'uriv:URNNamespace-Informal');
  }else{
    $graph->addCompressedTriple("urnns:$ns", 'rdf:type', 'uriv:URNNamespace-Formal');
  }
  $graph->addCompressedTriple("urnns:$ns", 'skos:notation', $ns, 'uriv:URNNamespaceDatatype');

  $namespaces = get_urn_namespaces();
  addIanaRecord($graph, "urnns:$ns", @$namespaces[$ns]);
  
  $ns_node = "<{$graph->expandURI("urnns:$ns")}>";
  $query = <<<EOF
CONSTRUCT {
  ?technology uriv:usesNamespace $ns_node .
  {$SPARQL->CONSTRUCT_LABEL('?technology')}
  {$SPARQL->CONSTRUCT_PAGE('?technology')}
} WHERE {
  ?technology wdt:P7470 "urn:$ns:\$1" .
  {$SPARQL->MATCH_PAGE('?technology')}
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en" . }
}
EOF;
  addWikidataResult($graph, $query);
}

function addExtraVocabTrips($graph)
{
  global $filepath;
  $lines = file("$filepath/nsextras.csv");
  $tmap = array(
    ''=>'skos:Concept',
    'c'=>'rdfs:Class',  
    'p'=>'rdf:Property',
    'd'=>'rdfs:Datatype');
  foreach($lines as $line)
  {
    list($term, $type, $name) = explode(",", rtrim($line));
    $graph->addCompressedTriple("$term", 'rdf:type', $tmap[$type]);
    $graph->addCompressedTriple("$term", 'rdfs:isDefinedBy', 'uriv:');
    $graph->addCompressedTriple("$term", 'rdfs:label', $name, 'literal');
  }
}

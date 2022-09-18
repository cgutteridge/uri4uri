<?php

function initGraph()
{
  global $PREFIX;
  global $PREFIX_OLD;

  $graph = new Graphite();
  $graph->ns('uri',"$PREFIX/uri/");
  $graph->ns('uriv',"$PREFIX/vocab#");
  $graph->ns('scheme',"$PREFIX/scheme/");
  $graph->ns('domain',"$PREFIX/domain/");
  $graph->ns('suffix',"$PREFIX/suffix/");
  $graph->ns('mime',"$PREFIX/mime/");
  $graph->ns('olduri',"$PREFIX_OLD/uri/");
  $graph->ns('olduriv',"$PREFIX_OLD/vocab#");
  $graph->ns('oldscheme',"$PREFIX_OLD/scheme/");
  $graph->ns('olddomain',"$PREFIX_OLD/domain/");
  $graph->ns('oldsuffix',"$PREFIX_OLD/suffix/");
  $graph->ns('oldmime',"$PREFIX_OLD/mime/");
  $graph->ns('occult', "http://data.totl.net/occult/");
  $graph->ns('xtypes', "http://prefix.cc/xtypes/");
  $graph->ns('vs',"http://www.w3.org/2003/06/sw-vocab-status/ns#");
  
  return $graph;
}

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
  $document_url = $PREFIX.$_SERVER['REQUEST_URI'];
  $graph->addCompressedTriple($document_url, 'rdf:type', 'foaf:Document');
  $graph->addCompressedTriple($document_url, 'dcterms:title', $title, 'literal');
  $graph->addCompressedTriple($document_url, 'foaf:primaryTopic', "$uri");
  
  linkOldConcept($graph, $uri, '');
  
# wikipedia data etc. not cc0
#"  $graph->addCompressedTriple('', 'dcterms:license', "http://creativecommons.org/publicdomain/zero/1.0/");
#  $graph->addCompressedTriple("http://creativecommons.org/publicdomain/zero/1.0/", 'rdfs:label', "CC0: Public Domain Dedication", 'literal');
  
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
    }
  } # end scheme
  
  if(!empty($b['host']))
  {
    $graph->addCompressedTriple($uriuri, 'uriv:host', "domain:$b[host]");
    addDomainTrips($graph, $b['host']);
  }
  
  if(@$b['port'])
  {
    $graph->addCompressedTriple($uriuri, 'uriv:port', $b['port'], 'xsd:positiveInteger');
  }
  else if(!empty($b['host']))
  {
    $graph->addCompressedTriple($uriuri, 'uriv:port', 'uriv:noPortSpecified');
  }
  
  if(@$b['user'])
  {
    $graph->addCompressedTriple($uriuri, 'uriv:user', $b['user'], 'literal');
    if(@$b['pass'])
    {
      $graph->addCompressedTriple($uriuri, 'uriv:pass', $b['pass'], 'literal');
    }
    $graph->addCompressedTriple($uriuri, 'uriv:account', "$uriuri#account-".$b['user']);
    $graph->addCompressedTriple("$uriuri#account-".$b['user'], 'rdf:type', 'foaf:OnlineAccount');
    $graph->addCompressedTriple("$uriuri#account-".$b['user'], 'rdfs:label', $b['user'], 'xsd:string');
  }

  if(@$b['path'])
  {
    $graph->addCompressedTriple($uriuri, 'uriv:path', $b['path'], 'xsd:string');
    if(preg_match("/\.([^#\.\/]+)($|#)/", $b['path'], $bits ))
    {
      $graph->addCompressedTriple($uriuri, 'uriv:suffix', "suffix:$bits[1]");
      addSuffixTrips($graph, $bits[1]);
    }
    if(preg_match("/\/([^#\/]+)($|#)/", $b['path'], $bits ))
    {
      $graph->addCompressedTriple($uriuri, 'uriv:filename', $bits['1'], 'xsd:string');
    }
  }

  if(isset($b['query']))
  {
    $graph->addCompressedTriple($uriuri, 'uriv:queryString', $b['query'], 'xsd:string');
    $graph->addCompressedTriple($uriuri, 'uriv:query', "$uriuri#query");
    $graph->addCompressedTriple("$uriuri#query", 'rdf:type', 'uriv:Query');
    $graph->addCompressedTriple("$uriuri#query", 'rdf:type', 'rdf:Seq');
    $i = 0;
    foreach(preg_split("/&/", $b['query']) as $kv)
    {
      ++$i;
      $graph->addCompressedTriple("$uriuri#query", "rdf:_$i", "$uriuri#query-$i");
      $graph->addCompressedTriple("$uriuri#query-$i", 'rdf:type', 'uriv:QueryKVP');
      if(preg_match('/=/', $kv))
      {
        list($key, $value) = preg_split('/=/', $kv, 2);
        $graph->addCompressedTriple("$uriuri#query-$i", 'uriv:key', $key, 'xsd:string');
        $graph->addCompressedTriple("$uriuri#query-$i", 'uriv:value', $value, 'xsd:string');
      }
    }
  }
}

function addDomainTrips($graph, $domain)
{
  global $PREFIX, $match_page_for, $construct_page_for, $construct_label_for;
  
  $zones = get_tlds();
  
  $domain_idn = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

  $graph->addCompressedTriple("domain:$domain", 'rdf:type', 'uriv:Domain');
  $graph->addCompressedTriple("domain:$domain", 'rdfs:label', $domain, 'literal');
  $graph->addCompressedTriple("domain:$domain", 'skos:notation', $domain, 'uriv:DomainDatatype');
  if($domain_idn !== $domain)
  {
    $graph->addCompressedTriple("domain:$domain", 'skos:notation', $domain_idn, 'uriv:DomainDatatype');
  }
  $graph->addCompressedTriple("domain:$domain", 'uriv:whoIsRecord', "https://www.iana.org/whois?q=$domain_idn");

  # Super Domains
  if(strpos($domain, ".") !== false)
  {
    $old_domain = $domain;
    list($domain_name, $domain) = explode(".", $domain, 2);
    $graph->addCompressedTriple("domain:$domain", 'uriv:subDom', "domain:$old_domain");
    return addDomainTrips($graph, $domain);
  }

  # TLD Shenanigans...

  $graph->addCompressedTriple("domain:$domain", 'rdf:type', 'uriv:TopLevelDomain');
  
  $domain_node = "<$PREFIX/domain/$domain>";
  $query = <<<EOF
CONSTRUCT {
  $domain_node owl:sameAs ?domain .
  {$construct_page_for('?domain', $domain_node)}
  {$construct_label_for('?domain')}
  ?country <http://dbpedia.org/property/cctld> <$PREFIX/domain/$domain> .
  ?country a <http://dbpedia.org/ontology/Country> .
  {$construct_label_for('?country')}
  {$construct_page_for('?country')}
  ?country geo:lat ?lat .
  ?country geo:long ?long .
} WHERE {
  ?domain wdt:P5914 "$domain_idn" .
  {$match_page_for('?domain')}
  OPTIONAL {
    ?domain wdt:P17 ?country .
    {$match_page_for('?country', false)}
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
  
  if(isset($zones[$domain_idn]))
  {
    $zone = $zones[$domain_idn] ;
    $graph->addCompressedTriple("domain:$domain", 'uriv:delegationRecordPage', "http://www.iana.org$zone[url]");
    $graph->addCompressedTriple("domain:$domain", 'foaf:page', "http://www.iana.org$zone[url]");
    $graph->addCompressedTriple("http://www.iana.org$zone[url]", 'rdf:type', 'foaf:Document');
    static $typemap = array(
"country-code"=>"TopLevelDomain-CountryCode",
"generic"=>"TopLevelDomain-Generic",
"generic-restricted"=>"TopLevelDomain-GenericRestricted",
"infrastructure"=>"TopLevelDomain-Infrastructure",
"sponsored"=>"TopLevelDomain-Sponsored",
"test"=>"TopLevelDomain-Test");
    $graph->addCompressedTriple("domain:$domain", 'rdf:type', 'uriv:'.$typemap[$zone['type']]);
    $graph->addCompressedTriple("domain:$domain", 'uriv:sponsor', "domain:$domain#sponsor");
    $graph->addCompressedTriple("domain:$domain#sponsor", 'rdf:type', 'foaf:Organization');
    $graph->addCompressedTriple("domain:$domain#sponsor", 'rdfs:label', $zone['sponsor'], 'xsd:string');
  }
}

function addSuffixTrips($graph, $suffix)
{
  global $PREFIX, $match_page_for, $construct_page_for, $construct_label_for;
  $graph->addCompressedTriple("suffix:$suffix", 'rdf:type', 'uriv:Suffix');
  $graph->addCompressedTriple("suffix:$suffix", 'rdfs:label', ".".$suffix, 'xsd:string');
  $graph->addCompressedTriple("suffix:$suffix", 'skos:notation', $suffix, 'uriv:SuffixDatatype');

  $suffix_lower = strtolower($suffix);
  $suffix_upper = strtoupper($suffix);
  
  $suffix_node = "<$PREFIX/suffix/$suffix>";
  $query = <<<EOF
CONSTRUCT {
  $suffix_node uriv:usedForFormat ?format .
  ?format a uriv:Format .
  {$construct_label_for('?format')}
  {$construct_page_for('?format')}
  ?mime uriv:usedForSuffix $suffix_node .
  ?mime uriv:usedForFormat ?format .
  ?mime a uriv:Mimetype .
  ?mime rdfs:label ?mime_str .
  ?mime skos:notation ?mime_notation .
} WHERE {
  { ?format wdt:P1195 "$suffix_lower" . } UNION { ?format wdt:P1195 "$suffix_upper" . }
  {$match_page_for('?format')}
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

function addIanaRecord($graph, $subject, $info)
{
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
  global $PREFIX, $match_page_for, $construct_page_for, $construct_label_for;
  $mime_types = get_mime_types();
  $graph->addCompressedTriple("mime:$mime", 'rdf:type', 'uriv:Mimetype');
  $graph->addCompressedTriple("mime:$mime", 'rdfs:label', $mime, 'literal');
  $graph->addCompressedTriple("mime:$mime", 'skos:notation', $mime, 'uriv:MimetypeDatatype');
  
  addIanaRecord($graph, "mime:$mime", @$mime_types[$mime]);
  
  $mime_node = "<$PREFIX/mime/$mime>";
  $query = <<<EOF
CONSTRUCT {
  $mime_node uriv:usedForFormat ?format .
  ?format a uriv:Format .
  {$construct_label_for('?format')}
  {$construct_page_for('?format')}
  $mime_node uriv:usedForSuffix ?suffix .
  ?suffix uriv:usedForFormat ?format .
  ?suffix a uriv:Suffix .
  ?suffix rdfs:label ?suffix_label .
  ?suffix skos:notation ?suffix_notation .
} WHERE {
  ?format wdt:P1163 "$mime" .
  {$match_page_for('?format')}
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
  global $PREFIX, $match_page_for, $construct_page_for, $construct_label_for;
  $schemes = get_schemes();
  $graph->addCompressedTriple("scheme:$scheme", 'rdf:type', 'uriv:URIScheme');
  $graph->addCompressedTriple("scheme:$scheme", 'skos:notation', $scheme, 'uriv:URISchemeDatatype');

  addIanaRecord($graph, "scheme:$scheme", @$schemes[$scheme]);
  
  $scheme_node = "<$PREFIX/scheme/$scheme>";
  $query = <<<EOF
CONSTRUCT {
  {$construct_label_for('?scheme', $scheme_node)}
  $scheme_node owl:sameAs ?scheme .
  {$construct_page_for('?scheme', $scheme_node)}
  ?technology uriv:usesScheme $scheme_node .
  {$construct_label_for('?technology')}
  {$construct_page_for('?technology')}
} WHERE {
  OPTIONAL {
    ?scheme wdt:P4742 "$scheme" .
    ?scheme wdt:P31 wd:Q37071 .
    {$match_page_for('?scheme')}
  }
  OPTIONAL {
    ?technology wdt:P4742 "$scheme" .
    FILTER NOT EXISTS {
      ?technology wdt:P31 wd:Q37071 .
    }
    {$match_page_for('?technology')}
  }
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

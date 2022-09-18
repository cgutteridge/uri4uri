<!DOCTYPE html>
<html lang="en" prefix="og: https://ogp.me/ns#">
 <head>
  <link href="/resources/site.css" rel="stylesheet" type="text/css">
  <meta charset="utf-8">
  <title>uri4uri<?=empty($page_title) ? "" : " &ndash; $page_title"?></title>
  <script type="text/javascript" src="/resources/jquery-1.9.1.min.js"></script>
  <meta property="og:title" content="<?=$page_title?>">
<?php if(!empty($page_url)) { ?>
  <meta property="og:url" content="<?=$page_url?>">
<?php } ?>
  <meta property="og:site_name" content="uri4uri">
  <meta name="theme-color" content="#000066">
<?php
if(!empty($page_head_content)) echo $page_head_content;
?>
 </head>
 <body>
   <?php 
	$qs = $_SERVER["REQUEST_URI"];
	if( $qs != "/" && $qs != "" ) 
	{ 
		print "<div class='gofaster'><a href='/'>« home</a></div>"; 
	} 
?>
   <h1><a href="/">uri4uri</a></h1>
   <div class="content">
<?php if( !isset( $page_show_title ) || $page_show_title ) { ?>
     <h2><?php print $page_title; if( @$page_thingy_type ) { print " $page_thingy_type"; } ?></h2>
<?php } ?>
     <div class="content2">
       <?php print $page_content; ?>
     </div>
   </div>
 </body>
</html>

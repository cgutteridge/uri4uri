<!DOCTYPE html>
<html lang='en-gb'>
 <head>
  <link href='/site.css' rel='stylesheet' type='text/css'>
  <title><?php print $title; ?></title>
  <script type='text/javascript' src='/jquery-1.9.1.min.js'></script>
  <meta charset="utf-8" />
 </head>
 <body>
   <?php 
	$qs = $_SERVER["REQUEST_URI"];
	if( $qs != "/" && $qs != "" ) 
	{ 
		print "<div class='gofaster'><a href='/'>« home</a></div>"; 
	} 
?>
   <h1><a href='/'>uri4uri.net</a></h1>
   <div class='content'>
<?php if( !isset( $show_title ) || $show_title ) { ?>
     <h2><?php print $title; if( @$thingy_type ) { print " $thingy_type"; } ?></h2>
<?php } ?>
     <div class='content2'>
       <?php print $content; ?>
     </div>
   </div>
 </body>
</html>

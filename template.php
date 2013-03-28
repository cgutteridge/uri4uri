<!DOCTYPE html>
<html>
 <head>
  <link href='/site.css' rel='stylesheet' type='text/css'>
  <title><?php print $title; ?></title>
 </head>
 <body>
   <h1><a href='/'>uri4uri.net</a></h1>
   <div class='content'>
     <h2><?php print $title; if( @$thingy_type ) { print " $thingy_type"; } ?></h2>
     <div class='content2'>
       <?php print $content; ?>
     </div>
   </div>
 </body>
</html>

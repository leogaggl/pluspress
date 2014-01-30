<?php
///////////////////////////////
// CONFIGURE YOUR PLUSRSS
///////////////////////////////

  // Set API key. Get your key at https://code.google.com/apis/console.
  $key = getenv('PLUS_KEY') ? getenv('PLUS_KEY') : 'AIzaSyCk2i4oXtPuRIBzwwkBhnMG6fsoIO0Bm9Y';

  // Set ID of Plus user. That's the long number in their profile URL.
  $uid = getenv('PLUS_ID') ? getenv('PLUS_ID') : '101636881032878340378';

  // Other parameters you can tweak if you like
  $size = 20; // number of RSS items
  $cachetime = 5 * 60;
  $cachefolder = getenv('PLUS_CACHE') ? getenv('PLUS_CACHE') : '/tmp';
  $cachefile = "$cachefolder/index-cached-".md5(@$_SERVER['REQUEST_URI']).".html";
  date_default_timezone_set('GMT');
  
  header('Content-Type: application/atom+xml');

///////////////////////////////
// SERVE FROM CACHE IF EXISTS
///////////////////////////////

  // http://simonwillison.net/2003/may/5/cachingwithphp/ modded
  if (file_exists($cachefile) && time() - $cachetime < filemtime($cachefile)) {
    print file_get_contents($cachefile);
    exit;
  }
  ob_start();

/////////////
// GO FETCH
/////////////

  $url = "https://www.googleapis.com/plus/v1/people/$uid/activities/public?key=$key&maxResults=$size";
  $activities = json_decode(get_remote($url));
  $items = $activities -> items;

/////////////////////////////////////////
// HELPERS TO PROCESS SOME OF THE DATA
//////////////////////////////////////////

  function get_remote($url) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);

    $contents = curl_exec($ch);
    curl_close($ch);
    return $contents;

  }

  function pubDate($item) { return gmdate(DATE_RFC822, strtotime($item -> published)); }

  function content($item) {

    $object = $item -> object;
    $content = '';

    if ($item->verb == 'share') {
      $source = "<a href='".$object->actor->url."'>".$object->actor->displayName."</a>";
      $content .= $item->annotation ."<p>&nbsp;<br/><em>".$source.":</em></p><blockquote>".$object->content."</blockquote>";
    } 
    elseif ($item->verb == 'note') {
    	$content .= $item->title;
    }
    else {
      $content .= iconv("UTF-8","UTF-8//IGNORE", $object->content);
    }

    if ($object->attachments and sizeof($object->attachments)) {
      $attachment = $object->attachments[0];
      if ($attachment->objectType == 'photo')
        $content.="<p><a href='".$attachment->url."'><img width='".$attachment->image->width."'height='".$attachment->image->height ."' src='".$attachment->image->url."\ /></a></p>";
      else if ($attachment->objectType='article')
        $content .= "<p><a href='".$attachment->url."'>".$attachment->displayName."</a></p>";
    }

    return html_entity_decode(strip_tags($content));

  }

//////////////////////
// PUMP OUT THE FEED
//////////////////////
?>
<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n" ?>
<rss xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom" version="2.0">
  <channel>
    <atom:link href="https://gaggl.com/plusrss.php" rel="self" type="application/rss+xml" />
    <title><?php echo $activities -> title ?></title>
    <link>http://plus.google.com/<?php echo $uid ?>/posts</link>
    <description>Google+ Public Stream - Leo Gaggl</description>
    <dc:date><?php echo sizeof($items) ? $items[0]->published : ""?></dc:date>
<?php foreach ($items as $item) {
   $item_content = content($item);
 ?>
    <item>
      <title><?php echo $item -> title ?>...</title>
      <link><?php echo $item -> url ?></link>
      <description><?php echo $item_content ?></description>
      <guid><?php echo $item -> url ?></guid>
      <dc:date><?php echo $item -> published ?></dc:date>
    </item>
<?php } ?>
  </channel>
</rss><?php
////////////////////////////
// WRITE ALL THAT TO CACHE
////////////////////////////
  $fp = fopen($cachefile, 'w');
  fwrite($fp, ob_get_contents());
  fclose($fp);
  ob_end_flush(); // Send the output to the browser
?>

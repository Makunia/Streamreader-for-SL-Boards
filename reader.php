<?php
//<link rel=stylesheet href="../liberay/lib.php" type="text/css">
//die(phpinfo());

error_reporting(0);
//error_reporting(E_ALL);
//ini_set('display_errors',1);
//ini_set('error_reporting', E_ALL);
//ini_set('display_startup_errors',1);

if (!isset($_GET["ip"])) die("Error");
$server = $_GET["ip"];
if (!isset($_GET["port"])) $port = "";
else $port = $_GET["port"];
$ip = $server;
if (!isset($_GET["stream"])) $stream_title = "Contact Cyber Ahn for Update";
else $stream_title = $_GET["stream"];
$cachIP = explode("://",$server);
$server = $cachIP[1];
$iceport = $port;
$iceserver = $server;
$write = "";
$page;

function getMp3StreamTitle($streamingUrl)
{
//$ua = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.110 Safari/537.36'; //we simulate a Chrome 27 on Linux browser
  $ua = 'WinampMPEG/2.9';
  $opts = [
            'http' => [
              'method' => 'GET',
              'header' => 'Icy-MetaData: 1',
              'accept' => 'audio/mpeg',
              'user_agent' => $ua
             ],
            'ssl' => [
              'method' => 'GET',
              'header' => 'Icy-MetaData: 1',
              'accept' => 'audio/mpeg',
              'user_agent' => $ua,
              'verify_peer' => false,
              'verify_peer_name' => false,
             ]
          ];
  $context = stream_context_create($opts); // we create stream context with "Icy-MetaData: 1" for request the icy-metaint that we need!
  $res = get_headers($streamingUrl, 1, $context); //get headers in our result variable (because we need the metaint and some other stuff how bitrate and samplerate etc.)
  $stream = fopen($streamingUrl, 'r', false, $context); //we open the stream buffer with the metadata header in context
  if ($stream && isset($res["icy-metaint"])) { //Check if Stream ok and we got the metaint value
    $size = ord(stream_get_contents($stream, 1, $res["icy-metaint"]))*16; //the metaint tells us the offset of the stream where begins the streammetadata the first byte multiply with 16 tells us the length
    $metaData = explode(";", trim(stream_get_contents($stream, $size))); //we take the metadata and trim it because all not needed bytes (from 16) filled with escape byte 0x00 and we split it as array (for multiple metadata) split sign is ";"
    foreach($metaData as $mDat) { //foreach the array
      $mSplit = explode("=", $mDat); //every metadata is splited with varname=value so we split it with the "="
      $k = htmlspecialchars(array_shift($mSplit)); //we take the first array and remove it
      $v = htmlspecialchars(implode("=", $mSplit)); //we implode the value if a "=" in the value
      if ($k != "" && $v != "") { //the last data is empty because its end with a ; thats why we check if both not empty
        $res[$k] = $v; //then we packs into our result variable
      }
    }
  } else {
    $res["error"] = "cant open Stream ... wrong address?"; //if it was wrong ... example its not a stream
  }
  return $res;  //we send our array now
}
$radio_status = 0;
$fp = @fsockopen($server, $port, $errno, $errstr, 30);
if ($fp)
{
        fputs($fp, "GET /7.html HTTP/1.0\r\nUser-Agent: XML Getter (Mozilla Compatible)\r\n\r\n");
        while(!feof($fp))
        $page .= fgets($fp, 1000);
        fclose($fp);
        $page = preg_replace("#.*<body>#", "", $page);
        $page = preg_replace("#</body>.*#", ",", $page);
        $numbers = explode(",", $page);
        $shoutcast_currentlisteners = $numbers[0];
        $connected = $numbers[1];
        if($connected == 1) 
        {
            $radio_status = 1;
            $wordconnected = "yes";
        }
        else
        $wordconnected = "no";
        $shoutcast_peaklisteners = $numbers[2];
        $shoutcast_maxlisteners = $numbers[3]; 
        $shoutcast_reportedlisteners = $numbers[4];
        $shoutcast_bitrate = $numbers[5];
        $shoutcast_cursong = $numbers[6];
        $shoutcast_curbwidth = $shoutcast_bitrate * $shoutcast_currentlisteners;
        $shoutcast_peakbwidth = $shoutcast_bitrate * $shoutcast_peaklisteners;
} 
function getPageStream($web)
{
        $html = "";
          $ch = curl_init($web);
          curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.0.12) Gecko/20070508 Firefox/1.5.0.12");
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_HEADER, 0);
          //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
          curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
          curl_setopt($ch, CURLOPT_TIMEOUT, 1);
          $html = curl_exec($ch);
          if(curl_errno($ch))
          {
              $html = "";
          }
          curl_close ($ch);
        return $html;
}
function getBetweenStream($content,$start,$end)
{
        $a1 = strpos($content,$start);
        $content = substr($content,$a1 + strlen($start));
        while($a2 = strrpos($content,$end))
        {
            $content = substr($content,0,$a2);
        }
        return $content;
}  
if ($radio_status == 1)
{
  $stream_last = $ip.":".$port."/played.html";
  $data_last = getPageStream($stream_last);
  $last_song = getBetweenStream($data_last,'Current Song</b></td></tr><tr><td>','</tr></table><br><br><table cellpadding=0');
  $write = "stream_typ:ShoutCast|strema_title:".$stream_title."|stream_status:Stream is up at ".$shoutcast_bitrate." kbps with ".$shoutcast_reportedlisteners." of ".$shoutcast_maxlisteners." listeners (".$shoutcast_reportedlisteners." unique)|listener_peak:".$shoutcast_reportedlisteners."|current_song:".$shoutcast_cursong."|last_song<".$last_song;
}
elseif($fp = @fsockopen($iceserver, $iceport, $errno, $errstr, '1')) 
{
  fclose($fp);
  $string = file_get_contents("http://" . $server . ":" . $iceport . "/status-json.xsl");
  $json_a = json_decode($string, true);
  $icecast_reportedlisteners = $json_a['icestats']['source'][0]['listeners'];
  $icecast_bitrate = $json_a['icestats']['source'][0]['bitrate']; 
  $icecast_maxlisteners = $json_a['icestats']['source'][0]['listener_peak']; 
  $icecast_cursong = $json_a['icestats']['source'][0]['yp_currently_playing'];
  $last_song = "No Data Found";
  $write = "stream_typ:Icecast|strema_title:".$stream_title."|stream_status:Stream is up at ".$icecast_bitrate." kbps with ".$icecast_reportedlisteners." of ".$icecast_maxlisteners." listeners (".$icecast_reportedlisteners." unique)|listener_peak:".$icecast_reportedlisteners."|current_song:".$icecast_cursong."|last_song<".$last_song;
}     
else 
{
  $data = getMp3StreamTitle($_GET["ip"]);
  if (!isset($data["error"])) {
    $StreamServer = "Unknown";
    if (isset($data["ice-audio-info"])) $StreamServer = "IceCast";
    if (isset($data["icy-notice2"])) $StreamServer = "ShoutCast";
    if (is_array($data["StreamTitle"])) $data["StreamTitle"] = $data["StreamTitle"][count($data["StreamTitle"])-1];
    if (is_array($data["icy-br"])) $data["icy-br"] = $data["icy-br"][count($data["icy-br"])-1];
    $write = "stream_typ:".$StreamServer."|strema_title:".substr($data["StreamTitle"],1,-1)."|stream_status:Stream is up at ".$data["icy-br"]." kbps with --- of -- listeners (-- unique)|listener_peak:---|current_song:".substr($data["StreamTitle"],1,-1)."|last_song<---";
  } else {
    $write = "|strema_title:Stream is Offline|stream_status:Stream is up at --- kbps with --- of -- listeners (-- unique)|listener_peak:0|current_song:---|last_song<---";
  }
}
echo "$write"; 
?>

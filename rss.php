<?php

/*
This script is a workaround that allows HumHub to have RSS feeds of a profile's public posts.

This script depends on Parsedown - https://github.com/erusev/parsedown

If you can and would like you can buy me a coffee. If you can't it's not a problem. Thank you.
https://ko-fi.com/selectallfromdual
https://www.paypal.com/paypalme/francescoceliento

Git Repository and tutorial - https://github.com/FrancescoCeliento/humhub-feedrss
*/
error_reporting(0);

defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'dev');

require(__DIR__ . '/protected/vendor/autoload.php');
require(__DIR__ . '/protected/vendor/yiisoft/yii2/Yii.php');


$config = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/protected/humhub/config/common.php'),
    require(__DIR__ . '/protected/humhub/config/web.php'),
    (is_readable(__DIR__ . '/protected/config/dynamic.php')) ? require(__DIR__ . '/protected/config/dynamic.php') : [],
    require(__DIR__ . '/protected/config/common.php'),
    require(__DIR__ . '/protected/config/web.php')
);

require_once('Parsedown.php');

$fulllink = htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
$excludefromlink = explode('/',$fulllink)[sizeof(explode('/',$fulllink))-1];
$link = str_replace($excludefromlink,'',$fulllink);

if (isset($_GET['cid'])) {
	header("location: ".$link."index.php?r=content%2Fperma&id=".$_GET['cid']);
}

$setting = array();
$setting['title'] = $config['name'];
$setting['link'] = $link;
$setting['description'] = 'RSS adapter for HumHub.com - Source script https://github.com/FrancescoCeliento/humhub-feedrss';
$host = $setting['link'];
$dbhost = $config['params']['installer']['db']['installer_hostname'];
$dbname = $config['params']['installer']['db']['installer_database'];
$dbuser = $config['components']['db']['username'];
$dbpass = $config['components']['db']['password'];

if (isset($_GET["cguid"])){
	$cguid = $_GET["cguid"];
}

if (isset($_GET["r"])) {
	$r = $_GET["r"];
}

function execute_query_torss($query,$setting, $host, $database, $user, $password) {

	$messaggioerrore = "Connection failed";

	$conn = new PDO("mysql:host=$host; dbname=$database;charset=utf8", $user, $password);
	
        $rss = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:sy="http://purl.org/rss/1.0/modules/syndication/" xmlns:slash="http://purl.org/rss/1.0/modules/slash/" />');

        $channel = $rss->addChild('channel');
        $channel->addChild('title', $setting['title']);
		
		$atomlink=$channel->addChild('atom:link','',"http://www.w3.org/2005/Atom"); 
		$atomlink->addAttribute('href',$setting['link']); 
		$atomlink->addAttribute('rel',"self"); 
		$atomlink->addAttribute('type',"application/rss+xml");
		
        $channel->addChild('link', $setting['link']);
        $channel->addChild('description', $setting['description']);
		
        $stmt = $conn->query($query);

		$i=0;
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
				if ($i==0) {
					$channel->addChild('lastBuildDate', $row['pubDate']);
				}
                $item = $channel->addChild('item');
                foreach ($row as $key => $value) {
                       
                        if ($key == "description" || $key == "title") {
								$Parsedown = new Parsedown();
								$value = $Parsedown->text($value);
								$value = strip_tags($value);
								$value = stripslashes($value);
								
								if ($key == "title") {
								
									if (strlen($value) > (260 - strlen($setting['link'].'rss.php?cid=xxxxxxxxxxx') - 4)) {
										$value = substr($value,0,260 - strlen($setting['link'].'rss.php?cid=xxxxxxxxxxx') - 4).' ...';
									 }
								
								}
                        }
                        
                        if ($key == "link" || $key == "guid") {
                                $value = $setting['link']."rss.php?cid=".$value;
                        }
                        
                        $item->addChild($key, htmlspecialchars($value));
                }
				
				$i++;
        }

       header('Content-type: text/xml');
       $output=$rss->asXML(); 
       
       
       return $output;

}

if (isset($cguid) && isset($r) && ($r == 'user/profile' || $r == 'user/profile/home')) {
$query = "SELECT DATE_FORMAT(p.created_at,'%a, %d %b %Y %H:%i:%s +0000') AS pubDate, 
				 p.message AS title, 
				 c.id AS link, 
				 c.id AS guid, 
				 p.message AS description
			FROM content c, 
				 post p, 
				 user u, 
				 profile pr 
		   WHERE u.guid = '$cguid' 
		     AND u.id = p.created_by 
			 AND p.id = c.object_id 
			 AND c.visibility = 1 
			 AND c.object_model = 'humhub\\\modules\\\post\\\models\\\Post' 
			 AND pr.user_id = u.id 
		ORDER BY p.created_at DESC ";
		
} else if (isset($cguid) && isset($r) && $r == 'space/space') {
$query = "SELECT DATE_FORMAT(p.created_at,'%a, %d %b %Y %H:%i:%s +0000') AS pubDate,
	             p.message AS title, 
	             c.id AS link, 
				 c.id AS guid, 
	             p.message AS description
		    FROM space s,
			     contentcontainer cc,
				 content c,
				 post p,
				 user u,
				 profile pr
		   WHERE s.guid = '$cguid' 
				 AND cc.pk = s.id
				 AND c.contentcontainer_id = cc.id
				 AND c.visibility = 1
				 AND c.object_model = 'humhub\\\modules\\\post\\\models\\\Post' 
				 AND p.id = c.object_id
				 AND u.id = p.created_by
				 AND pr.user_id = u.id
	    ORDER BY p.created_at DESC";
	 
} else {

$query = "SELECT ' ' AS pubDate,
	               ' ' AS title,
	               ' ' AS link,
	               ' ' AS guid,
	               ' ' AS description
		        FROM DUAL
		       WHERE 1 = 2";

}
		  
echo execute_query_torss($query,$setting,$dbhost,$dbname,$dbuser,$dbpass);

?>

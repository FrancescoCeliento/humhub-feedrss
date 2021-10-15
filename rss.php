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

$setting = array();
$setting['title'] = $config['name'];
$setting['link'] = htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
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

	$conn = new PDO("mysql:host=$host; dbname=$database", $user, $password);
	
        $rss = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:sy="http://purl.org/rss/1.0/modules/syndication/" xmlns:slash="http://purl.org/rss/1.0/modules/slash/" />');

        $channel = $rss->addChild('channel');
        $atomlink=$channel->addChild('link','',$setting['link']); 
		$atomlink->addAttribute('href',$setting['link']); 
		$atomlink->addAttribute('rel',"self"); 
		$atomlink->addAttribute('type',"application/rss+xml");
        
        $channel->addChild('title', $setting['title']);
        $channel->addChild('link', $setting['link']);
        $channel->addChild('description', $setting['description']);
        $stmt = $conn->query($query);

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                $item = $channel->addChild('item');
                foreach ($row as $key => $value) {
                       
                        if ($key == "description" || $key == "title") {
								$Parsedown = new Parsedown();
                                $value = utf8_encode($value);
								$value = $Parsedown->text($value);
								$value = strip_tags($value);
								
								if ($key == "title") {
								
									if (strlen($value) > (260 - strlen($setting['link'].'/index.php?r=content%2Fperma&id=xxxxxxxxxxx') - 4)) {
										$value = substr($value,0,260 - strlen($setting['link'].'/index.php?r=content%2Fperma&id=xxxxxxxxxxx') - 4).' ...';
									 }
								
								}
                        }
                        
                        if ($key == "link") {
                                $value = $setting['link']."/index.php?r=content%2Fperma&id=".$value;
                        }
                        
                        $item->addChild($key, htmlspecialchars($value));
                }
        }

       header('Content-type: text/xml');
       $output=$rss->asXML(); 
       
       
       return $output;

}

if (isset($cguid) && isset($r) && $r == 'user/profile') {
$query = "SELECT p.created_at AS pubDate, 
				 p.message AS title, 
				 c.id AS link, 
				 p.message AS description, 
				 concat(pr.firstname,' ', pr.lastname) AS author 
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
$query = "SELECT p.created_at AS pubDate,
	             p.message AS title, 
	             c.id AS link, 
	             p.message AS description,
	             concat(pr.firstname,' ', pr.lastname) AS author
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
	               ' ' AS description,
	               ' ' AS author
		        FROM DUAL
		       WHERE 1 = 2";

}
		  
echo execute_query_torss($query,$setting,$dbhost,$dbname,$dbuser,$dbpass);

?>

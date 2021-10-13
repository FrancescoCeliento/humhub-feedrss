<?php

/*
his script is a workaround that allows HumHub to have RSS feeds of a profile's public posts.

This script depends on Parsedown - https://github.com/erusev/parsedown

If you can and would like you can buy me a coffee. If you can't it's not a problem. Thank you.
https://ko-fi.com/selectallfromdual
https://www.paypal.com/paypalme/francescoceliento

Git Repository and tutorial - https://github.com/FrancescoCeliento/humhub-feedrss
*/

require_once('Parsedown.php');

$setting = array();
$setting['title'] = 'INSERT HUMHUB NAME'; 
$setting['link'] = 'INSERT HUMHUB ROOT LINK';
$setting['description'] = 'RSS adapter for HumHub.com - Powered by https://github.com/FrancescoCeliento/humhub-feedrss';
$host = $setting['link'];
$dbhost = 'INSERT DB HOST';
$dbname = 'INSERT DB NAME';
$dbuser = 'INSERT DB USER';
$dbpass = 'INSERT DB PASSWORD';

$cguid = $_GET["cguid"];

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
								
									if (strlen($value) > (160 - strlen($setting['link'].'/index.php?r=content%2Fperma&id=xxxxxxxxxxx') - 4)) {
										$value = substr($value,0,160 - strlen($setting['link'].'/index.php?r=content%2Fperma&id=xxxxxxxxxxx') - 4).' ...';
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


$query = "SELECT p.created_at AS pubDate, ".
	 "	 p.message AS title, ".
	 "   c.id AS link, ".
	 "	 p.message AS description, ".
	 " 	 concat(pr.firstname,' ', pr.lastname) AS author ".
	 "  FROM content c, ".
	 "  	 post p, ".
	 "  	 user u, ".
	 " 	 profile pr ".
	 " WHERE u.guid = '$cguid' ".
	 "   AND u.id = p.created_by ".
	 "   AND p.id = c.object_id ".
	 "   AND c.visibility = 1 ".
	 "   AND c.object_model = 'humhub\\\modules\\\post\\\models\\\Post' ".
	 "   AND pr.user_id = u.id ".
	 " ORDER BY p.created_at DESC ";
		  
if (isset($_GET["cguid"])) {
	echo execute_query_torss($query,$setting,$dbhost,$dbname,$dbuser,$dbpass);
}
		  
?>

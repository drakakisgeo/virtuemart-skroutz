<?php

include_once('xmlgenerator.php');
$xml = new XmlGenerator();
if($xml->cachefile_exists){
    if(!$xml->is_uptodate()){
        $xml->createFile = 1;
        $data = $xml->create();
        Header('Content-type: text/xml');
        print($data->asXML());
    }else{
        Header('Content-type: text/xml');
        echo file_get_contents($xml->cached_file);
    }
}else{
        $xml->createFile = 1;
        $data = $xml->create();
        Header('Content-type: text/xml');
        print($data->asXML());
}
?>
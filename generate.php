<?php
include_once('xmlgenerator.php');
$xml = new XmlGenerator();
$data = $xml->create();
Header('Content-type: text/xml');
print($data->asXML());

?>
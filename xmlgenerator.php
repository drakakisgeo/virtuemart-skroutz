<?php

include_once("setup.php");

/**
 * An easy to read/tweak class for XML generation (Skroutz e.t.c)
 * For Virtuemart 2.0.18a / Joomla 2.5.8
 * @author  Drakakis George <lolly@lollypop.gr>
 * @copyright 2013 - lollypop.gr
*/
class XmlGenerator {

    // URLs
    private $_imagesPath;
    private $_thumbFolder;

    // For Queries
    private $_thumbSize;
    private $_dbPrefix;
    private $_lang;
    private $_categories=null;

    // Cache Vars
    public $cacheTime;
    public $cachefile_exists = false;
    public $cached_file;
    public $createFile=0;

    public function __construct(){
         $this->_imagesPath = BASEURL.'/images/stories/virtuemart/product/';
         $this->_thumbFolder  = BASEURL.'/images/stories/virtuemart/product/resized/';
         $this->_thumbSize  = THUMBSIZE;
         $this->_dbPrefix  = DBPREFIX;
         $this->_lang  = LANG;
         $this->cacheTime = CACHETIME;
         $this->cachefile_exists = $this->check_file();
         $this->cached_file = XMLFILE.'.xml';
     }

    /**
     * Create XML structure
     * @return object
     */
    public function create(){
        // Grab Data
        $this->get_categories();
        $images = $this->get_images();
        $manufacturers = $this->get_manufacturers();
        $products = $this->get_products(LIMITNUM);
        // Start XML structure
        $xml = new SimpleXMLElement('<skroutzstore/>');
        $xml->addAttribute('name',STORENAME);
        $xml->addAttribute('url',STOREURL);
        $xml->addAttribute('total_products',$products['total_products']);
        $xml->addAttribute('encoding','utf8');
        $products_node = $xml->addChild('products');

        // Loop through data to create nodes
        foreach($products['data'] as $item){
            $product_node = $products_node->addChild('product');
            $product_node->addAttribute('id',$item['id']);
            $product_node->addChild('name',$item['name']);
            $product_node->addChild('link',BASEURL."/".$this->e($this->get_url($item['id'])));
            $product_node->addChild('price_with_vat',number_format($item['price'], 2, ',', ''));
            $category_node = $product_node->addChild('category',$this->get_catpath($item['category_id'],$this->_categories)); // change that
            $category_node->addAttribute('id',$item['category_id']); // change that
            $product_node->addChild('image',$this->_imagesPath.$images[$item['img_id']]);
            $product_node->addChild('thumbnail',$this->get_thumb($images[$item['img_id']]));
            $product_node->addChild('manufacturer',$manufacturers[$item['manufacturer_id']]);
            $product_node->addChild('availability',$item['availability']);
            $product_node->addChild('stock',$item['stock']);
        }

            if($this->createFile==1){ // store it to cache
                $xml->asXML(XMLFILE.'.xml');
            }
    return $xml;
    }

    /**
     * Get all VM products
     * @return array
     */
    public function get_products($limit=null){
       if($limit){
        $limitText = "LIMIT ".$limit;
       }
       $products = array(
        'total_products'=>0,
        'date_created'=>date('D M j G:i:s T Y'),
        'data'=> array()
        );

        try{
            $DBH = new PDO("mysql:host=localhost;dbname=".DBNAME.";charset=".DBENCODING."", "".DBUSER."", "".DBPASS."");
            $q = "SELECT
            p.*,
            dp.product_name as productname,
            dp.slug,cp.virtuemart_category_id as catid,
            pp.product_price as price,
            imp.virtuemart_media_id as imgid,
            mp.virtuemart_manufacturer_id as manufacturer_id
            FROM
                ".$this->_dbPrefix."products AS p,
                ".$this->_dbPrefix."products_el_gr AS dp,
                ".$this->_dbPrefix."product_categories as cp,
                ".$this->_dbPrefix."product_prices as pp,
                ".$this->_dbPrefix."product_medias as imp,
                ".$this->_dbPrefix."product_manufacturers as mp
            WHERE p.virtuemart_product_id = dp.virtuemart_product_id &&
                    p.published = 1 &&
                    cp.virtuemart_product_id = p.virtuemart_product_id &&
                    pp.virtuemart_product_id = p.virtuemart_product_id &&
                    imp.virtuemart_product_id = p.virtuemart_product_id &&
                    mp.virtuemart_product_id = p.virtuemart_product_id
                    ORDER BY p.virtuemart_product_id DESC
                     ".$limitText;
            $list =  $DBH->query($q) or die("failed!");
            $allitems = 0;
            while($c = $list->fetch(PDO::FETCH_ASSOC)){
                $allitems++;
                $data[] = array(
                    'id' => intval($c['virtuemart_product_id']),
                    'name' => $this->e($c['productname']),
                    'slug' => $this->e($c['slug']),
                    'category_id'=> $c['catid'],
                    'price'=> $c['price'],
                    'stock'=> $c['product_in_stock']>0 ? 'Y' : 'N',
                    'availability' => $c['product_availability'],
                    'manufacturer_id' => $c['manufacturer_id'],
                    'img_id'=> $c['imgid']
                    );
            }
            $products['total_products'] = $allitems;
            $DBH = null;
        }catch(PDOEXCEPTION $e){
            echo $e->getMessage();
            die();
        }

        // Parse all data to products array
        $products['data'] = $data;

        return $products;
    }

    /**
     * Helper to fix chars for XML structure
     * @param  string $string
     * @return string
     */
    public function e($string) {
    return str_replace(array("&", "<", ">", "\"", "'"),
        array("&amp;", "&lt;", "&gt;", "&quot;", "&apos;"), $string);
    }

    /**
     * Get list of ids and Manufacture's names
     * @return array (key=id,value=name)
     */
    public function get_manufacturers(){
       $listItems = array();
        try{
            $DBH = new PDO("mysql:host=localhost;dbname=".DBNAME.";charset=".DBENCODING."", "".DBUSER."", "".DBPASS."");
            $q = "SELECT mf_name as name,virtuemart_manufacturer_id as id FROM ".$this->_dbPrefix."manufacturers_".$this->_lang;
            $list =  $DBH->query($q) or die("failed!");
            while($c = $list->fetch(PDO::FETCH_ASSOC)){
                $listItems[$c['id']] = $this->e($c['name']);
            }
            $DBH = null;
        }catch(PDOEXCEPTION $e){
            echo $e->getMessage();
            die();
        }
        return $listItems;
    }
    /**
     * Get all images
     * @return array
     */
    public function get_images(){
       $listItems = array();
        try{
            $DBH = new PDO("mysql:host=localhost;dbname=".DBNAME.";charset=".DBENCODING."", "".DBUSER."", "".DBPASS."");
            $q = "SELECT virtuemart_media_id as id,file_title as name FROM ".$this->_dbPrefix."medias WHERE file_mimetype='image/jpeg'";
            $list =  $DBH->query($q) or die("failed!");
            while($c = $list->fetch(PDO::FETCH_ASSOC)){
                $listItems[$c['id']] = $this->e($c['name']);
            }
            $DBH = null;
        }catch(PDOEXCEPTION $e){
            echo $e->getMessage();
            die();
        }
        return $listItems;
    }
    /**
     * Create thumbnail
     * @param  string $filename
     * @return string
     */
    public function get_thumb($filename){
        $makeString = "_".$this->_thumbSize."x".$this->_thumbSize;
        $ext   = pathinfo($filename, PATHINFO_EXTENSION);
        $thumb = basename($filename, ".$ext") . $makeString .'.'. $ext;
        return $this->_thumbFolder.$thumb;
    }

    /**
     * Get the URL
     * @param  integer $product_id
     * @return string
     */
    public function get_url($product_id){
        return "index.php?option=com_virtuemart&view=productdetails&virtuemart_product_id=".$product_id;
    }


    /**
     * Category path generator -- Currently supports only one level deep.
     * @param  integer $catid
     * @return string
     */
    public function get_catpath($catid,$categories){
        $parentid = $categories[$catid][1];
        if($parentid!=0){
            return $categories[$parentid][0]. " / ". $categories[$catid][0];
        }else{
            return $categories[$catid][0];
        }
    }

    /**
     * Get Categories and Parent/child ids in a multi dimentional Array
     * @return array
     */
    public function get_categories(){
        $listItems = array();
        try{
            $DBH = new PDO("mysql:host=localhost;dbname=".DBNAME.";charset=".DBENCODING."", "".DBUSER."", "".DBPASS."");
            $q = "SELECT cats.category_name as name, cats.virtuemart_category_id as id,
                        scat.category_parent_id as pid
                    FROM ".$this->_dbPrefix."categories_".$this->_lang. " as cats,
                        fybv3_virtuemart_category_categories as scat
                    WHERE scat.category_child_id = cats.virtuemart_category_id";

            $list=$DBH->query($q) or die("failed!");
            while($c = $list->fetch(PDO::FETCH_ASSOC)){
                $listItems[$c['id']] = array($this->e($c['name']),$c['pid']);
            }
            $DBH = null;
        }catch(PDOEXCEPTION $e){
            echo $e->getMessage();
            die();
        }
        $this->_categories = $listItems;
        return $listItems;
    }

    /**
     * Check if cache file is there
     * @return boolean
     */
    private function check_file(){

        if(file_exists(XMLFILE.'.xml')){
            return true;
         }else{
            return false;
         }
    }

    /**
     * Check if Cache file needs update
     * @return boolean
     */
    public function is_uptodate(){
        if((time() - CACHETIME < filemtime(XMLFILE.'.xml'))) {
            return true;
        }else{
            return false;
        }
    }
}
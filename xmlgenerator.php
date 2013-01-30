<?php

/**
 * An easy to read/tweak class for XML generation (Skroutz e.t.c)
 * For Virtuemart 2.0.18a / Joomla 2.5.8
 * @author  Drakakis George <lolly@lollypop.gr>
 * @copyright  Copyright (C) 20013 - 2012 lollypop.gr, Inc. All rights reserved.
*/
class XmlGenerator {

    //----- DB Details
    const DBNAME = "dbname";
    const DBUSER = "dbuser";
    const DBPASS = "password";
    const DBENCODING = "utf8";

    // Store Constants
    const STORENAME = "Your Store Name";
    const STOREURL = "http://www.yourdomain.gr";

    // URLs
    private $_basepath = "http://www.yourdomain.gr/";
    private $_imagesPath = "http://www.yourdomain.gr/images/stories/virtuemart/product/";
    private $_thumbFolder = "http://www.yourdomain.gr/images/stories/virtuemart/product/resized/";

    // For Queries
    private $_thumbSize = 120;
    private $_dbPrefix = "fybv3_virtuemart_"; // WITH virtuemart_ at the end of the string
    private $_lang = "el_gr"; // The language @ DB


    /**
     * Create XML structure
     * @return object
     */
    public function create(){
        // Grab Data
        $categories = $this->get_categories();
        $images = $this->get_images();
        $manufacturers = $this->get_manufacturers();
        $products = $this->get_products();
        // Start XML structure
        $xml = new SimpleXMLElement('<skroutzstore/>');
        $xml->addAttribute('name',self::STORENAME);
        $xml->addAttribute('url',self::STOREURL);
        $xml->addAttribute('total_products',$products['total_products']);
        $xml->addAttribute('encoding','utf8');
        $products_node = $xml->addChild('products');

        // Loop through data to create nodes
        foreach($products['data'] as $item){
            $product_node = $products_node->addChild('product');
            $product_node->addAttribute('id',$item['id']);
            $product_node->addChild('name',$item['name']);
            $product_node->addChild('link',$this->e($this->_basepath.$this->get_url($item['id'])));
            $product_node->addChild('price_with_vat',number_format($item['price'], 2, ',', ''));
            $category_node = $product_node->addChild('category',$categories[$item['category_id']]); // change that
            $category_node->addAttribute('id',$item['category_id']); // change that
            $product_node->addChild('image',$this->_imagesPath.$images[$item['img_id']]);
            $product_node->addChild('thumbnail',$this->get_thumb($images[$item['img_id']]));
            $product_node->addChild('manufacturer',$manufacturers[$item['manufacturer_id']]);
            $product_node->addChild('availability',$item['availability']);
            $product_node->addChild('stock',$item['stock']);
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
            $DBH = new PDO("mysql:host=localhost;dbname=".self::DBNAME.";charset=".self::DBENCODING."", "".self::DBUSER."", "".self::DBPASS."");
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
        mysql_close($dbc);
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
     * Get list of ids and categories
     * @return array
     */
    public function get_categories(){
        $listItems = array();
        try{
            $DBH = new PDO("mysql:host=localhost;dbname=".self::DBNAME.";charset=".self::DBENCODING."", "".self::DBUSER."", "".self::DBPASS."");
            $q = "SELECT category_name as name,virtuemart_category_id as id FROM ".$this->_dbPrefix."categories_".$this->_lang;

            $list=$DBH->query($q) or die("failed!");
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
     * Get list of ids and Manufacture's names
     * @return array (key=id,value=name)
     */
    public function get_manufacturers(){
       $listItems = array();
        try{
            $DBH = new PDO("mysql:host=localhost;dbname=".self::DBNAME.";charset=".self::DBENCODING."", "".self::DBUSER."", "".self::DBPASS."");
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
            $DBH = new PDO("mysql:host=localhost;dbname=".self::DBNAME.";charset=".self::DBENCODING."", "".self::DBUSER."", "".self::DBPASS."");
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
     * SEO path generator
     * @param  integer $catid
     * @return string
     */
    public function get_catpath($catid){
        //
    }

}
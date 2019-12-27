<?php
//NOT CONFIGURED

include_once( SEEDROOT."seedcore/SEEDBasketDB.php" );
include_once( SEEDROOT."seedcore/SEEDBasketProductHandler.php" );
include_once( SEEDROOT."seedcore/SEEDBasketUpdater.php" );
include_once( SEEDROOT."Keyframe/KeyframeForm.php" );
include_once( SEEDAPP."basket/basketProductHandlers.php" );
include_once( SEEDAPP."basket/basketProductHandlers_seeds.php" );

class SEEDBasket_Basket{
	/*****
	 * Class for managing basket in session
	 */
	
	//Basket Control
	public const BASKET_CLOSED = false;
	public const BASKET_OPEN = true;
	
	public const NO_BASKET = null;
	public const NO_ID = 0;
	
	
	private static $basket = self::NO_BASKET;
	
	private $data;
	private $isOpen = self::BASKET_CLOSED;
	private $buyer_id = self::NO_ID;
	
	private function __construct(){
		if(!$this->is_session_started()){session_start();}
		if(!isset($_SESSION['basket'])){
			$_SESSION['basket'] = array('id'=>self::NO_ID,'data'=>array());
		}
		$this->data =& $_SESSION['basket']['data'];
		$this->buyer_id =& $_SESSION['basket']['id'];
		$this->isOpen = self::BASKET_OPEN;
	}
	
	public static function getBasket(){
		if(!self::$basket){
			self::$basket = new SEEDBasket_Basket();
		}
		return self::$basket;
	}
	
	public function closeBasket(){
		unset($_SESSION['basket']);
		self::$basket = self::NO_BASKET;
		$this->isOpen = self::BASKET_CLOSED;
	}
	
	//Product Control ---------------------------------------------------------------------------------
	
	public function addProduct(SEEDBasket_Purchase $pur){
		if(!$this->isOpen){
			return self::BASKET_CLOSED;
		}
		$this->data[] = $pur->encode();
		end($this->data);
		$key = key($this->data);
		reset($this->data);
		return $key;
	}
	
	public function removeProduct(SEEDBasket_Purchase $pur){
		if(!$this->isOpen){
			return self::BASKET_CLOSED;
		}
		unset($this->data[array_search($pur->encode(), $this->data)]);
	}
	
	public function removeProductByIndex(int $index){
		if(!$this->isOpen){
			return self::BASKET_CLOSED;
		}
		unset($this->data[$index]);
	}
	
	public function removeProductByString(String $encodedString){
		if(!$this->isOpen){
			return self::BASKET_CLOSED;
		}
		unset($this->data[array_search($encodedString, $this->data)]);
	}
	
	public function updateProduct(int $index, SEEDBasket_Purchase $pur){
		if(!$this->isOpen){
			return self::BASKET_CLOSED;
		}
		$this->data[$index] = $pur->encode();
	}
	
	public function updateProductByString(int $index, String $encodedString){
		if(!$this->isOpen){
			return self::BASKET_CLOSED;
		}
		$this->data[$index] = $encodedString;
	}
	
	public function confirmPurchases(SEEDBasketCore $oSB){
		foreach($this->getContents($oSB) as $key=>$pur){
			$pur->confirmPurchase($oSB);
		}
	}
	
	//End Product Control -----------------------------------------------------------------------------
	
	public function getContents(SEEDBasketCore $oSB){
		if(!$this->isOpen){
			return self::BASKET_CLOSED;
		}
		$ra = array();
		foreach($this->data as $key=>$encodedString){
			$ra[$key] = SEEDBasket_Purchase::decode($oSB,$encodedString);
		}
		return $ra;
	}
	
	public function getBuyerID(){
		if(!$this->isOpen){
			return self::BASKET_CLOSED;
		}
		return $this->buyer_id;
	}
	
	public function setBuyerID(int $id){
		if(!$this->isOpen){
			return self::BASKET_CLOSED;
		}
		$this->buyer_id = $id;
	}
	
	private function is_session_started(){
		if ( php_sapi_name() !== 'cli' ) {
			if ( version_compare(phpversion(), '5.4.0', '>=') ) {
				return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
			} else {
				return session_id() === '' ? FALSE : TRUE;
			}
		}
		return FALSE;
	}
	
}

class SEEDBasket_Product{
	/*****
	 * Class containing product data
	 */
	 
	public const ITEM_MANY = "ITEM-N";
	public const ITEM_ONE = "ITEM-1";
	public const MONEY = "MONEY";
	
	private $seller_id;
	private $type;
	private $titles = array("en" => "","fr" => "");
	private $img;
	private $quantity_min;
	private $quantity_max;
	private $quantity_type;
	private $item_prices = array("CAD" => 0, "USD" => 0);
	private $item_discounts = array("CAD" => "", "USD" => "");
	private $item_shippings = array("CAD" => "", "USD" => "");
	private $extra;
	
	private function __construct(array $data){
		$this->seller_id = $data['uid_seller'];
		$this->type = $data['product_type'];
		$this->titles['en'] = $data['title_en'];
		$this->titles['fr'] = $data['title_fr'];
		$this->img = $data['img'];
		$this->quantity_max = $data['bask_quant_max'];
		$this->quantity_min = $data['bask_quant_min'];
		$this->quantity_type = $data['quant_type'];
		$this->item_prices['CAD'] = $data['item_price'];
		$this->item_prices['USD'] = $data['item_price_US'];
		$this->item_discounts['CAD'] = $data['item_discount'];
		$this->item_discounts['USD'] = $data['item_discount_US'];
		$this->item_shippings['CAD'] = $data['item_shipping'];
		$this->item_shippings['USD'] = $data['item_shipping_US'];
		$this->extra = $data['sExtra'];
	}
	
	public static function getProduct(SEEDBasketCore $oSB,int $key){
		$kfrc = $oSB->oDB->GetProductList( "_key=".$key );
        if($kfrc){
			$kfrc->CursorFetch();
			$ra = $kfrc->ValuesRA();
			return new SEEDBasket_Product($ra);
		}
		return null;
	}
	
	public static function getProductList(SEEDBasketCore $oSB, String $cond = "1=1"){
		$products = array();
		$kfrc = $oSB->oDB->GetProductList( "1=1" );
		if($kfrc){
			for($i=0;$i<$kfrc->CursorNumRows();$i++){
				$kfrc->CursorFetch();
				$ra = $kfrc->ValuesRA();
				$products[] = new SEEDBasket_Product($ra);
			}
		}
		return $products;
	}
	
	public function getSellerID(){
		return $this->seller_id;
	}
	
	public function getProductType(){
		return $this->type;
	}
	
	public function getTitle(String $lang){
		return @$this->titles[$lang]?:$this->titles['en'];
	}
	
	public function getImage(){
		return $this->img;
	}
	
	public function getMaxPurchase(){
		return $this->quantity_max;
	}
	
	public function getMinPurchase(){
		return $this->quantity_min;
	}
	
	public function getPurchaseType(){
		return $this->quantity_type;
	}
	
	public function getItemPrice($currency){
		return @$this->item_prices[$currency]?:$this->item_prices['CAD'];
	}
	
	public function getItemDiscount($currency){
		return @$this->item_discounts[$currency]?:$this->item_discounts['CAD'];
	}
	
	public function getItemShipping($currency){
		return @$this->item_shippings[$currency]?:$this->item_shippings['CAD'];
	}
	
	public function getExtra(){
		return $this->extra;
	}
	
}

class SEEDBasket_Purchase{
	/*****
	 * Class containing puchase details
	 */
	
	private const CURRENT_BUYER_ID = 0;
	private const CURRENT_DATE = "NOW()";

	private $purchase_id = 0;
	private $buyer_id;
	private $product_id;
	private $status = "NEW";
	private $date;
	private $quantity = 0;
	private $value = 0.00;
	private $extra;
	private $confirmed;
	
	private function __construct(SEEDBasketCore $oSB, array $data, bool $confirmed){
		$this->loadFromRA($oSB, $data);
		$this->confirmed = $confirmed;
	}
	
	private function loadFromRA(SEEDBasketCore $oSB, array $data){
		$this->buyer_id = @$data['fk_SEEDBasket_Buyers']?:self::CURRENT_BUYER_ID;
		$this->product_id = @$data['fk_SEEDBasket_Products']?:$data['product'];
		$this->date = @$data['_created']?:self::CURRENT_DATE;
		$this->purchase_id = @$data['_key']?:0;
		$this->status = @$data['eStatus']?:"NEW";
		$purchaseType = SEEDBasket_Product::getProduct($oSB,$this->product_id)->getPurchaseType();
		if($purchaseType == SEEDBasket_Product::MONEY){
			$this->value = @$data['f']?:round($data['amount'],2);
		}
		else{
			$this->quantity = @$data['n']?:$data['amount'];
		}
		$this->extra = @$ra['sExtra']?:"";
	}
	
	public static function getPurchase(SEEDBasketCore $oSB, int $key){
		$ra = $oSB->oDB->GetList("PUR", "_key=".$key);
		if($ra){
			return new SEEDBasket_Purchase($oSB, $ra[0],true);
		}
		return null;
	}
	
	public static function createPurchase(SEEDBasketCore $oSB, int $product_id, $amount, bool $confirmed = false){
		return new SEEDBasket_Purchase($oSB,array('product'=>$product_id,'amount'=>$amount),$confirmed);
	}
	
	public static function decode(SEEDBasketCore $oSB, String $encodedString):SEEDBasket_Purchase{
		list($product_id,$amount,$confirmed) = explode("!",$encodedString);
		return self::createPurchase($oSB, $product_id, $amount, $confirmed);
	}
	
	public function encode():String{
		//TODO Make less lazy
		return $this->product_id."!".($this->value?:$this->quantity)."!".($this->confirmed?1:0);
	}
	
	public function confirmPurchase(SEEDBasketCore $oSB){
		/***********
		 * Also know as save purchase
		 */
		$ra = array('_key'=>$this->purchase_id,
					'fk_SEEDBasket_Buyers'=>(@$this->buyer_id?:SEEDBasket_Basket::getBasket()->getBuyerID()),
					'fk_SEEDBasket_Products'=>$this->product_id,
					'n'=>$this->quantity,'f'=>$this->value,'eStatus'=>$this->status,
					'sExtra'=>$this->extra
					);
		$kfr = new KeyframeRecord($oSB->oDB->GetKfrel("PUR"));
		foreach($ra as $key=>$value){
			if($key == "_key"){
				$kfr->SetKey($value);
			}
			else{
				$kfr->SetValue($key,$value);
			}
		}
		$kfr->PutDBRow();
	}
	
	public function isConfirmed():bool{
		return $this->confirmed;
	}
	
}

class SEEDBasketCore{
	
	public $oApp;
	public $oDB;
	
	private $raHandlerDefs;
    private $raHandlers = array();
    private $raParms = array();
    private $kfrBasketCurr = null;
	
	function __construct(SEEDAppConsole $oApp, $raHandlerDefs, $raParms = array() )
    {
        $this->oApp = $oApp;
        $this->oDB = new SEEDBasketDB( $oApp->kfdb, 0,
            //get this from oApp
            $oApp->logdir, ['db'=>@$raParms['db']] );
        $this->raHandlerDefs = $raHandlerDefs;
        $this->raParms = $raParms;
    }
	
}

define( "SITE_LOG_ROOT", $oApp->logdir );
$oSB = new SEEDBasketCore( $oApp, SEEDBasketProducts_SoD::$raProductTypes, array('logdir'=>SITE_LOG_ROOT) );
$purchase = SEEDBasket_Purchase::getPurchase($oSB,1);
var_dump(SEEDBasket_Basket::getBasket()->addProduct($purchase));
SEEDBasket_Basket::getBasket()->removeProduct($purchase);
var_dump(SEEDBasket_Basket::getBasket()->getContents($oSB));

?>
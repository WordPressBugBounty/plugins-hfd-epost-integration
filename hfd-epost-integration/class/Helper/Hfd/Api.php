<?php
/**
 * Created by PhpStorm.
 * Date: 6/6/18
 * Time: 6:57 PM
 */
namespace Hfd\Woocommerce\Helper\Hfd;

use Hfd\Woocommerce\Container;

class Api
{
    /**
     * @var \Hfd\Woocommerce\Setting
     */
    protected $setting;

    public function __construct()
    {
        $this->setting = Container::get('Hfd\Woocommerce\Setting');
    }

    /**
     * @param \WC_Order $order
     * @return bool
     */
    public function syncOrder($order)
    {
        $orderNumber = $order->get_id();

        list($param3, $param7, $cargohazor) = $this->_paramBaseOnShippingMethod($order);
        $userName = __('Guest', 'hfd-integration');

        $street = $aparment = $floor = $entrance = $houseNumber = '';

        if ($order->get_shipping_address_1()) {
            $street .= $order->get_shipping_address_1();
        }

        if ($order->get_shipping_address_2()) {
            $street .= ' ' . $order->get_shipping_address_2();
        }

        if ($order->get_shipping_first_name() || $order->get_shipping_last_name()) {
            $userName = $order->get_shipping_first_name() .' '. $order->get_shipping_last_name();
        }
		
		$user_phone = $order->get_billing_phone() ? $order->get_billing_phone() : '';
		$shippingCity = $order->get_shipping_city() ? $order->get_shipping_city() : '';
		
		//create order line items array
		$hfd_sync_order_items = $this->setting->get( 'hfd_sync_order_items' );
		$hfd_take_varaible_sku = $this->setting->get( 'hfd_take_varaible_sku' );
		if( $hfd_sync_order_items == "yes" ){
			$orderItems = array();
			$items = $order->get_items();
			if( $items ){
				foreach( $items as $key => $item ){
					if( $hfd_take_varaible_sku == "yes" ){
						$pid = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
					}else{
						$pid = $item->get_product_id();
					}
					
					$product = wc_get_product( $pid );
					$orderItems[] = array(
						'Code' 			=> $product->get_sku(),
						'Quantity'		=> $item['quantity'],
						'DeliveryCollect' => 'מסירה'
					);
				}
			}
		}
		
		//create hfd array
		$pParam = array(
			'ClientNumber'			=> $this->getCustomerNumber(),
			'MesiraIsuf'			=> $this->_getParam2( $order ),
			'ShipmentTypeCode'		=> $param3,
			'CargoTypeHaloch'		=> $param7,
			'AddressRemarks'		=> $street,
			'ShipmentRemarks'		=> $order->get_customer_note(),
			'StageCode'				=> $this->_getParam8( $order ),
			'PudoCodeDestination'	=> $this->_getParam35( $order ),
			'OrdererName'			=> $this->getSenderName(),
			'HouseNum'				=> $houseNumber,
			'Apartment'				=> $aparment,
			'Floor'					=> $floor,
			'Entrance'				=> $entrance,
			'NameTo'				=> $userName,
			'StreetName'			=> $street,
			'CityName'				=> $shippingCity,
			'TelFirst'				=> $user_phone,
			'StreetCode'			=> '',
			'ReferenceNum1'			=> $orderNumber,	
			'Email'					=> $order->get_billing_email(),
			'ProductsPrice'			=> $this->_getParam31( $order )
		);
		
		//add cargoHazor if govina
		/* if( $cargohazor ){
			//$pParam['CargoTypeHazor'] = $cargohazor;
			$pParam['govina'] = array(
				'code' => $this->_getParam30( $order ),
				'sum' => (int)$order->get_total(),
				'date' => date( 'Ymd', strtotime( $order->get_date_created() ) ),
			);
		} */
		
		//send order items only if yes selected
		if( $hfd_sync_order_items == "yes" ){
			$pParam['OrderItems'] = $orderItems;
		}
		
		$pParam = apply_filters( 'hfd_before_sync', $pParam );
		
        $url = 'https://api.hfd.co.il/rest/v2/shipments/create';
		
        $result = array(
            'error'     => false,
            'message'   => ''
        );

        $authToken = $this->setting->get('betanet_epost_hfd_auth_token');
        try {
			$args = array(
				'timeout' => 15,
				'user-agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',
				'sslverify' => false,
				'body' => json_encode( $pParam )
			);
			// add bearer token into request
            if( $authToken ){
				$args['headers'] = array(
					'Authorization' => 'Bearer '.$authToken,
					'Content-Type'  => 'application/json'
				);
			}else{
				$args['headers'] = array(
					'Content-Type'  => 'application/json'
				);
			}
			
			$response = wp_remote_post( $url, $args );

            if( is_wp_error( $response ) ){
                throw new \Exception('Fail to connect API');
            }
						
			$response = wp_remote_retrieve_body( $response );
            $arrResponse = json_decode( $response, true );

            if( $this->isApiDebug() ){
                $_response = $arrResponse;
                if( is_array( $_response ) ){
                    $_response = wp_json_encode( $_response );
                }
                $_logInfo = PHP_EOL .'===== Begin =====';
                $_logInfo .= PHP_EOL . '> Request parameters: '. wp_json_encode( $pParam );
                $_logInfo .= PHP_EOL .'> Call API: '. $url;
                $_logInfo .= PHP_EOL .'> Response: '. $_response;
                $_logInfo .= PHP_EOL .'====== End ======';
                $filesystem = Container::get( 'Hfd\Woocommerce\Filesystem' );
                $filesystem->writeLog( $_logInfo, 'HFD' );
            }

            if( isset( $arrResponse['shipmentNumber'] ) && !empty( $arrResponse['shipmentNumber'] ) ){
				$result['number'] = $arrResponse['shipmentNumber'];
                $result['rand_number'] = $arrResponse['randNumber'];
            }else if( isset( $arrResponse['errorMessage'] ) ){
				$result['error'] = true;
                $result['message'] = $arrResponse['errorMessage'];
			}else{
                $result['error'] = true;
                $result['message'] = __( 'Something went wrong', 'hfd-integration' );
            }
            return $result;
        } catch (\Exception $e) {
            $result['error'] = true;
            $result['message'] = $e->getMessage();

            return $result;
        }
    }

    protected function _orderAddressParser($address)
    {
        if (is_array($address)) {
            $address = $address[0];
        }
        $apartment = 'דירה';
        $floor = 'קומה';
        $entrance = 'כניסה';
        $parser = preg_split('/(?<=\D)(?=\d)|\d+\K/', $address);
        if (!$parser) {
            $res['street'] = $address;
        }
        $parser = array_map('trim', $parser);
        $res['street'] = $parser[0];
        $res['number'] = isset($parser[1]) ? $parser[1] : '';
        if (in_array($apartment, $parser)) {
            $key = array_search($apartment, $parser);
            $res['apartment'] = isset($parser[$key+1]) ? $parser[$key+1] : '';
        }
        if (in_array($floor, $parser)) {
            $key = array_search($floor, $parser);
            $res['floor'] = isset($parser[$key+1]) ? $parser[$key+1] : '';
        }
        if (in_array($entrance, $parser)) {
            $key = array_search($entrance, $parser);
            $res['entrance'] = isset($parser[$key+1]) ? $parser[$key+1] : '';
        }
        return $res;
    }

    /**
     * @param \WC_Order $order
     * @return string
     */
    protected function _getShippingMethod($order)
    {
        $shippingMethod = '';
        /* @var \WC_Order_Item_Shipping $method */
        foreach ($order->get_shipping_methods() as $method) {
            $shippingMethod = $method->get_method_id();
            if (substr($shippingMethod, 0, strlen(\Hfd\Woocommerce\Shipping\Epost::METHOD_ID)) == \Hfd\Woocommerce\Shipping\Epost::METHOD_ID) {
                $shippingMethod = \Hfd\Woocommerce\Shipping\Epost::METHOD_ID;
                break;
            }
			
			if (substr($shippingMethod, 0, strlen(\Hfd\Woocommerce\Shipping\Govina::METHOD_ID)) == \Hfd\Woocommerce\Shipping\Govina::METHOD_ID) {
                $shippingMethod = \Hfd\Woocommerce\Shipping\Govina::METHOD_ID;
                break;
            }
			
			if (substr($shippingMethod, 0, strlen(\Hfd\Woocommerce\Shipping\Home_Delivery::METHOD_ID)) == \Hfd\Woocommerce\Shipping\Home_Delivery::METHOD_ID) {
                $shippingMethod = \Hfd\Woocommerce\Shipping\Home_Delivery::METHOD_ID;
                break;
            }
			
            if (substr($shippingMethod, 0, strlen('free_shipping')) == 'free_shipping') {
                $shippingMethod = 'free_shipping';
                break;
            }
        }
        return $shippingMethod;
    }

    /**
     * @param \WC_Order $order
     * @return array
     */
    protected function _paramBaseOnShippingMethod($order)
    {
        $shippingMethod = $this->_getShippingMethod($order);
        switch ($shippingMethod) {
            case \Hfd\Woocommerce\Shipping\Epost::METHOD_ID:
                $result = ['50', '11', '0'];
                break;
			case \Hfd\Woocommerce\Shipping\Govina::METHOD_ID:
                $result = ['37', '10', '100'];
                break;
            case 'free_shipping':
            default:
                $result = ['35', '10', '0'];
                break;
        }

        return $result;
    }

    /**
     * @param \WC_Order $order
     * @return string
     */
    protected function _getParam35($order)
    {
        $shippingMethod = $this->_getShippingMethod($order);
        if ($shippingMethod !== \Hfd\Woocommerce\Shipping\Epost::METHOD_ID) {
            return '';
        }

        try {
            /* @var \WC_Order_Item_Shipping $method */
            foreach ($order->get_shipping_methods() as $method) {
                if (substr($shippingMethod, 0, strlen(\Hfd\Woocommerce\Shipping\Epost::METHOD_ID)) == \Hfd\Woocommerce\Shipping\Epost::METHOD_ID) {
                    $spotInfo = $method->get_meta('epost_pickup_info');
                    $spotInfo = unserialize($spotInfo);

                    if ($spotInfo['n_code']) {
                        return $spotInfo['n_code'];
                    }
                }
            }
        } catch (\Exception $e) {
        }

        return '';
    }
	
	public function _getParam8($order){
		$shippingMethod = $this->_getShippingMethod($order);
		if( $shippingMethod == \Hfd\Woocommerce\Shipping\Home_Delivery::METHOD_ID ){
            return 10;
        }
		return '';
	}
	
	public function _getParam31($order){
		$shippingMethod = $this->_getShippingMethod($order);
        if( $shippingMethod == \Hfd\Woocommerce\Shipping\Govina::METHOD_ID ){
            return $order->get_total();
        }
		return 0;
	}
	
	public function _getParam2($order){
		$shippingMethod = $this->_getShippingMethod($order);
        if( $shippingMethod == \Hfd\Woocommerce\Shipping\Home_Delivery::METHOD_ID ){
            return 'איסוף';
        }
		return 'מסירה';
	}
	
	public function _getParam30($order){
		$shippingMethod = $this->_getShippingMethod($order);
        if( $shippingMethod == \Hfd\Woocommerce\Shipping\Govina::METHOD_ID ){
            $betanet_pmethod = get_post_meta( $order->get_id(), 'betanet_pmethod', true );
			if( $betanet_pmethod == "govina_cash" ){
				return 1;
			}else if( $betanet_pmethod == "govina_cheque" ){
				return 11;
			}
        }
		return '';
	}
	
	public function _getParam32($order){
		$shippingMethod = $this->_getShippingMethod($order);
        if( $shippingMethod == \Hfd\Woocommerce\Shipping\Govina::METHOD_ID ){
			$order_date = $order->get_date_paid();
			if( empty( $order_date ) ){
				$order_date = $order->get_date_created();
			}
            return date( 'd/m/Y', strtotime( $order_date ) );
        }
		return '';
	}
	
	public function _getParam33($order){
		$shippingMethod = $this->_getShippingMethod($order);
        if( $shippingMethod == \Hfd\Woocommerce\Shipping\Govina::METHOD_ID ){
            return $order->get_customer_note();
        }
		return '';
	}
    /**
     * @return mixed
     */
    public function isActive()
    {
        return $this->setting->get('betanet_epost_hfd_active');
    }

    /**
     * @return string
     */
    public function getServiceUrl()
    {
        $serviceUrl = $this->setting->get('betanet_epost_hfd_service_url');
        $serviceUrl .= "?APPNAME=run&PRGNAME=ship_create_anonymous";

        return $serviceUrl;
    }

    /**
     * @return string
     */
    public function getCustomerNumber()
    {
        return $this->setting->get('betanet_epost_hfd_customer_number');
    }

    /**
     * @return int
     */
    public function isApiDebug()
    {
        return $this->setting->get('betanet_epost_hfd_debug');
    }

    /**
     * @return string
     */
    public function getSenderName()
    {
        return $this->setting->get('betanet_epost_hfd_sender_name') ?: '';
    }
}
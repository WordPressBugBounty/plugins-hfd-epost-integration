<?php
/**
 * Created by PhpStorm.
 * Date: 6/4/18
 * Time: 5:36 PM
 */
namespace Hfd\Woocommerce;

require 'AutoLoad.php';

class App
{
	const HFD_API_MIGRATION_VERSION = '2026-06-rest-api-v4';

    protected $registry;
    /**
     * Init plugin
     */
    public function init()
    {
        $autoload = new AutoLoad();

        spl_autoload_register(function ($class) use ($autoload) {
            $autoload->load($class);
        });

        /**
         * Init plugin classes
         */
        $registry = Registry::getInstance();
        $this->registry = $registry;

        $registry->set('autoload', $autoload);
        $this->registerHook();
    }

    /**
     * Register hook for plugin
     */
    public function registerHook()
    {
        add_filter('woocommerce_shipping_methods', array($this, 'registerShippingMethod'));
        add_filter('woocommerce_hidden_order_itemmeta', array($this, 'hiddenPickupMeta'));
        add_filter('woocommerce_order_shipping_to_display', array($this, 'emailPickupInfo'), 10, 2);

        add_action( 'woocommerce_after_shipping_rate', array($this, 'renderAdditional' ) );
        add_action('woocommerce_before_order_itemmeta', array($this, 'adminRenderPickup'), 10, 3);
        add_action('woocommerce_before_checkout_process', array($this, 'validatePickupInfo'));
        add_action('wp_footer', array($this, 'renderPickupMap'));
        add_action('wp_ajax_save_pickup', array($this, 'saveCartPickup'));
        add_action('wp_ajax_nopriv_save_pickup', array($this, 'saveCartPickup'));
        add_action('wp_ajax_get_spots', array($this, 'getSpots'));
        add_action('wp_ajax_nopriv_get_spots', array($this, 'getSpots'));
        add_action('wp_enqueue_scripts', array($this, 'loadStyles'));
        add_action('wp_enqueue_scripts', array($this, 'loadScripts'));
        add_action('plugins_loaded', array($this, 'initAdmin'));
		
		//create a endpoint for print label
		add_filter( 'generate_rewrite_rules', array( $this, 'registerEndpointForPrintLabel' ) );
		
		//white list our endpoint
		add_filter( 'query_vars', array( $this, 'whitelistEndpointForPrintLabel' ) );
		
		//print details
		add_action( 'template_redirect', array( $this, 'epostPrintLabel' ) );
		add_action( 'template_redirect', array( $this, 'epostTrackShipment' ) );
		
		//flush reqrite rules
		add_filter( 'admin_init', array( $this, 'flushRewriteUrls' ) );
				
		//add wordpress ron for auto sync
		add_filter( 'cron_schedules', array( $this, 'hfdAutoSyncOrderCron' ) );
		
		add_action( 'hfd_schedule_auto_sync', array( $this, 'hfdScheduleAutoSyncOrder' ) );
		
		// Schedule an action if it's not already scheduled
		if( !wp_next_scheduled( 'hfd_schedule_auto_sync' ) ){
			wp_schedule_event( time(), 'hfd_auto_sync', 'hfd_schedule_auto_sync' );
		}
				
		//action for gutenberg
		if( $this->is_block_checkout() && !is_admin() ){
			add_action( 'woocommerce_after_order_object_save', array( $this, 'hfd_save_pickup_info_block' ), 11, 2 );
		}else{
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'convertPickupToOrder'), 10, 3 );
			add_action( 'woocommerce_new_order', array($this, 'convertPickupToOrderOptional'), 99, 2 );
		}
		
		//action for render data
		add_action( 'wp_ajax_hfd_get_additional_data', array( $this, 'hfdRenderAdditionalData' ) );
		add_action( 'wp_ajax_nopriv_hfd_get_additional_data', array( $this, 'hfdRenderAdditionalData' ) );
		
		//update plugin settings if its not saved
        add_action( 'plugins_loaded', array( $this, 'hfdUpdatePluginsOptions' ) );
		
		//update option on plugin upgrade
		add_action( 'upgrader_process_complete', array( $this, 'hfd_run_on_plugin_update' ), 10, 2 );
    }
	
	public function hfd_run_on_plugin_update( $upgrader_object, $options ){
		if( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ){
			// Iterate through the plugins being updated and check if ours is there
			if( $options['plugins'] ){
				foreach( $options['plugins'] as $plugin ){
					if( $plugin == HFD_EPOST_PLUGIN_FILE ){
						$this->hfdUpdatePluginsOptions();
					}
				}
			}
		}
	}
		
	public function is_block_checkout(){
		$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
		if( $checkout_page_id ){
			//Get post object
			$post = get_post( $checkout_page_id );
			
			//Check if Gutenberg (block editor) is enabled for checkout
			return $post && post_type_supports( $post->post_type, 'editor' );
		}
		return false;
	}
	
    public function hfdUpdatePluginsOptions(){
		$this->migrateLegacyHfdApiOptions();

        $track_shipment_url = get_option( 'betanet_epost_hfd_track_shipment_url' );
        $cancel_shipment_url = get_option( 'betanet_epost_hfd_cancel_shipment_url' );
        $print_label_url = get_option( 'betanet_epost_hfd_print_label_url' );
        $hfd_order_auto_sync = get_option( 'hfd_order_auto_sync' );
        $hfd_sync_order_items = get_option( 'hfd_sync_order_items' );
		
        $hfd_epost_service_url = get_option( 'betanet_epost_service_url' );
        if( strpos( $hfd_epost_service_url, "http://" ) !== false || empty( $hfd_epost_service_url ) ){
            update_option( 'betanet_epost_service_url', 'https://api.hfd.co.il/rest/v2/epost-points/get-list-by-address' );
        } 
        if( empty( $track_shipment_url ) ){
            update_option( 'betanet_epost_hfd_track_shipment_url', 'https://run.hfd.co.il/info/{RAND}' );
        }
        if( empty( $cancel_shipment_url ) ){
            update_option( 'betanet_epost_hfd_cancel_shipment_url', 'https://api.hfd.co.il/rest/v2/shipments/{shipping_number}' );
        }
        if( empty( $print_label_url ) ){
            update_option( 'betanet_epost_hfd_print_label_url', 'https://api.hfd.co.il/rest/v2/shipments/{shipping_number}/label' );
        }
        if( empty( $hfd_order_auto_sync ) ){
            update_option( 'hfd_order_auto_sync', 'no' );
        }
		if( empty( $hfd_sync_order_items ) ){
            update_option( 'hfd_sync_order_items', 'no' );
        }
		
		//replace old urls to new
		if( strpos( $hfd_epost_service_url, 'ws.hfd.co.il' ) !== false || strpos( $hfd_epost_service_url, 'run.hfd.co.il' ) !== false || strpos( $hfd_epost_service_url, 'uniscripts/MGrqispi.dll' ) !== false ){
			update_option( 'betanet_epost_service_url', 'https://api.hfd.co.il/rest/v2/epost-points/get-list-by-address' );
		}
		
		$betanet_epost_hfd_service_url = get_option( 'betanet_epost_hfd_service_url' );
		if( empty( $betanet_epost_hfd_service_url ) ){
			update_option( 'betanet_epost_hfd_service_url', 'https://ws.hfd.co.il/RunCom.Server/Request.aspx' );
			$betanet_epost_hfd_service_url = 'https://ws.hfd.co.il/RunCom.Server/Request.aspx';
		}
		if( ( strpos( $betanet_epost_hfd_service_url, "uniscripts/MGrqispi.dll" ) !== false || strpos( $betanet_epost_hfd_service_url, "run.hfd.co.il" ) !== false ) && filter_var( $betanet_epost_hfd_service_url, FILTER_VALIDATE_URL ) == true ){
			$betanet_epost_hfd_service_url = str_replace( array( 'uniscripts/MGrqispi.dll', 'run.hfd.co.il' ), array( 'RunCom.Server/Request.aspx', 'ws.hfd.co.il' ), $betanet_epost_hfd_service_url );
			
			update_option( 'betanet_epost_hfd_service_url', $betanet_epost_hfd_service_url );
		}
		
		if(
			strpos( $track_shipment_url, 'ws.hfd.co.il' ) !== false ||
			strpos( $track_shipment_url, 'api.hfd.co.il/rest/v2/shipments/' ) !== false ||
			strpos( $track_shipment_url, 'run.hfd.co.il' ) !== false ||
			strpos( $track_shipment_url, 'uniscripts/MGrqispi.dll' ) !== false
		){
			update_option( 'betanet_epost_hfd_track_shipment_url', 'https://run.hfd.co.il/info/{RAND}' );
		}
		
		if( strpos( $cancel_shipment_url, 'ws.hfd.co.il' ) !== false || strpos( $cancel_shipment_url, 'run.hfd.co.il' ) !== false || strpos( $cancel_shipment_url, 'uniscripts/MGrqispi.dll' ) !== false ){
			update_option( 'betanet_epost_hfd_cancel_shipment_url', 'https://api.hfd.co.il/rest/v2/shipments/{shipping_number}' );
		}
		
		if( strpos( $print_label_url, 'ws.hfd.co.il' ) !== false || strpos( $print_label_url, 'run.hfd.co.il' ) !== false || strpos( $print_label_url, 'uniscripts/MGrqispi.dll' ) !== false ){
			update_option( 'betanet_epost_hfd_print_label_url', 'https://api.hfd.co.il/rest/v2/shipments/{shipping_number}/label' );
		}
    }

	protected function migrateLegacyHfdApiOptions(){
		$currentVersion = get_option( 'hfd_api_migration_version' );
		if( $currentVersion === self::HFD_API_MIGRATION_VERSION ){
			return;
		}

		$defaults = array(
			'betanet_epost_service_url' => 'https://api.hfd.co.il/rest/v2/epost-points/get-list-by-address',
			'betanet_epost_hfd_print_pdf_url' => 'https://api.hfd.co.il/rest/v2/shipments/{shipping_number}/label',
			'betanet_epost_hfd_track_shipment_url' => 'https://run.hfd.co.il/info/{RAND}',
			'betanet_epost_hfd_cancel_shipment_url' => 'https://api.hfd.co.il/rest/v2/shipments/{shipping_number}',
			'betanet_epost_hfd_print_label_url' => 'https://api.hfd.co.il/rest/v2/shipments/{shipping_number}/label',
		);

		foreach( $defaults as $optionName => $newValue ){
			$oldValue = get_option( $optionName );
			if( $oldValue === $newValue ){
				continue;
			}

			if( $this->shouldMigrateLegacyApiOption( $optionName, $oldValue ) ){
				update_option( $optionName . '_backup_pre_' . self::HFD_API_MIGRATION_VERSION, $oldValue );
				update_option( $optionName, $newValue );
			}
		}

		update_option( 'hfd_api_migration_version', self::HFD_API_MIGRATION_VERSION );
	}

	protected function shouldMigrateLegacyApiOption( $optionName, $optionValue ){
		if( empty( $optionValue ) ){
			return true;
		}

		if( !is_string( $optionValue ) ){
			return false;
		}

		$legacyNeedles = array(
			'ws.hfd.co.il',
			'api.hfd.co.il/rest/v2/shipments/',
			'run.hfd.co.il',
			'uniscripts/MGrqispi.dll',
			'RunCom.Server/Request.aspx',
			'ws_spotslist',
			'ship_locate_random',
			'bitul_mishloah',
			'ship_print_ws',
		);

		foreach( $legacyNeedles as $needle ){
			if( strpos( $optionValue, $needle ) !== false ){
				return true;
			}
		}

		if( $optionName === 'betanet_epost_service_url' && strpos( $optionValue, '/rest/v2/epost-points/get-list-by-address' ) === false ){
			return true;
		}

		if( $optionName === 'betanet_epost_hfd_track_shipment_url' && strpos( $optionValue, 'run.hfd.co.il/info/' ) === false ){
			return true;
		}

		if( in_array( $optionName, array(
			'betanet_epost_hfd_print_pdf_url',
			'betanet_epost_hfd_cancel_shipment_url',
			'betanet_epost_hfd_print_label_url',
		), true ) && strpos( $optionValue, '/rest/v2/shipments/' ) === false ){
			return true;
		}

		return false;
	}
    
	public function hfdScheduleAutoSyncOrder(){
		$hfd_order_auto_sync = get_option( 'hfd_order_auto_sync' );
		$hfd_auto_sync_time = get_option( 'hfd_auto_sync_time' );
		$hfd_auto_sync_status = get_option( 'hfd_auto_sync_status' );
				
		if( $hfd_order_auto_sync != "yes" || empty( $hfd_auto_sync_time ) || empty( $hfd_auto_sync_status ) )
			return;
		
		$args = array(
			'limit' => -1,
			'status' => array( $hfd_auto_sync_status ),
			'return' => 'ids',
			'meta_key'	=> 'hfd_sync_flag',
			'meta_compare' => 'NOT EXISTS',
			'date_query' => array(
				'after' => wp_date( 'Y-m-d H:i:s', strtotime( '-'.( $hfd_auto_sync_time + 10 ).' minutes' ) ),
				'before' => wp_date( 'Y-m-d H:i:s', strtotime( '-'.$hfd_auto_sync_time.' minutes' ) )
			),
		);
		$orderIds = wc_get_orders( $args );
		
		if( $orderIds ){
			/* @var \Hfd\Woocommerce\Helper\Hfd $hfdHelper */
			$hfdHelper = Container::create('Hfd\Woocommerce\Helper\Hfd');
			$result = $hfdHelper->sendOrders( $orderIds );
			$filesystem = Container::get('Hfd\Woocommerce\Filesystem');
			$filesystem->writeSession( serialize($result), 'sync_to_hfd' );
		}
	}
	
	public function hfdAutoSyncOrderCron( $schedules ){
		$hfd_auto_sync_time = get_option( 'hfd_auto_sync_time' );
		$hfd_order_auto_sync = get_option( 'hfd_order_auto_sync' );
		if( !empty( $hfd_auto_sync_time ) && $hfd_order_auto_sync == "yes" ){
			$schedules['hfd_auto_sync'] = array(
				'interval'  => 60,
				'display'   => sprintf( __( 'Every %s Minute', 'hfd-integration' ), 1 )
			);
		}
		return $schedules;
	}
	
	public function flushRewriteUrls(){
		$rules = $GLOBALS['wp_rewrite']->wp_rewrite_rules();
		if( !isset( $rules['printLabel/(\d+)/?$'] ) || !isset( $rules['trackShipment/([^/]+)/?$'] ) ){
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}
	}
	
	public function whitelistEndpointForPrintLabel( $query_vars ){
		$query_vars[] = 'epost-ship-number';
		$query_vars[] = 'epost-track-number';
		return $query_vars;
	}
	
	public function registerEndpointForPrintLabel( $wp_rewrite ){
		$wp_rewrite->rules = array_merge(
			array(
				'printLabel/(\d+)/?$' => 'index.php?epost-ship-number=$matches[1]',
				'trackShipment/([^/]+)/?$' => 'index.php?epost-track-number=$matches[1]',
			),
			$wp_rewrite->rules
		);
	}
	public function epostPrintLabel(){
		$epost_ship_number = intval( get_query_var( 'epost-ship-number' ) );
		if( $epost_ship_number ){
			$helper = \Hfd\Woocommerce\Container::get('Hfd\Woocommerce\Setting');
			$printLabelUrl = $helper->get( 'betanet_epost_hfd_print_label_url' );
			$authToken = $helper->get( 'betanet_epost_hfd_auth_token' );
			if( !empty( $authToken ) ){
				$args = array(
					'headers' => array(
						'Authorization' => 'Bearer '.$authToken,
						'Accept' => 'application/pdf',
					),
					'sslverify' => false
				);
				$printLabelUrl = str_replace(
					array( "{RAND}", "{shipping_number}" ),
					array( $epost_ship_number, $epost_ship_number ),
					$printLabelUrl
				);
				$response = wp_remote_get( $printLabelUrl, $args );
				if( !is_wp_error( $response ) ){
					$responseBody = wp_remote_retrieve_body( $response );
					$contentType = wp_remote_retrieve_header( $response, 'content-type' );
					if( strpos( $contentType, 'application/json' ) !== false ){
						$decoded = json_decode( $responseBody, true );
						if( !empty( $decoded['Base64String'] ) ){
							$responseBody = base64_decode( $decoded['Base64String'] );
						}
					}
					if( strpos( $responseBody, '%PDF' ) !== 0 ){
						return;
					}
					$fileName = $epost_ship_number.".pdf";
					header('Content-Type: application/pdf');
					header('Content-Length: '.strlen( $responseBody ));
					header('Content-disposition: inline; filename="'.$fileName.'"');
					header('Cache-Control: public, must-revalidate, max-age=0');
					header('Pragma: public');
					header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
					header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
					print $responseBody;
					exit;
				}
			}
		}
	}
	
	public function epostTrackShipment(){
		$trackNumber = sanitize_text_field( get_query_var( 'epost-track-number' ) );
		if( empty( $trackNumber ) ){
			return;
		}

		$helper = \Hfd\Woocommerce\Container::get('Hfd\Woocommerce\Setting');
		$trackShipmentUrl = $helper->get( 'betanet_epost_hfd_track_shipment_url' );
		if( empty( $trackShipmentUrl ) ){
			return;
		}

		$trackShipmentUrl = str_replace( '{RAND}', rawurlencode( $trackNumber ), $trackShipmentUrl );
		$requestArgs = array(
			'sslverify' => false,
			'timeout' => 20,
			'headers' => array(
				'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			),
		);

		$response = wp_remote_get( $trackShipmentUrl, $requestArgs );
		if( is_wp_error( $response ) ){
			$this->renderTrackShipmentDebugPage(
				$trackNumber,
				$trackShipmentUrl,
				$requestArgs,
				array(
					'wp_error' => $response->get_error_message(),
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$contentType = wp_remote_retrieve_header( $response, 'content-type' );
		$statusCode = wp_remote_retrieve_response_code( $response );
		$responseHeaders = wp_remote_retrieve_headers( $response );

		if( empty( $body ) || (int) $statusCode >= 400 ){
			$this->renderTrackShipmentDebugPage(
				$trackNumber,
				$trackShipmentUrl,
				$requestArgs,
				array(
					'status_code' => $statusCode,
					'content_type' => $contentType,
					'headers' => $responseHeaders,
					'body' => $body,
				)
			);
		}

		if( $contentType ){
			header( 'Content-Type: ' . $contentType );
		} else {
			header( 'Content-Type: text/html; charset=UTF-8' );
		}

		echo $body;
		exit;
	}

	protected function renderTrackShipmentDebugPage( $trackNumber, $requestUrl, $requestArgs, $responseData ){
		status_header( 200 );
		header( 'Content-Type: text/html; charset=UTF-8' );

		$requestHeaders = isset( $requestArgs['headers'] ) ? $requestArgs['headers'] : array();
		$requestBody = isset( $requestArgs['body'] ) ? $requestArgs['body'] : '';
		$responseHeaders = isset( $responseData['headers'] ) ? $responseData['headers'] : array();
		if( is_object( $responseHeaders ) && method_exists( $responseHeaders, 'getAll' ) ){
			$responseHeaders = $responseHeaders->getAll();
		}

		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html__( 'HFD Tracking Debug', 'hfd-integration' ) . '</title>';
		echo '<style>body{font-family:Arial,sans-serif;margin:24px;line-height:1.5;background:#f6f7f7;color:#1d2327}h1,h2{margin:0 0 16px}section{background:#fff;border:1px solid #dcdcde;padding:20px;margin-bottom:20px}pre{white-space:pre-wrap;word-break:break-word;background:#f6f7f7;border:1px solid #dcdcde;padding:12px;overflow:auto}table{border-collapse:collapse;width:100%}th,td{border:1px solid #dcdcde;padding:10px;text-align:left;vertical-align:top}th{width:220px;background:#f6f7f7}</style>';
		echo '</head><body>';
		echo '<h1>' . esc_html__( 'HFD Tracking Debug', 'hfd-integration' ) . '</h1>';

		echo '<section><table>';
		echo '<tr><th>' . esc_html__( 'Tracking Number', 'hfd-integration' ) . '</th><td>' . esc_html( $trackNumber ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Request URL', 'hfd-integration' ) . '</th><td>' . esc_html( $requestUrl ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'HTTP Method', 'hfd-integration' ) . '</th><td>GET</td></tr>';
		echo '<tr><th>' . esc_html__( 'Status Code', 'hfd-integration' ) . '</th><td>' . esc_html( isset( $responseData['status_code'] ) ? (string) $responseData['status_code'] : 'N/A' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Content Type', 'hfd-integration' ) . '</th><td>' . esc_html( isset( $responseData['content_type'] ) ? (string) $responseData['content_type'] : 'N/A' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'WP Error', 'hfd-integration' ) . '</th><td>' . esc_html( isset( $responseData['wp_error'] ) ? (string) $responseData['wp_error'] : '' ) . '</td></tr>';
		echo '</table></section>';

		echo '<section><h2>' . esc_html__( 'Request Headers', 'hfd-integration' ) . '</h2><pre>' . esc_html( wp_json_encode( $requestHeaders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . '</pre></section>';
		echo '<section><h2>' . esc_html__( 'Request Body', 'hfd-integration' ) . '</h2><pre>' . esc_html( $requestBody ? $requestBody : '(empty)' ) . '</pre></section>';
		echo '<section><h2>' . esc_html__( 'Response Headers', 'hfd-integration' ) . '</h2><pre>' . esc_html( wp_json_encode( $responseHeaders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . '</pre></section>';
		$responseBody = isset( $responseData['body'] ) && $responseData['body'] !== '' ? $responseData['body'] : '(empty)';
		echo '<section><h2>' . esc_html__( 'Response Body', 'hfd-integration' ) . ' (Raw)</h2><pre>' . esc_html( $responseBody ) . '</pre></section>';
		echo '<section><h2>' . esc_html__( 'Response Body', 'hfd-integration' ) . ' (Rendered HTML)</h2><div style="border:1px solid #dcdcde;padding:12px;background:#fff;overflow:auto;">' . ( $responseBody === '(empty)' ? esc_html( $responseBody ) : $responseBody ) . '</div></section>';
		echo '</body></html>';
		exit;
	}
    public function initAdmin()
    {
        $path = basename(HFD_EPOST_PATH). '/languages';
        load_plugin_textdomain('hfd-integration', false, $path);
        /* @var \Hfd\Woocommerce\Admin $admin */
        $admin = Container::get('Hfd\Woocommerce\Admin');
        $admin->init();
    }

    /**
     * @param array $methods
     * @return array
     */
    public function registerShippingMethod($methods)
    {
        $methods['betanet_epost'] = new \Hfd\Woocommerce\Shipping\Epost();
        $methods['betanet_govina'] = new \Hfd\Woocommerce\Shipping\Govina();
        $methods['betanet_home_delivery'] = new \Hfd\Woocommerce\Shipping\Home_Delivery();

        return $methods;
    }

    /**
     * Save pickup information into cart
     */
    public function saveCartPickup()
    {
		$out = array( "success" => 0, "msg" => __( "Something went wrong", "hfd-integration" ) );
        if( isset( $_POST['spot_info'] ) && wp_verify_nonce( $_POST['_ajax_nonce'], 'save_pickup' ) ){
            $spotInfo = array_map( 'sanitize_text_field', $_POST['spot_info'] );
            /* @var \Hfd\Woocommerce\Cart\Pickup $cartPickup */
            $cartPickup = Container::get('Hfd\Woocommerce\Cart\Pickup');
            $cartPickup->saveSpotInfo( $spotInfo );
			
			$out = array( "success" => 1, "msg" => __( "Pickup saved", "hfd-integration" ) );
        }else{
			$out = array( "success" => 1, "msg" => __( "Incorrect nonce or spot info missing", "hfd-integration" ) );
		}
		echo wp_json_encode( $out );
		exit;
    }

    /**
     * Retrieve list spots
     */
    public function getSpots()
    {
        if (isset($_GET['city'])) {
            return $this->getSpotsByCity( sanitize_text_field( $_GET['city'] ) );
        }

        $helper = Container::get('Hfd\Woocommerce\Helper\Spot');
        $spots = $helper->getSpots();
        header('Content-type: application/json');
        echo wp_json_encode($spots);
        exit;
    }

    public function getSpotsByCity($city)
    {
        $helper = Container::get('Hfd\Woocommerce\Helper\Spot');
        $spots = $helper->getSpotsByCity($city);
        header('Content-type: application/json');
        echo wp_json_encode($spots);
        exit;
    }
	
	/**
     * @param \WC_Order $order
     * @param array $data
     */
	public function hfd_save_pickup_info_block( $order, $data ){
		if( $order ){
			if( in_array( $order->get_status(), array( 'pending', 'processing' ) ) ){
				/* @var \Hfd\Woocommerce\Cart\Pickup $cartPickup */
				$cartPickup = Container::get( 'Hfd\Woocommerce\Cart\Pickup' );
				$cartPickup->convertToOrder( $order );
			}
		}
	}
	
	public function hfdRenderAdditionalData(){
		/* @var \Hfd\Woocommerce\Shipping\Additional $additionalBLock */
        $additionalBLock = Container::create('Hfd\Woocommerce\Shipping\Additional');
        $out = array( "html" => $additionalBLock->render() );
		
		$cities = array();
		if( isset( $_POST['load_cities'] ) ){
			$hfdHelper = \Hfd\Woocommerce\Container::get('Hfd\Woocommerce\Helper\Spot');;
			$cities = $hfdHelper->getCities();
		}
		$out['cities'] = $cities;
		echo json_encode( $out );
        exit;
	}
	
    /**
     * @param int $orderId
     * @param array $data
     * @param \WC_Order $order
     */
    public function convertPickupToOrder($orderId, $data, $order)
    {
        /* @var \Hfd\Woocommerce\Cart\Pickup $cartPickup */
        $cartPickup = Container::get('Hfd\Woocommerce\Cart\Pickup');
        $cartPickup->convertToOrder($order);
    }
	
	/**
     * @param int $orderId
     * @param array $data
     * @param \WC_Order $order
     */
	 
	public function convertPickupToOrderOptional( $orderId, $order )
	{
		/* @var \Hfd\Woocommerce\Cart\Pickup $cartPickup */
        $cartPickup = Container::get('Hfd\Woocommerce\Cart\Pickup');
        $cartPickup->convertToOrder($order);
	}
	
    /**
     * @param int $itemId
     * @param \WC_Order_Item_Shipping $item
     */
    public function adminRenderPickup($itemId, $item)
    {
        if ($item->get_type() != 'shipping') {
            return;
        }

        /* @var \Hfd\Woocommerce\Order\Pickup $orderPickup */
        $orderPickup = Container::create('Hfd\Woocommerce\Order\Pickup');
        echo $orderPickup->renderAdminInfo($item);
    }

    /**
     * @param string $text
     * @param \WC_Order $order
     * @return string
     */
    public function emailPickupInfo($text, $order)
    {
        /* @var \Hfd\Woocommerce\Order\Pickup $orderPickup */
        $orderPickup = Container::create('Hfd\Woocommerce\Order\Pickup');
        $shippingItem = $orderPickup->getShippingItem($order);

        if ($shippingItem) {
            $spotInfo = $shippingItem->get_meta('epost_pickup_info');
            if ($spotInfo) {
                $spotInfo = unserialize($spotInfo);

                $html = '<p>';
                $html .= sprintf(
                    '<strong>%s:</strong> %s<br />',
                    __('Branch name', 'hfd-integration'),
                    $spotInfo['name']
                );
                $html .= sprintf(
                    '<strong>%s:</strong> %s %s, %s<br />',
                    __('Branch address', 'hfd-integration'),
                    $spotInfo['street'],
                    $spotInfo['house'],
                    $spotInfo['city']
                );
                $html .= sprintf(
                    '<strong>%s:</strong> %s',
                    __('Operating hours', 'hfd-integration'),
                    $spotInfo['remarks']
                );
                $html .= '</p>';

                $text .= $html;
            }

        }

        return $text;
    }

    public function validatePickupInfo()
    {
        $message = '<ul class="woocommerce-error" role="alert"><li>%s</li></ul>';
        $response = array(
            'messages'  => '',
            'refresh'   => false,
            'reload'    => false,
            'result'    => 'failure'
        );

        if( !isset( $_POST['shipping_method'] ) ){
            return;
        }

        $shippingMethods = array_map( 'sanitize_text_field', $_POST['shipping_method'] );
        $isEpost = false;
        /* @var \Hfd\Woocommerce\Shipping\Epost $epostShipping */
        $epostShipping = Container::get('Hfd\Woocommerce\Shipping\Epost');
        foreach ($shippingMethods as $shippingMethod) {
            if ($epostShipping->isEpost($shippingMethod)) {
                $isEpost = true;
                break;
            }
        }

        if ($isEpost) {
            /* @var \Hfd\Woocommerce\Cart\Pickup $cartPickup */
            $cartPickup = Container::get('Hfd\Woocommerce\Cart\Pickup');
            $spotInfo = $cartPickup->getSpotInfo();
            if (!$spotInfo || !$spotInfo['n_code']) {
                $response['messages'] = sprintf($message, __('Please choose pickup branch', 'hfd-integration'));
                header('Content-type: application/json');
                echo wp_json_encode( $response );
                exit;
            }
        }
    }

    /**
     * @param array $metaKeys
     * @return array
     */
    public function hiddenPickupMeta($metaKeys)
    {
        $metaKeys[] = 'epost_pickup_info';

        return $metaKeys;
    }

    /**
     * Load plugin styles
     */
    public function loadStyles()
    {
        wp_enqueue_style('betanet-epost-jqueryui', HFD_EPOST_PLUGIN_URL . '/css/jquery-ui.min.css');
        wp_enqueue_style('betanet-epost-style', HFD_EPOST_PLUGIN_URL . '/css/style.css');
    }

    public function loadScripts()
    {
        wp_enqueue_script( 'jquery-ui-dialog' );
    }

    /**
     * Render pickup button
     * @param \WC_Shipping_Rate $method
     * @return void
     */
    public function renderAdditional( $method )
    {
        if( $method->get_method_id() != 'betanet_epost' ){
            return;
        }
				
        /* @var \Hfd\Woocommerce\Shipping\Additional $additionalBLock */
        $additionalBLock = Container::create('Hfd\Woocommerce\Shipping\Additional');
        echo $additionalBLock->render();
        return;
    }

    public function renderPickupMap()
    {
		if( function_exists( 'is_cart' ) && function_exists( 'is_checkout' ) && ( is_cart() || is_checkout() ) ){
			$template = Container::create('Hfd\Woocommerce\Template');
			echo $template->fetchView('cart/footer.php');
		}
    }

    public function pluginActivation()
    {
        /* @var \Hfd\Woocommerce\Setting $setting */
        $setting = Container::get( 'Hfd\Woocommerce\Setting' );
        $setting->initDefaultSetting();
    }

    public function pluginDeactivation()
    {
        //
    }
}

<?php
/**
 * Created by PhpStorm.
 * Date: 6/6/18
 * Time: 2:04 PM
 */
namespace Hfd\Woocommerce\Helper;

use Hfd\Woocommerce\Container;

class Spot
{
    const CACHE_KEY = 'betanet_epost_spot_listing';

    protected $cities = array();

    public function getSpots()
    {
        $spots = $this->loadCache();

        if( $spots ){
            return $spots;
        }
		
        $spots = $this->requestSpots();

        $this->saveCache( $spots );

        return $spots;
    }

    public function getSpotsByCity($city)
    {
        $cache = Container::get('Hfd\Woocommerce\Cache');
        $cacheKey = self::CACHE_KEY . '_' . md5($city);
        $spots = $cache->get($cacheKey);
        if( $spots ){
            return $spots;
        }
		
        $spots = $this->requestSpots($city);

        $cache->save($cacheKey, $spots);

        return $spots;
    }

    /**
     * @return mixed
     */
    protected function loadCache()
    {
        $cache = Container::get('Hfd\Woocommerce\Cache');
        $spots = $cache->get(self::CACHE_KEY);

        return $spots;
    }

    protected function saveCache($data)
    {
        $cache = Container::get('Hfd\Woocommerce\Cache');
        $cache->save(self::CACHE_KEY, $data);

        return $this;
    }

    /**
     * @param string $city
     * @return mixed
     */
    public function getServiceUrl($city = 'all')
    {
        $setting = Container::get('Hfd\Woocommerce\Setting');
        return $setting->get('betanet_epost_service_url');
    }

    public function getCities()
    {
        if ($this->cities) {
            return $this->cities;
        }

        return $this->loadCities();
    }

    protected function loadCities()
    {
        $file = HFD_EPOST_PATH . '/data/city.csv';
        if (!file_exists($file)) {
            return array();
        }

        $fp = fopen($file,"r");
        while ($row = fgetcsv($fp)) {
            $this->cities[] = $row[0];
        }
        fclose($fp);

        sort($this->cities);
        return $this->cities;
    }

    protected function requestSpots($city = '')
    {
        $setting = Container::get('Hfd\Woocommerce\Setting');
        $authToken = $setting->get('betanet_epost_hfd_auth_token');
        $clientId = (string) $setting->get('betanet_epost_hfd_customer_number');

        if (empty($authToken) || empty($clientId)) {
            return array();
        }

        $payload = array(
            'clientId' => $clientId,
            'city' => $city ? $city : '',
            'shipmentDirection' => 'מסירה',
            'language' => 'HE',
            'street' => '',
            'openingHoursFormat' => false,
        );

        $args = array(
            'timeout' => 15,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Bearer ' . $authToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => wp_json_encode($payload),
        );

        $response = wp_remote_post($this->getServiceUrl($city), $args);
        if (is_wp_error($response)) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return array();
        }

        $spots = array();
        foreach ($decoded as $spot) {
            if (!is_array($spot)) {
                continue;
            }
            $normalized = $this->normalizeSpot($spot);
            if (!empty($normalized['n_code'])) {
                $spots[$normalized['n_code']] = $normalized;
            }
        }

        return $spots;
    }

    protected function normalizeSpot($spot)
    {
        $openingHours = '';
        if (!empty($spot['openingHours']) && is_array($spot['openingHours'])) {
            $parts = array();
            foreach ($spot['openingHours'] as $day) {
                if (empty($day['day'])) {
                    continue;
                }

                if (!empty($day['trading'])) {
                    $parts[] = sprintf('%s %s-%s', $day['day'], $day['open'], $day['close']);
                } else {
                    $parts[] = sprintf('%s %s', $day['day'], __('Closed', 'hfd-integration'));
                }
            }
            $openingHours = implode(', ', $parts);
        }

        $remarks = '';
        if (!empty($spot['spotRemarks'])) {
            $remarks = $spot['spotRemarks'];
        } elseif ($openingHours) {
            $remarks = $openingHours;
        }

        return array(
            'n_code' => isset($spot['spotId']) ? (string) $spot['spotId'] : '',
            'name' => isset($spot['spotName']) ? $spot['spotName'] : '',
            'type' => isset($spot['spotType']) ? $spot['spotType'] : '',
            'city' => isset($spot['city']) ? $spot['city'] : '',
            'street' => isset($spot['street']) ? $spot['street'] : '',
            'house' => isset($spot['houseNo']) ? $spot['houseNo'] : '',
            'remarks' => $remarks,
            'latitude' => isset($spot['latitude']) ? $spot['latitude'] : '',
            'longitude' => isset($spot['longitude']) ? $spot['longitude'] : '',
            'city_code' => '',
            'street_code' => '',
            'mesirot_yn' => '',
            'ahzarot_yn' => '',
            'spotAddress' => isset($spot['spotAddress']) ? $spot['spotAddress'] : '',
            'spotDistanceText' => isset($spot['spotDistanceText']) ? $spot['spotDistanceText'] : '',
            'sortingCode' => isset($spot['sortingCode']) ? $spot['sortingCode'] : '',
        );
    }
}

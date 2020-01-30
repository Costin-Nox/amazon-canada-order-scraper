<?php
/**
 * Amazon order history parser.
 * 
 * @author  Costin Ghiocel <me@costingcl.com>
 */

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client;
use Tightenco\Collect\Support\Collection;

class AmazonScraper {

    private $session;
    private $agent;
    private $client;
    private $stem;
    private $data         = array();
    private $pages        = 0;
    private $validFilters = [2021, 2020, 2019, 2018, 2017, 2016, 2015, 2014, 2013, 2012];

    public function __construct(string $session, string $agent, ?int $filter = null) 
    {
        if ($filter && !in_array($filter, $this->validFilters)) {
            _log('Please provide a valid year, ie: 2020', true);
            die;
        }

        if ($filter) {
            _log('Getting stats for year '. $filter);
            $this->stem = "ref=ppx_yo_dt_b_pagination_1_2?ie=UTF8&orderFilter=year-{$filter}&search=&startIndex=";
        }
        else {
            _log('Getting stats for last 6 months.');
            $this->stem = "ref=ppx_yo_dt_b_pagination_1_2?ie=UTF8&orderFilter=months-6&search=&startIndex=";
        }

        $this->session = $session;
        $this->agent   = $agent;
        $this->client  = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'https://www.amazon.ca/gp/your-account/order-history/',
            // pages take a while sometimes....
            'timeout'  => 10.0,
        ]);

        $this->pages = $this->_build();
    }

    /**
     * Cast to json
     * 
     * @return string [description]
     */
    public function __toString()
    {
        return json_encode($this->data);
    }

    /**
     * Build data.
     * 
     * @param  string|null $filter [description]
     * @return [type]              [description]
     */
    private function _build(?string $filter = null) : int
    {
        $page = 0;
        $data = array();

        do {
            $pageData = $this->parsePage($page);
            $page++;
            $data = array_merge($data, $pageData);
        } while (count($pageData));

        $this->data = collect($data);

        if (empty($data)) {
            _log('Nothing.. are your session cookie/agent correct? Is the date range valid? Do you get results for it on the order page?', true);
            die;
        }
        
        return $page;
    }

    /**
     * Looks ugly thrown somewhere else..puurrrdddyyyy
     * 
     * @return array
     */
    private function buildHeaders() : array 
    {
        return array(
            'accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'accept-encoding'           => 'gzip, deflate, br',
            'accept-language'           => 'en-US,en;q=0.9,ro;q=0.8',
            'cache-control'             => 'max-age=0',
            'cookie'                    => $this->session,
            'dnt'                       => 1,
            'sec-fetch-mode'            => 'navigate',
            'sec-fetch-site'            => 'same-origin',
            'sec-fetch-user'            => '?1',
            'upgrade-insecure-requests' => 1,
            'user-agent'                => $this->agent,

        );
    }

    /**
     * Get a page from history.
     * 
     * @param  int  $index Index basically where to start.. amazon returns 10 items per page.
     * 
     * @return PSR7 Reponse
     */
    private function getPageIndex(int $index) : Response
    {
        $headers = $this->buildHeaders();
        $uri     = $this->stem . $index;
        $tries   = 0;

        do {
            _log("Fetching index {$index}..");

            try {
                $response = $this->client->request('GET', $uri, ['headers' => $headers]);
            } catch (\GuzzleHttp\Exception\ConnectException $e) {
                $response = null;
                _log("Failed to fetch index {$index}.. retrying", true);
            }

            $tries++;
        } while (empty($response) && $tries<5);
        
        if (empty($response)) {
            _log('Failed to get data from amazon.. aborting.', true);
            die;
        }

        return $response;
    }

    /**
     * Shipment parser
     * 
     * @param  string $html
     * 
     * @return array
     */
    private function parseShipment(string $html) : array
    {
        $orderHtml       = new DOMDocument();
        @$orderHtml->loadHTML("<html><body>".$html."</body></html>");

        $shipsXpath      = new DOMXpath($orderHtml);
        $shipments       = $shipsXpath->query("//div[contains(@class,'a-box shipment')]");
        $shipmentsParsed = [];

        foreach ($shipments as $skey => $shipment) 
        {
            $sHtml                  = $shipment->ownerDocument->saveXML($shipment);
            $shipmentsParsed[$skey] = array();

            //get status for this box.
            $re = '/a-row shipment-top-row.*\s+<div.*\s+.*class="a-row">\s+<span class="a-size-medium.*>\s+(.*)\s+<\/span>/m';
            preg_match_all($re, $sHtml, $matches, PREG_SET_ORDER, 0);
            $shipmentsParsed[$skey]['status'] = empty($matches[0][1]) ? 'Delivered' : $matches[0][1];

            //get item name(s) and price(s)
            $re = '/<a .*"\/gp\/product.*>\s+(\w+.*)\s+.*|\s+<span.*price">\s+CDN\$\s(.*)\s<\/span>/m';
            preg_match_all($re, $sHtml, $matches, PREG_SET_ORDER, 0);

            foreach($matches as $keyItem => $match) 
            {
                //name
                if(!empty($match[1])){
                    $shipmentsParsed[$skey]['items'][$keyItem]['name'] = $match[1];
                }
                //price
                if(!empty($match[2])){
                    $shipmentsParsed[$skey]['items'][$keyItem-1]['price'] = (double) $match[2];
                }
            }
        }

        return $shipmentsParsed;
    }

    /**
     * Parse the returned page.
     * 
     * @param  Response $response [description]
     * 
     * @return array
     */
    private function parse(Response $response) : array
    {
        $topDom = new DOMDocument();
        @$topDom->loadHTML($response->getBody());

        $xpath  = new DOMXpath($topDom);
        $orders = $xpath->query("//div[contains(@class,'order')]");

        $ordersParsed = array();

        foreach($orders as $key => $order){
            if ($key % 2) { continue; }

            $matches = array();
            $html    = $order->ownerDocument->saveXML($order);

            /** Get Price */
            $price = '/a-color.*">\n\s+CDN\$\s(.*)/m';
            preg_match_all($price, $html, $matches, PREG_SET_ORDER, 0);

            //gets some garbage, price is easy to detect, skip if not there.. use the array not the cast, some can be 0
            if (empty($matches[0][1])) { continue; }

            $price = (double)$matches[0][1];

            /** get orderId */
            $re = '/orderID=(.*\d+)[\\\\|.|"]/m';
            preg_match_all($re, $html, $matches, PREG_SET_ORDER, 0);
            $orderId = $matches[0][1];

            $ordersParsed[$orderId]['orderId'] = $orderId;
            $ordersParsed[$orderId]['price']   = $price;

            /** Get Date */
            $date = '/<span class="a-color-secondary value">\s+(.+ \d+, \d+)/m';
            preg_match_all($date, $html, $matches, PREG_SET_ORDER, 0);
            $ordersParsed[$orderId]['date'] = $matches[0][1];

            /** Shipments/Items */
            $ordersParsed[$orderId]['shipments'] = $this->parseShipment($html, $ordersParsed);
        }

        return $ordersParsed;
    }

    /**
     * Fetch and parse a page.
     * 
     * @param  int    $page Page number.
     * 
     * @return array
     */
    public function parsePage(int $page) : array 
    {
        $index    = $page * 10;
        $response = $this->getPageIndex($index);

        return $this->parse($response);
    }

    /**
     * Public getter.
     * 
     * @return array
     */
    public function getResult() : Collection
    {
        return $this->data;
    }
}

?>
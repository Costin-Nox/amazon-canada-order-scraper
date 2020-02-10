<?php
/**
 * Parse AmazonScraper data and output to csv.
 * 
 * @author  Costin G <me@costingcl.com>
 */

use Carbon\Carbon;
use League\Csv\Writer;
use Tightenco\Collect\Support\Collection;

class DataParser 
{
	private $data;
	private $csv;
	private $computed;

	public function __construct(Collection $data, string $fileName) 
	{
		Carbon::setToStringFormat('jS \o\f F, Y');

		$this->fileName = $fileName;
		$this->data     = $data;
		$this->csv      = Writer::createFromPath($this->fileName, "w");

		$this->csv->insertOne(['orderid', 'date', 'product', 'status', 'price']);
		$this->computed = $this->_buildData();
	}

	private function _buildData() : array 
	{
		$computed = 
			[
				'totalSpent'         => 0,
				'totalSpentItems'    => 0,
				'totalOrders'        => 0,
				'packagedReceived'   => 0,
				'totalItems'         => 0,
				'refundedItemsCount' => 0,
				'totalRefunded'      => 0,
				'monthly'            => [],
			];

		foreach ($this->data as $orderId => $order) 
		{
			$orderedOn                = new Carbon($order['date']);
			$total                    = $order['price'];
			$computed['totalSpent']  += $total;
			$computed['totalOrders'] ++;


			foreach ($order['shipments'] as $shipment) 
			{
				$computed['packagedReceived'] ++;
				$status = null;

				if (strpos($shipment['status'], 'Delivered') !== false) 
				{
					$status = 'delivered';
				} 
				elseif (strpos($shipment['status'], 'Return') !== false)
				{
					$status = 'returned';
				}
				else
				{
					$status = $shipment['status'];
				}

				foreach ($shipment['items'] as $item) 
				{
					if (!isset($item['price'])) {
						$priceReadable = 'n/a';
						$price         = 0;
					} else {
						$priceReadable = $item['price'];
						$price         = $item['price'];
					}

					if ($status == 'returned') {
						$computed['refundedItemsCount'] ++;
						$computed['totalRefunded'] += $price;
					}

					$computed['totalItems'] ++;
					$computed['totalSpentItems'] += $price;

					$itemName = empty($item['name']) ? 'Uknown' : $item['name'];

					$this->csv->insertOne([$orderId, $orderedOn, $itemName, $status, $priceReadable]);
				}
			}
		}

		return $computed;
	}

	public function getComputed() : array
	{
		return $this->computed;
	}
}

?>
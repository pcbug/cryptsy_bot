<?php
require_once "cryptsy_lib.php";
require_once "cryptsy_bot.php";

include "config.php";

class my_doge_bot extends cryptsy_bot
{
	public function my_doge_bot($key,$secret)
	{

		$this->profit_percent = isset($profit_percent)?$profit_percent:1.02;
		$this->stop_lost_percent = isset($stop_lost_percent)?$stop_lost_percent:0.98;
		$this->order_proportion = isset($order_proportion)?$order_proportion:0.7;
		$this->sell_proportion = isset($sell_proportion)?$sell_proportion:0.7;

		$this->buy_count = 0;
		$this->sell_count = 0;

		// It's a bot to sell/buy DOGE and BTC only
		$this->set_key($key , $secret, "DOGE/BTC");
	}

	/*
	 * [cur_buy_price] => 0.00000209
	 * [cur_sell_price] => 0.00000210
	 * [my_wallet] => Array
	 * 	(
	 * 		[LTC] => 0.00000000
	 * 		[BTC] => 0.00000000
	 * 		[FTC] => 0.00000000
	 * 		[LEAF] => 0.00000000
	 * 		[MEOW] => 0.00000000
	 * 		[CASH] => 0.00000000
	 * 		[VTC] => 0.00000000
	 * 	)
	 * [my_orders] => Array
	 * 	(
	 *		[0] => Array
	 *		(
	 *			[orderid] => 39960050
	 *			[created] => 2014-02-09 04:46:27
	 *			[ordertype] => Buy
	 *			[price] => 0.00000163
	 *			[quantity] => 43123.00000000
	 *			[orig_quantity] => 43123.00000000
	 *			[total] => 0.07029049
	 *		)
	 *		[1] => Array
	 *		(
	 *			[orderid] => 39960053
	 *			[created] => 2014-02-09 04:46:28
	 *			[ordertype] => Sell
	 *			[price] => 0.00000169
	 *			[quantity] => 12571.00000000
	 *			[orig_quantity] => 12571.00000000
	 *			[total] => 0.02124499
	 *		)
	 *	)
	 * [my_trade] => Array
	 * 	(
	 *		[0] => Array
	 *		(
	 *			[orderid] => 39960050
	 *			[created] => 2014-02-09 04:46:27
	 *			[ordertype] => Buy
	 *			[price] => 0.00000163
	 *			[quantity] => 43123.00000000
	 *			[orig_quantity] => 43123.00000000
	 *			[total] => 0.07029049
	 *		)
	 *	)
	 */
	protected function init($data)
	{
		// initialize
		// read history data
		// train your parmaters
		$this->show_status();
	}

	/*
	 * main algorithm
	 */
	protected function tick($data)
	{
		$this->data = $data;

		$cur_buy_price = $data["cur_buy_price"];
		$cur_sell_price = $data["cur_sell_price"];
		$my_orders = $data["my_orders"];
		$my_btc = $data["my_wallet"]["BTC"];
		$my_doge = $data["my_wallet"]["DOGE"];

		// ATTENTION!!! only support 1 buy and 1 sell order
		if( sizeof($my_orders) == 0)
		{
			$this->place_order();
			$this->show_status();
			return;
		}
		else if(sizeof($my_orders) == 1) // bought or sold an order, cancel old order and re-create all orders
		{
			if(sizeof($data["my_trade"]) != 0)
			{
				$my_trade = $data["my_trade"][0];
				print($my_trade["ordertype"]." ".$my_trade["quantity"]." at price ".$my_trade["price"]." , total btc ".$my_trade["total"]."\n");

				if( $my_trade["ordertype"] == "Buy")
				{
					$this->buy_count++;
					$this->sell_count = 0;
				}
				else if( $my_trade["ordertype"] == "Sell")
				{
					$this->sell_count++;
					$this->buy_count = 0;
				}
			}

			$this->cancel_market_orders();

			if( $buy_count >= 3) // big drop now, sleep 1 hour
				sleep(360);

			return;
		}
		else if( sizeof($my_orders) > 2)
		{
			// something wrong here
			$this->cancel_market_orders();
			return;
		}
	}
	protected function place_order()
	{
		$my_btc = $this->data["my_wallet"]["BTC"];
		$my_doge = $this->data["my_wallet"]["DOGE"];
		$cur_buy_price = $this->data["cur_buy_price"];
		$cur_sell_price = $this->data["cur_sell_price"];

		if( $my_btc > 0)
			$this->create_buy_order($cur_sell_price*pow($this->stop_lost_percent,$this->buy_count+1), floor($my_btc/$cur_sell_price*$this->order_proportion));
		if( $my_doge > 0)
		{
			// we don't want to sell too much if we just bought many times
			$quantity = floor($my_doge*$this->sell_proportion);
			if($this->buy_count >= 2)
				$quantity = floor($my_doge*pow($this->sell_proportion,$this->buy_count));
			$this->create_sell_order($cur_buy_price*$this->profit_percent, $quantity);
		}
	}
	protected function show_status()
	{
		$this->data = $this->update_data();
		$my_btc = $this->data["my_wallet"]["BTC"];
		$my_doge = $this->data["my_wallet"]["DOGE"];
		$cur_buy_price = $this->data["cur_buy_price"];
		$cur_sell_price = $this->data["cur_sell_price"];
		$my_orders = $this->data["my_orders"];


		$myorder0 = 0;
		if(sizeof($my_orders) >= 1)
			$myorder0 = $my_orders[0];
		$myorder1 = 0;
		if(sizeof($my_orders) >= 2)
			$myorder0 = $my_orders[1];
		if(sizeof($my_orders) == 0)
		{
			$dt = new DataTime();
			$dt->setTimezone(new DateTimeZone('EST'));
			$datetime = $dt->format('Y-\m\-d\ h:i:s');
		}
		else
			$datetime = $my_orders[0]["created"];

		$total_btc = $my_btc + $my_doge*$cur_buy_price + $myorder0["quantity"]*$cur_buy_price + $myorder1["quantity"]*$cur_buy_price;
		print("=================================================================================\n");
		print($datetime." - total btc=".$total_btc." , cur_price=".$cur_buy_price.", my_btc=".$my_btc." , my_doge=".$my_doge." , b_c=".$this->buy_count." , s_c=".$this->sell_count."\n");
		foreach($my_orders as $order)
			print("   my orders - ".$order["ordertype"]." ".$order["quantity"]." at price ".$order["price"]." , total btc ".$order["total"]."\n");
	}

	private $profit_percent;
	private $stop_lost_percent;
	private $order_proportion;
	private $sell_proportion;
	private $buy_count;
	private $sell_count;
	private $data;
}

$my_bot = new my_doge_bot($key,$secret);
$my_bot->run();

?>
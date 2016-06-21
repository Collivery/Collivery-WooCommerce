<?php
namespace MdsSupportingClasses;
/**
 * Created by PhpStorm.
 * User: peter
 * Date: 2016/06/14
 * Time: 8:25 AM
 *
 * Handles calculation of discount for the clients
 */
class DiscountCalculator
{
	/**
	 * @var
	 */
	private $settings;
	/**
	 * @var mixed
	 */
	private $current_order;
	private $discount = -1;

	public function __construct($settings)
	{
		$this->settings = $settings;
	}

	/**
	 * @param mixed $order
	 * @return DiscountCalculator
	 * @throws Exception
	 */
	public function start($order){
		if(!$order)
			throw new Exception('Invalid order');
		$this->current_order = $order;
		return $this;
	}

	/**
	 * Get the amount of discount a order gets
	 * @return DiscountCalculator
	 * @throws Exception
	 */
	public function calculate(){
		if(!$this->current_order)
			throw new Exception('DiscountCalculator::start(WC_Order $order) was not called');

		if($this->settings['method_free'] !== 'discount'){
			$this->discount = .0;
			return $this;
		}

		if(is_array($this->current_order))
			$total = $this->current_order['cart']['total'];
		else
			$total = $this->current_order->get_subtotal() + $this->current_order->get_cart_tax();

		if($total < $this->settings['free_min_total']){
			$this->discount = .0;
			return $this;
		}

		$this->discount = ($this->settings['shipping_discount_percentage'] / 100 * $total);
		return $this;
	}

	public function orderGetsDiscount($order)
	{
		return $this->start($order)->calculate()->getResult() > 0;
	}

	/**
	 * Gets the amount of discount on shipping that one get
	 * @return float
	 * @throws Exception
	 */
	public function getResult(){
		if($this->discount === -1)
			throw new Exception('The calculator was not initialized');

		$discount = $this->discount;
		$this->reset();
		return $discount;
	}

	private function reset(){
		$this->current_order = null;
		$this->discount = -1;
	}

	public function getCurrentOrder(){
		return $this->current_order;
	}
}
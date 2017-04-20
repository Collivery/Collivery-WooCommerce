<?php

namespace MdsSupportingClasses;

class Money
{
    /**
     * @var float
     */
    public $amount;

    /**
     * @var int
     */
    protected $markup;

    /**
     * @var bool
     */
    protected $shouldRound;

    /**
     * @var int
     */
    protected $discountPercentage;

    /**
     * Money constructor.
     *
     * @param $amount
     * @param $markup
     * @param $discountPercentage
     * @param $shouldRound
     */
    protected function __construct($amount, $markup, $discountPercentage, $shouldRound)
    {
        $this->markup = $markup;
        $this->discountPercentage = $discountPercentage;
        $this->shouldRound = $shouldRound;

        $this->amount = $this->process($amount);
    }

    /**
     * @param $amount
     * @param $markup
     * @param $discountPercentage
     * @param $shouldRound
     *
     * @return Money
     */
    public static function make($amount, $markup, $discountPercentage, $shouldRound)
    {
        return new self($amount, $markup, $discountPercentage, $shouldRound);
    }

    /**
     * @param $amount
     *
     * @return float|string
     */
    private function process($amount)
    {
        if ($this->markup > 0) {
            $amount += $amount * ($this->markup / 100);
        }

        return $this->applyDiscount($amount);
    }

    /**
     * @param float $amount
     *
     * @return float
     */
    public function applyDiscount($amount)
    {
        if ($this->discountPercentage > 0) {
            $amendedAmount = $amount - (($this->discountPercentage / 100) * $amount);

            return $this->shouldRound ? $this->round($amendedAmount) : $this->format($amendedAmount);
        } else {
            return $this->shouldRound ? $this->round($amount) : $this->format($amount);
        }
    }

    /**
     * Adds markup to price.
     *
     * @param $price
     * @param $markup
     *
     * @return float|string
     */
    public function addMarkup($price, $markup)
    {
        $price += $price * ($markup / 100);

        return $this->shouldRound ? $this->round($price) : $this->format($price);
    }

    /**
     * Format a number with grouped thousands.
     *
     * @param $price
     *
     * @return string
     */
    public function format($price)
    {
        return number_format($price, 2, '.', '');
    }

    /**
     * Rounds number up to the next highest integer.
     *
     * @param $price
     *
     * @return float
     */
    public function round($price)
    {
        return ceil($this->format($price));
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->amount;
    }
}

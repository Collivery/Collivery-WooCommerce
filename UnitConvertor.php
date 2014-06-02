<?php

class UnitConvertor {

    var $conversion_table = array();
    var $decimal_point;
    var $thousand_separator;
    var $bases = array();

    function __construct()
    {
	// Kilogramme, Gramme, Milligramme, Pounds, Ounce
	// Metres, Centimetres, Millimetres, Yards, Foot, Inches
	$conversions = array(
	    'Weight' => array(
		'base' => 'kg',
		'conv' => array(
		    'g' => 1000,
		    'mg' => 1000000,
		    't' => 0.001,
		    'oz' => 35.274,
		    'lb' => 2.2046,
		)
	    ),
	    'Distance' => array(
		'base' => 'km',
		'conv' => array(
		    'm' => 1000,
		    'cm' => 100000,
		    'mm' => 1000000,
		    'in' => 39370,
		    'ft' => 3280.8,
		    'yd' => 1093.6
		)
	    )
	);

	while (list( $key, $val ) = each($conversions)) {
	    $this->addConversion($val['base'], $val['conv']);
	}
    }

    /**
     * Constructor. Initializes the UnitConvertor object with the most important
     * properties.
     *
     * @param string  decimal point character
     * @param string  thousand separator character
     * @return   void
     * @access   public
     */
    function UnitConvertor($dec_point = '.', $thousand_sep = '')
    {
	$this->decimal_point = $dec_point;
	$this->thousand_separator = $thousand_sep;
    }

    /**
     * Adds a conversion ratio to the conversion table.
     *
     * @param string  the name of unit from which to convert
     * @param array   array(
     *       "pound"=>array("ratio"=>'', "offset"=>'')
     *        )
     *        "pound" - name of unit to set conversion ration to
     *        "ratio" - 'double' conversion ratio which, when
     *        multiplied by the number of $from_unit units produces
     *        the result
     *        "offset" - an offset from 0 which will be added to
     *        the result when converting (needed for temperature
     *        conversions and defaults to 0).
     * @return   boolean   true if successful, false otherwise
     * @access   public
     */
    function addConversion($from_unit, $to_array)
    {
	if (!isset($this->conversion_table[$from_unit])) {
	    while (list( $key, $val ) = each($to_array)) {
		if (strstr($key, '/')) {
		    $to_units = explode('/', $key);
		    foreach ($to_units as $to_unit) {
			$this->bases[$from_unit][] = $to_unit;

			if (!is_array($val)) {
			    $this->conversion_table[$from_unit . "_" . $to_unit] = array("ratio" => $val, "offset" => 0);
			} else {
			    $this->conversion_table[$from_unit . "_" . $to_unit] = array(
				"ratio" => $val['ratio'],
				"offset" => ( isset($val['offset']) ? $val['offset'] : 0 )
			    );
			}
		    }
		} else {
		    $this->bases[$from_unit][] = $key;

		    if (!is_array($val)) {
			$this->conversion_table[$from_unit . "_" . $key] = array("ratio" => $val, "offset" => 0);
		    } else {
			$this->conversion_table[$from_unit . "_" . $key] = array(
			    "ratio" => $val['ratio'],
			    "offset" => ( isset($val['offset']) ? $val['offset'] : 0 )
			);
		    }
		}
	    }
	    return true;
	}
	return false;
    }

    /**
     * Converts from one unit to another using specified precision.
     *
     * @param double  value to convert
     * @param string  name of the source unit from which to convert
     * @param string  name of the target unit to which we are converting
     * @param integer double precision of the end result
     * @return   void
     * @access   public
     */
    function convert($value, $from_unit, $to_unit, $precision)
    {
	if ($this->getConvSpecs($from_unit, $to_unit, $value, $converted)) {
	    if (!empty($converted)) {
		return number_format($converted, (int) $precision, $this->decimal_point, $this->thousand_separator);
	    } else {
		return "";
	    }
	} else {
	    return false;
	}
    }

    /**
     * CVH : changed this Function getConvSpecs in order to have it look up
     * intermediary Conversions from the
     * "base" unit being that one that has the highest hierarchical order in one
     * "logical" Conversion_Array
     * when taking $conv->addConversion('km',
     * array('meter'=>1000, 'dmeter'=>10000, 'centimeter'=>100000,
     * 'millimeter'=>1000000, 'mile'=>0.62137, 'naut.mile'=>0.53996,
     * 'inch(es)/zoll'=>39370, 'ft/foot/feet'=>3280.8, 'yd/yard'=>1093.6));
     * "km" would be the logical base unit for all units of dinstance, thus,
     * if the function fails to find a direct or reverse conversion in the table
     * it is only logical to suspect that if there is a chance
     * converting the value it only is via the "base" unit, and so
     * there is not even a need for a recursive search keeping the perfomance
     * acceptable and the ressource small...
     *
     * CVH check_key checks for a key in the Conversiontable and returns a value
     */
    function check_key($key)
    {
	if (array_key_exists($key, $this->conversion_table)) {
	    if (!empty($this->conversion_table[$key])) {
		return $this->conversion_table[$key];
	    }
	}
	return false;
    }

    /**
     * Key function. Finds the conversion ratio and offset from one unit to another.
     *
     * @param string  name of the source unit from which to convert
     * @param string  name of the target unit to which we are converting
     * @param double  conversion ratio found. Returned by reference.
     * @param double  offset which needs to be added (or subtracted, if negative)
     *                     to the result to convert correctly.
     *                     For temperature or some scientific conversions,
     *        i.e. Fahrenheit -> Celcius
     * @return   boolean   true if ratio and offset are found for the supplied
     *        units, false otherwise
     * @access   private
     */
    function getConvSpecs($from_unit, $to_unit, $value, &$converted)
    {
	$key = $from_unit . "_" . $to_unit;
	$revkey = $to_unit . "_" . $from_unit;
	$found = false;
	if ($ct_arr = $this->check_key($key)) {
	    // Conversion Specs found directly
	    $ratio = (double) $ct_arr['ratio'];
	    $offset = $ct_arr['offset'];
	    $converted = (double) ( ( $value * $ratio ) + $offset );

	    return true;
	}  // not found in direct order, try reverse order
	elseif ($ct_arr = $this->check_key($revkey)) {
	    $ratio = (double) ( 1 / $ct_arr['ratio'] );
	    $offset = -$ct_arr['offset'];
	    $converted = (double) ( ( $value + $offset ) * $ratio );

	    return true;
	} // not found test for intermediary conversion
	else {
	    // return ratio = 1 if keyparts match
	    if ($key == $revkey) {
		$ratio = 1;
		$offset = 0;
		$converted = $value;
		return true;
	    }
	    // otherwise search intermediary
	    reset($this->conversion_table);
	    while (list( $convk, $i1_value ) = each($this->conversion_table)) {
		// split the key into parts
		$keyparts = preg_split("/_/", $convk);
		// return ratio = 1 if keyparts match
		// Now test if either part matches the from or to unit
		if ($keyparts[1] == $to_unit && ( $i2_value = $this->check_key($keyparts[0] . "_" . $from_unit) )) {
		    // an intermediary $keyparts[0] was found
		    // now let us put things together intermediary 1 and 2
		    $converted = (double) ( ( ( ( $value - $i2_value['offset'] ) / $i2_value['ratio'] ) * $i1_value['ratio'] ) + $i1_value['offset'] );

		    $found = true;
		} elseif ($keyparts[1] == $from_unit && ( $i2_value = $this->check_key($keyparts[0] . "_" . $to_unit) )) {
		    // an intermediary $keyparts[0] was found
		    // now let us put things together intermediary 2 and 1
		    $converted = (double) ( ( ( ( $value - $i1_value['offset'] ) / $i1_value['ratio'] ) + $i2_value['offset'] ) * $i2_value['ratio'] );

		    $found = true;
		}
	    }
	    return $found;
	}
    }

}

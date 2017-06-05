<?php

if (!empty($placeholder)) {
    if ((isset($selectedValue) || !isset($fields[$selectedValue])) || count($fields) > 1) {
        echo '<option value="" selected="selected">'.$placeholder.'</option>';
    } else {
        echo '<option value="">'.$placeholder.'</option>';
    }
}

if (!empty($fields)) {
    foreach ($fields as $value) {
        if (is_array($value) && isset($value['nice_contact'])) {
            $value = $value['nice_contact'];
        }

        if (count($fields) === 0 || (isset($selectedValue) && $selectedValue !== '' && $selectedValue === $value)) {
            echo '<option value="'.$value.'" selected="selected">'.$value.'</option>';
        } else {
            echo '<option value="'.$value.'">'.$value.'</option>';
        }
    }
}

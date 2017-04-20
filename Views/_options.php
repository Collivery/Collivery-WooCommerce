<?php

if (!empty($placeholder)) {
    if ((isset($selectedValue) || !isset($fields[$selectedValue])) || count($fields) > 1) {
        echo '<option selected="selected">'.$placeholder.'</option>';
    } else {
        echo '<option>'.$placeholder.'</option>';
    }
}

if (!empty($fields)) {
    foreach ($fields as $value) {
        if (count($fields) === 0 || (isset($selectedValue) && $selectedValue !== '' && $selectedValue === $value)) {
            echo '<option value="'.$value.'" selected="selected">'.$value.'</option>';
        } else {
            echo '<option value="'.$value.'">'.$value.'</option>';
        }
    }
}

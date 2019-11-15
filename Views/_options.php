<?php

if (!empty($placeholder)) {
    if ((!isset($selectedValue) || !isset($fields[$selectedValue])) || count($fields) > 1) {
        echo '<option value="" selected="selected">'.$placeholder.'</option>';
    } else {
        echo '<option value="">'.$placeholder.'</option>';
    }
}

if (!empty($fields)) {
    foreach ($fields as $value => $text) {
        if (is_array($text) && isset($text['nice_contact'])) {
            $value = $text['contact_id'];
            $text = $text['nice_contact'];
        }

        if ((isset($selectedValue) && $selectedValue !== '' && $selectedValue === $value)) {
            echo '<option value="'.$value.'" selected="selected">'.$text.'</option>';
        } else {
            echo '<option value="'.$value.'">'.$text.'</option>';
        }
    }
}

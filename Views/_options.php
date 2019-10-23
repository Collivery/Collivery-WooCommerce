<?php

if (!empty($placeholder)) {
    if ((isset($selectedValue) || !isset($fields[$selectedValue])) || count($fields) > 1) {
        echo '<option value="" selected="selected">'.$placeholder.'</option>';
    } else {
        echo '<option value="">'.$placeholder.'</option>';
    }
}

if (!empty($fields)) {
    foreach ($fields as $field) {
        if (is_array($field) && isset($field['nice_contact'])) {
            $value = $field['contact_id'];
            $text = $field['nice_contact'];
        } else {
            $text = $value = $field;
        }

        if (count($fields) === 0 || (isset($selectedValue) && $selectedValue !== '' && $selectedValue === $value)) {
            echo '<option value="'.$value.'" selected="selected">'.$text.'</option>';
        } else {
            echo '<option value="'.$value.'">'.$text.'</option>';
        }
    }
}

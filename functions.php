<?php

function get_elem_pos($arr, $elem)
{
    $counter = 0;

    foreach ($arr as $num) {
        if ($num > $elem) break;
        $counter++;
    }

    return $counter;
}
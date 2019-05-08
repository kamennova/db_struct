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

function array_prepend(&$arr, $elems)
{
    for ($i = count($elems) - 1; $i >= 0; $i--) {
        array_unshift($arr, $elems[$i]);
    }
}

function array_append(&$arr, $elems){
    for ($i = 0, $num = count($elems); $i < $num; $i++) {
        array_push($arr, $elems[$i]);
    }
}
<?php

function nth_key($arr, $index)
{
    return array_keys($arr)[$index];
}

function key_uniform_search($arr, $key)
{
    $g = count($arr);
    $index = $g;

    return uniform_search_step($arr, $key, $g, $index, 0);
}

function uniform_search_step($arr, $key, $g, $index, $koef)
{
    $g = floor($g / 2) || 1;
    $index = $index + $koef * $g;

    $index_key = nth_key($arr, $index);
    if ($index_key == $key) return $arr[$key];

    $koef = $index_key < $key ? -1 : 1;

    return uniform_search_step($arr, $key, $g, $index, $koef);
}
<?php

function uniform_search($arr, $key)
{
    $step = count($arr);
    $index = -1;

    return uniform_search_step($arr, $key, $step, $index, 1);
}

function uniform_search_step($arr, $key, $step, $index, $koef)
{
    $step = ceil($step / 2);
    if ($step == 0) return false;

    $index += $koef * $step;
    $index_key = $arr[$index];

    if ($index_key == $key) return $index;

    $koef = $index_key < $key ? 1 : -1;

    return uniform_search_step($arr, $key, $step, $index, $koef);
}
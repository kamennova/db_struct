<?php

require_once "UniformSearch.php";
require_once "Database.php";


$db = new Database('db.txt', 3, [
    1 => 'new record',
    45 => 'dataada',
    123 => 'new',
    120 => 'stupid row',
    99 => 'lol',
    2 => 'data',
    100 => 'one more row',

    6 => 'bcbxf',
    4 => 'tree',

    7 => 'inserted',
]);

/*
 // Uniform search tests
$arr = [0 => 'some data', 1 => 'user data', 2 => 'gfgfgf', 12 => 'fdfdfdfd', 19 => 'fdfdfd'];
echo key_uniform_search($arr, 2);
*/
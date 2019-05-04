<?php

require_once "UniformSearch.php";
require_once "BTree.php";

/*
 // Uniform search tests
$arr = [0 => 'some data', 1 => 'user data', 2 => 'gfgfgf', 12 => 'fdfdfdfd', 19 => 'fdfdfd'];
echo key_uniform_search($arr, 2);
*/

// BTree tests
$tree = new BTree(2);
$tree->insert(7, 'data');
$tree->insert(15, 'some data');
$tree->insert(20, 'new data');
$tree->insert(45, 'data row');
$tree->insert(17, 'data');
$tree->insert(29, 'some data');
$tree->insert(2, 'data');
$tree->insert(10, 'some data');

echo $tree->find_by_key(10);
$tree->edit_data_by_key(10, 'edited');
echo $tree->find_by_key(10);

$tree->insert(55, 'data row');
$tree->insert(4, 'data');
$tree->insert(3, 'some data');
$tree->insert(70, 'data');
$tree->insert(80, 'some data');
<?php

/**
 * Class BNode
 *
 * $keys = [
 *      $key => $data
 * ]
 *
 * $children = [
 *      BNode
 * ]
 *
 * $parent = BNode
 *
 */
class BNode
{
    public $keys;
    public $children;
    public $parent;

    function __construct($b_keys = null, $b_children = null, $b_parent = null)
    {
        if ($b_keys) {
            $this->keys = $b_keys;
        }

        $this->children = $b_children;
        $this->parent = $b_parent;
    }

//    ---

    function is_leaf()
    {
        return ($this->children == null || count($this->children) == 0);
    }

    function is_root()
    {
        return $this->parent == null;
    }


    function get_nth_data_row($index)
    {
        $i = 0;
        foreach ($this->keys as $data) {
            if ($i == $index) return $data;
            $i++;
        }

        return false;
    }

    function get_nth_key($index)
    {
        $i = 0;
        foreach ($this->keys as $key => $data) {
            if ($i == $index) return $key;
            $i++;
        }

        return false;
    }

    function insert_child($child)
    {
        if (!$this->children) {
            $this->children = $child;
        } else {
            $pos = 0;
            $child_data = $child->get_nth_key(0);
            foreach ($this->keys as $key => $data) {
                if ($key > $child_data)
                    break;
                $pos++;
            }

            array_splice($this->children, $pos, 0, [$child]);
        }
    }

//    ---

    function node_output($recursion = 0)
    {
        if ($recursion > 1) return;

        echo "{\nData [ ";

        if ($this->keys) {
            foreach ($this->keys as $key => $val) {
                echo $key . " => " . $val . ", ";
            }
        }

        echo "]\nChildren: \n";

        if ($this->children) {
            foreach ($this->children as $child) {
                $child->node_output($recursion + 1);
            }
        }

        if ($this->parent) {
            echo "-----\nParent: \n";
            $this->parent->node_output($recursion + 1);
        }

        echo "}\n";
    }

    function short_node_output()
    {
        echo "Data [ ";

        if ($this->keys) {
            foreach ($this->keys as $key => $val) {
                echo $key . " => " . $val . ", ";
            }
        }

        echo "]\n";
    }
}

class BTree
{
    public $t;
    public $root;

    function __construct($t = 500)
    {
        $this->t = $t;
        $this->root = new BNode();

        echo "Min node len: " . ($t - 1) . ", max: " . (2 * $t - 1) . "\n";
    }

    function max_node_len()
    {
        return $this->t * 2 - 1;
    }

    function output()
    {
        $this->output_step($this->root);
    }

    function output_step($node)
    {
        echo "{ \n";
        $node->short_node_output();
        if ($node->children) {
            echo "Children: \n";

            foreach ($node->children as $child) {
                $child->short_node_output();
            }
        }

        echo "\n}\n";
    }

//    ---

    function insert($key, $data)
    {
//        echo "=============================\n";
//        echo "Inserting " . $key . " => " . $data . "\n";
        $accepting_node = $this->descend_to_leaf($this->root, $key);
        $accepting_node->keys[$key] = $data;
        ksort($accepting_node->keys);
        $this->repair_node($accepting_node);
    }

    function descend_to_leaf($node, $key)
    {
        if ($node->is_leaf())
            return $node;

        for ($i = 0, $num = count($node->keys); $i < $num; $i++) {
            $curr = $node->get_nth_key($i);

            if ($key < $curr) break;
        }

        return $this->descend_to_leaf($node->children[$i], $key);
    }

    function repair_node($node)
    {
        if (count($node->keys) <= $this->max_node_len()) {
//            echo "Repair not needed \n";
            return;
        }

//        echo "Repairing tree \n";
        $this->split_node($node);
    }

    function split_node($node)
    {
        $l_node_len = $this->t - 1;

        // moving median node cell to parent node
        $parent_key = $node->get_nth_key($l_node_len);
        $parent_data = $node->get_nth_data_row($l_node_len);

        // creating right node
        $r_node_keys = array_slice($node->keys, $l_node_len + 1, null, true);
        $r_node_children = $node->children ? array_slice($node->children, $l_node_len + 1) : null;
        $r_node = new BNode($r_node_keys, $r_node_children, $node->parent);

        // deleting right node properties from old node
        $l_node_keys = array_slice($node->keys, 0, $l_node_len, true);
        $l_node_children = $node->children ? array_slice($node->children, 0, $l_node_len + 1) : null;

        $node->keys = $l_node_keys;
        $node->children = $l_node_children;

        if ($node->is_root()) {
            // create new root node
            $new_root = new BNode([$parent_key => $parent_data], [$node, $r_node]);
            $this->root = $new_root;

            $node->parent = $new_root;
            $r_node->parent = $new_root;
        } else {
            $node->parent->keys[$parent_key] = $parent_data;
            ksort($node->parent->keys);

            $node->parent->insert_child($r_node);
            ksort($node->keys);
            $this->repair_node($node->parent);
        }
    }

//    ---

    function find_by_key($key)
    {
        $location = $this->descend_to_leaf($this->root, $key);

        if (!isset($location->keys[$key])) return false;

        return $location->keys[$key];
    }

    function edit_data_by_key($key, $new_data)
    {
        $location = $this->descend_to_leaf($this->root, $key);

        if (!isset($location->keys[$key])) return false;

        $location->keys[$key] = $new_data;
        return true;
    }
}
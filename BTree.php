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
        if ($this->children) {
            foreach ($this->children as $child) {
                $child->parent = $this;
            }
        }

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

    function get_extreme_key($min)
    {
        if ($min == true) return $this->get_nth_key(0);

        return $this->get_nth_key(count($this->keys) - 1);
    }

    function get_sibling(&$is_left = true)
    {
        // root or only-child node doesn't have siblings
        if ($this->is_root()) return false;

        $siblings_num = count($this->parent->children);
        if ($siblings_num < 2) return false;

        $pos = $this->get_parent_child_pos();
        echo "Pos" . $pos . "\n";

        if ($pos === $siblings_num - 1) { // this node is the last on the right
            $is_left = false;
            return $this->parent->children[$pos - 1];
        }

        return $this->parent->children[$pos + 1];
    }

    function get_parent_child_pos()
    {
        for ($i = 0, $num = count($this->parent->children); $i < $num; $i++) {
//            var_dump($this->parent->children[$i]);
            if ($this == $this->parent->children[$i]) return $i;
        }

        return false;
    }

    function get_nth_data_value($index)
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

    function del_data_cell($key)
    {
        unset($this->keys[$key]);
    }

//---

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

    function min_node_len()
    {
//        return $this->t - 1; // todo temporary
        return 2;
    }

    function max_node_len()
    {
        return $this->t * 2 - 1;
    }

//    ---

    function build_up($data_arr)
    {
        foreach ($data_arr as $key => $data) {
            $this->insert($key, $data);
        }
    }

//    ---

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

        // creating median node cell to move to parent node
        $parent_key = $node->get_nth_key($l_node_len);
        $parent_data = $node->get_nth_data_value($l_node_len);

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

            $node->parent = $this->root;
            $r_node->parent = $this->root;
        } else {
            $node->parent->keys[$parent_key] = $parent_data;
            ksort($node->parent->keys);

            $node->parent->insert_child($r_node);
            ksort($node->keys);
            $this->repair_node($node->parent);
        }
    }

//    ---

    function delete($key)
    {
        $del_location = $this->find_node($key);
        if (!$del_location) {
            echo "Key not found\n";
            return false;
        }

        if ($del_location->is_leaf()) {
            if (count($del_location->keys) >= $this->min_node_len() + 1) {
                $del_location->del_data_cell($key);
            } else {
                echo "Case 2\n";
                $is_left = true;
                $sibling = $del_location->get_sibling($is_left);

                if ($sibling) {
                    $parent = $del_location->parent;

                    // getting key to swap in parent
                    $pos = $del_location->get_parent_child_pos();
                    $swap_pos = $pos;

                    if ($pos === count($parent->keys)) { // todo use is_left
                        $swap_pos--;
                    }

                    $p_swap_key = $parent->get_nth_key($swap_pos);
                    $p_swap_val = $parent->keys[$p_swap_key];

                    if (count($sibling->keys) - 1 >= $this->min_node_len()) {

                        // getting data row before swapping
                        $swap_key = $sibling->get_extreme_key($is_left);
                        $swap_val = $sibling->keys[$swap_key];

                        echo "Swap key " . $swap_key . "\n";

                        $sibling->del_data_cell($swap_key);

                        unset($parent->keys[$p_swap_key]);
                        $del_location->parent->keys[$swap_key] = $swap_val;
                        ksort($parent->keys);

                        $parent->short_node_output();

                        $del_location->del_data_cell($key);
                        $del_location->keys[$p_swap_key] = $p_swap_val;
                        ksort($del_location->keys);
                    } else {
                        // move parent down
                        // merge 2 nodes
                        // delete children

//                        $p_desc = $parent->get_nth_key();
                        unset($parent->keys[$swap_pos]);
                        unset($parent->children[$swap_pos]);
                        array_values($parent->children);

                        $del_location->keys[$p_swap_key] = $p_swap_val;
                        ksort($del_location->keys);
                    }
                }

            }
        }
    }

    function del_case2($node, $key)
    {

    }

//    ----

    function find_node($key)
    {
        return $this->find_node_step($this->root, $key);
    }

    function find_node_step($node, $del_key)
    {
        $counter = 0;
        foreach ($node->keys as $node_key => $data) {
            if ($node_key > $del_key) {
                break;
//                return $this->find_node_step($node->children[$counter], $del_key);
            } else if ($node_key == $del_key) { // found
                return $node;
            }

            $counter++;
        }

        if ($node->is_leaf()) {
            return false;
        }

        return $this->find_node_step($node->children[$counter], $del_key);
    }

//    ---

    function find_by_key($key)
    {
        $location = $this->find_node($key);

        if (!$location) return false;

        return $location->keys[$key];
    }

    function edit_data_by_key($key, $new_data)
    {
        $location = $this->find_node($key);

        if (!$location) return false;

        $location->keys[$key] = $new_data;
        return true;
    }
}
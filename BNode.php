<?php

/**
 * Class BNode
 *
 * @property array $children
 *       $children = [BNode]
 * @property array $keys
 * @property BNode $parent
 *
 * @property int $pos
 * @property int $parent_pos
 * @property array $children_pos
 * @property array $values_pos
 */
class BNode
{
    public $pos;
    public $children;
    public $parent;

    // --- node line ---
    public $flag;
    public $parent_pos;
    public $keys;
    public $values_pos;
    public $children_pos;

    // --- entry line ---
    public $values;

    function __construct($pos, $keys = null, $parent_pos = null, $children_pos = null,
                         $values_pos = null, $parent = null, $children = null)
    {
        $this->pos = $pos;
        $this->parent_pos = $parent_pos;
        $this->keys = $keys;
        $this->children_pos = $children_pos;

        $this->children = $children;
        $this->values_pos = $values_pos;
        /* if ($this->children) {
            foreach ($this->children as $child) {
                $child->parent = $this;
            }
        }*/

        $this->parent = $parent;
    }

//    ---

    function is_leaf()
    {
        return ($this->children_pos == null || count($this->children_pos) == 0);
    }

    function is_root()
    {
        return $this->parent_pos == null;
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

        /**
         * @var BNode $parent
         */
        $parent = $this->parent;
        $siblings_num = count($parent->children);
        if ($siblings_num < 2) return false;

        $pos = $this->get_parent_child_pos();

        if ($pos === $siblings_num - 1) { // this node is the last on the right
            $is_left = false;
            return $parent->children[$pos - 1];
        }

        return $parent->children[$pos + 1];
    }

    function get_parent_child_pos($parent)
    {
        for ($i = 0, $num = count($parent->children_pos); $i < $num; $i++) {
            if ($this->pos == $parent->children_pos[$i]) return $i;
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

    function get_nth_value_pos($index)
    {
        $i = 0;
        foreach ($this->values_pos as $pos) {
            if ($i == $index) return $pos;
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

    function get_cell_pos($search_key)
    {
        $counter = 0;
        foreach ($this->keys as $key) {
            if ($key == $search_key) {
                return $counter;
            }
            $counter++;
        }
        return false;
    }

    /**
     * @param BNode $child
     * @return int $pos
     */
    function insert_child($child)
    {
        $pos = 0;

        if (!$this->children) {
            $this->children = $child;
        } else {
            $child_data = $child->get_nth_key(0);
            foreach ($this->keys as $key => $data) {
                if ($key > $child_data)
                    break;
                $pos++;
            }

            array_splice($this->children, $pos, 0, [$child]);
        }

        return $pos;
    }

    function insert_data_cell()
    {

    }

    function merge_leaves($node, $min = true)
    {
        foreach ($node->keys as $key => $val) {
            $this->keys[$key] = $val;
        }

        if ($min) {
            ksort($this->keys);
        }
    }

    /**
     * @param BNode $node
     * @param bool $smaller
     */
    function merge_sibling_in($node, $smaller)
    {
        foreach ($node->keys as $key => $val) {
            $this->keys[$key] = $val;
        }

        if ($smaller) {
            ksort($this->keys);
        }

        $push_func = $smaller ? "array_unshift" : "array_push";

        if ($node->children) {
            $num = count($node->children);

            if ($smaller) {
                for ($i = $num - 1; $i >= 0; $i--) {
                    $child = $node->children[$i];
                    $child->parent = $this;
                    $push_func($this->children, $child);
                }
            } else {
                for ($i = 0; $i < $num; $i++) {
                    $child = $node->children[$i];
                    $child->parent = $this;
                    $push_func($this->children, $child);
                }
            }
        }

        $sib_pos = $node->get_parent_child_pos();
        unset($node->parent->children[$sib_pos]);
        array_splice($node->parent->children, 0, 0);

        unset($node);
    }

    function add_data_cell($key, $value_pos){
        $this->keys [] = $key;
        sort($this->keys);
        $key_pos = $this->get_cell_pos($key);
        array_splice($this->values_pos, $key_pos, 0, $value_pos);
    }

//    ---

    function delete_data_cell($key)
    {
        unset($this->keys[$key]);
    }

//    --

    function update_parent_pos()
    {

    }
}
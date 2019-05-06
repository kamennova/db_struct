<?php

/**
 * Class BNode
 *
 * @property array $children
 *       $children = [BNode]
 * @property array $keys
 *      $keys = [
 *              key => data
 *              ]
 * @property BNode $parent
 *
 * @property int $pos
 * @property int $parent_pos
 * @property array $children_pos
 */
class BNode
{
    public $keys;
    public $values_pos;
    public $children;
    public $parent;

    public $pos;
    public $parent_pos;
    public $children_pos;

    function __construct($pos, $b_keys = null, $b_children = null, $b_parent = null)
    {
        $this->pos = $pos;

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
        return ($this->children_pos == null || count($this->children_pos) == 0);
//        return ($this->children == null || count($this->children) == 0);
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

    function get_parent_child_pos()
    {
        for ($i = 0, $num = count($this->parent->children); $i < $num; $i++) {
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
        foreach ($this->keys as $key => $val) {
            if ($key == $search_key) {
                return $counter;
            }
            $counter++;
        }
        return false;
    }

    /**
     * @param BNode $child
     */
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

//    ---

    function delete_data_cell($key)
    {
        unset($this->keys[$key]);
    }

//    --

    function update_parent_pos(){

    }
}
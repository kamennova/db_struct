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
        $this->values_pos = $values_pos;
        $this->children_pos = $children_pos;

        $this->children = $children;
        $this->parent = $parent;
    }

//    ---

    function is_leaf()
    {
        return $this->children_pos == null || count($this->children_pos) == 0;
    }

    function is_root()
    {
        return $this->parent_pos == null;
    }

    function get_extreme_key_pos($min)
    {
        if ($min == true) return 0;

        return count($this->keys) - 1;
    }

    /**
     * @param BNode $parent
     * @param bool $is_right if immediate sibling is on the right
     * @param bool $get_left return left sibling (returns right by default)
     * @return bool|mixed
     */
    function get_sibling_pos($parent, &$is_right = true, $get_left = false)
    {
        // root or only-child node doesn't have siblings
        if ($this->is_root()) return false; // todo remove??

        $children_num = count($parent->children_pos);
        if ($children_num == 1) return false;

        $pos = $this->get_parent_child_pos($parent);

        if ($pos === $children_num - 1) { // this node is the last on the right
            $is_right = false;
        }

        if (!$is_right || $get_left) {
            if ($pos == 0) {
                return false;
            }

            return $parent->children_pos[$pos - 1];
        }

        return $parent->children_pos[$pos + 1];
    }

    function get_parent_child_pos($parent)
    {
        for ($i = 0, $num = count($parent->children_pos); $i < $num; $i++) {
            if ($this->pos == $parent->children_pos[$i]) return $i;
        }

        return false;
    }

    function get_cell_pos($search_key) // todo uniform search
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
     * @param BNode $node
     * @param bool $is_left if $node is on the left
     */
    function merge_sibling_in($node, $is_left)
    {
        $push_func = $is_left ? "array_prepend" : "array_append";

        $push_func($this->keys, $node->keys);
        $push_func($this->values_pos, $node->values_pos);


        if ($node->children_pos) {
            $push_func($this->children_pos, $node->children_pos);
        }
    }

    function add_data_cell($key, $value_pos)
    {
        $this->keys [] = $key;
        sort($this->keys);
        $key_pos = $this->get_cell_pos($key);
        array_splice($this->values_pos, $key_pos, 0, $value_pos);
    }

//    ---

    function delete_data_cell($key, $child_pos = null)
    {
        $pos = $this->get_cell_pos($key);
        unset($this->keys[$pos]);
        unset($this->values_pos[$pos]);

        if(!is_null($child_pos)){
            unset($this->children_pos[$child_pos]);
//            array_splice($this->children, 0, 0);
        }
    }
}
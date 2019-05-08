<?php

require_once "BNode.php";
require_once "DBLineFormatter.php";
require_once "FileEditor.php";

/**
 * Class BTree provides structure for efficient data storing
 *
 * @property int $t
 * @property BNode $root
 *
 * @property int $last_node_pos;
 * @property int $last_data_pos
 * @property int $root_pos
 *
 */
class BTree
{
    public $t;
    public $root;

//    --- db modifications ---

    public $last_data_pos;
    public $last_node_pos;

    public $root_pos;

    public $data_editor;
    public $nodes_editor;

    public $formatter;

    /**
     * BTree constructor.
     * @param int $t
     * @param FileEditor $data_editor
     * @param FileEditor $nodes_editor
     * @param bool $is_empty
     * @param int $root_pos
     */
    function __construct($t, $data_editor, $nodes_editor, $is_empty = false, $root_pos = 1)
    {
        $this->t = $t;

        $this->formatter = new DBLineFormatter($this->t);

        $this->data_editor = $data_editor;
        $this->data_editor->line_length = $this->formatter->entry_length();

        $this->nodes_editor = $nodes_editor;
        $this->nodes_editor->line_length = $this->formatter->node_length();

        if ($is_empty) {
            $root_pos = 0;
            $this->last_node_pos = 0;
            $this->last_data_pos = 0;
            $this->insert_empty_node(); // inserting empty root node line
        }

        $this->root_pos = $root_pos;

//        $this->root = // todo store root in cache
    }

    function min_node_len()
    {
        return $this->t - 1;
//        return 2;// todo temporary
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

    /**
     * @param $key
     * @param $data
     */
    function insert($key, $data)
    {
        $root = $this->get_node($this->root_pos, false);

        /** @var BNode $accepting_node
         */
        $accepting_node = $this->descend_to_leaf($root, $key);

        $is_first_record = ($accepting_node === $root) && (!$root->keys || count($root->keys) == 0);
        $this->add_data_entry($data, $is_first_record); // entry position is $this->>last_data_pos

        $accepting_node->add_data_cell($key, $this->last_data_pos);
        $this->add_data_cell($accepting_node, $key, $this->last_data_pos);
        $this->repair_node($accepting_node);
    }

    function insert_empty_node()
    {
        $str = $this->formatter->new_node_str();
        $this->nodes_editor->first_write($str);
    }

    function get_node($line_pos, $get_data = false)
    {
        $str = $this->nodes_editor->get_line($line_pos);

        return $this->formatter->get_node($str, $line_pos, $get_data);
    }

    /**
     * @param BNode $node
     * @param int $key
     * @return mixed
     */
    function descend_to_leaf($node, $key)
    {
        if ($node->is_leaf())
            return $node;

        for ($i = 0, $num = count($node->keys); $i < $num; $i++) {
            $curr = $node->keys[$i];

            if ($key < $curr) break;
        }

        $child = $this->get_node($node->children_pos[$i], false);
        return $this->descend_to_leaf($child, $key);
    }

    function repair_node($node)
    {
        if (count($node->keys) <= $this->max_node_len()) {
            return;
        }

        $this->split_node($node);
    }

    /**
     * @param BNode $node
     */
    function split_node($node)
    {
        $l_node_len = $this->t - 1;

        // creating median node cell to move to parent node
        $median_key = $node->keys[$l_node_len];
        $median_value_pos = $node->values_pos[$l_node_len];

        // creating right node
        $r_node_keys = array_slice($node->keys, $l_node_len + 1);
        $r_node_values_pos = array_slice($node->values_pos, $l_node_len + 1);
        $r_node_children_pos = $node->children_pos ? array_slice($node->children_pos, $l_node_len + 1) : null;
        $r_node = $this->new_node($r_node_keys, $r_node_children_pos, $node->parent_pos, $r_node_values_pos);

        // deleting right node properties from old node
        $l_node_keys = array_slice($node->keys, 0, $l_node_len, true);
        $l_node_values_pos = array_slice($node->values_pos, 0, $l_node_len, true);
        $l_node_children_pos = $node->children_pos ? array_slice($node->children_pos, 0, $l_node_len + 1) : null;

        $node->keys = $l_node_keys;
        $node->values_pos = $l_node_values_pos;
        $node->children_pos = $l_node_children_pos;
        $this->update_node_str($node); // todo

        if ($node->is_root()) { // create new root node
            $this->new_node([$median_key], [$node->pos, $r_node->pos], null, [$median_value_pos]);
            $this->root_pos = $this->last_node_pos;
        } else {
            $parent = $this->get_node($node->parent_pos, false);

            $child_pos = $node->get_parent_child_pos($parent) + 1;
            array_splice($parent->children_pos, $child_pos, 0, $r_node->pos);
            $parent->add_data_cell($median_key, $median_value_pos);

            if (count($parent->keys) > $this->max_node_len()) {
                $this->repair_node($parent);
            } else {
                $this->update_node_str($parent);
            }
        }
    }

    /**
     * @param $keys
     * @param $children_pos
     * @param $parent_pos
     * @param $values_pos
     * @return BNode
     */
    function new_node($keys, $children_pos, $parent_pos, $values_pos)
    {
        $str = $this->formatter->new_node_str('', $parent_pos, $keys, $values_pos, $children_pos);
        $this->last_node_pos++; // position of the node to be inserted
        $this->nodes_editor->write($str, $this->last_node_pos);

        // update children parent
        if ($children_pos) {
            $parent = $this->formatter->get_pos_str($this->last_node_pos);
            $parent_start = $this->formatter->get_start_pos('parent_pos');

            foreach ($children_pos as $pos) {
                $this->nodes_editor->write($parent, $pos, $parent_start);
            }
        }

        return new BNode($this->last_node_pos, $keys, $parent_pos, $children_pos, $values_pos);
    }

//    ---

    function delete($key)
    {
        $del_location = $this->find_node($key);
        if (!$del_location) {
            echo "Key not found\n";
            return false;
        }

        return $this->delete_from_node($del_location, $key);
    }


    /**
     * @param BNode $node
     * @param int $key
     * @return boolean
     */
    function delete_from_node($node, $key)
    {
        if ($node->is_leaf()) {
            $this->delete_data_cell($node, $key);
            return $this->repair_deleted($node);
        } else {
            return $this->delete_from_internal($node, $key);
        }
    }

    /**
     * @param BNode $node
     * @return bool
     */
    function repair_deleted($node)
    {
        if (count($node->keys) < $this->min_node_len() && !$node->is_root()) {
            return $this->rebalance($node);
        }

        return true;
    }

    /**
     * @param BNode $node
     * @return bool
     */
    function rebalance($node)
    {
        $is_right = true; // if node's immediate sibling is on the right
        echo 'case2';

        /**
         * @var BNode $parent
         */
        $parent = $this->get_node($node->parent_pos, false);
        $node->parent = $parent;

        /**
         * @var BNode $sibling
         */
        $sibling = $this->get_node($node->get_sibling_pos($parent, $is_right));

        if ($sibling) {

            /* position of key to swap in parent
              (the key is on the right unless the $del_location is the last child) */
            $p_child_pos = $node->get_parent_child_pos($parent); // todo optimize??

            $p_swap_pos = $p_child_pos;
            if (!$is_right) {
                $p_swap_pos--;
            }

            if (count($sibling->keys) >= $this->min_node_len() + 1) {

                return $this->rotate($node, $sibling, $is_right, $p_swap_pos);

            } else if ($is_right && $other_sib_pos = $node->get_sibling_pos($parent, $is_right, true)) {

                $is_right = false;
                $p_swap_pos--;
                $other_sib = $this->get_node($other_sib_pos);
                return $this->rotate($node, $other_sib, $is_right, $p_swap_pos);

            } else { // merge to the sibling

                $p_swap_key = $parent->keys[$p_swap_pos];
                $p_swap_value_pos = $parent->values_pos[$p_swap_pos];

                // deleting p_swap_key & {$node position as child} from parent node
                $this->delete_data_cell($parent, $p_swap_key, $p_child_pos);

                // moving parent data cell to bottom
                $func = $is_right ? 'array_push' : 'array_unshift';
                $func($node->keys, $p_swap_key);
                $func($node->values_pos, $p_swap_value_pos);

                array_splice($node->keys, 0, 0);
                array_splice($node->values_pos, 0, 0);

                $sibling->merge_sibling_in($node, $is_right); // todo unset del_loc
                $this->update_node_str($sibling);
                $this->erase_node($node->pos);

                return $this->repair_deleted($parent);
            }
        } else {
            echo "OOOOOOOOOOOOOOOOOPS\n";
            return false;
        }
    }

    /**
     * @param BNode $node
     * @param BNode $sibling
     * @param $is_right
     * @param $p_swap_pos
     * @return bool
     */
    function rotate($node, $sibling, $is_right, $p_swap_pos)
    {
        $p_swap_key = $node->parent->keys[$p_swap_pos];
        $p_swap_value_pos = $node->parent->values_pos[$p_swap_pos];

        // getting data row before swapping
        $s_swap_pos = $sibling->get_extreme_key_pos($is_right);
        $s_swap_key = $sibling->keys[$s_swap_pos];
        $s_swap_value_pos = $sibling->values_pos[$s_swap_pos];

        if ($sibling->children_pos) {
            $s_child_pos = $s_swap_pos === 0 ? 0 : $s_swap_pos + 1;
        } else {
            $s_child_pos = null;
        }

        $this->delete_data_cell($sibling, $s_swap_key, $s_child_pos); // todo delete child

        // moving sibling key & value pos to parent
        $node->parent->keys[$p_swap_pos] = $s_swap_key;
        $node->parent->values_pos[$p_swap_pos] = $s_swap_value_pos;
        $this->update_node_str($node->parent);

        $func = $is_right ? 'array_push' : 'array_unshift';

        $func($node->keys, $p_swap_key); // todo get pos
        $func($node->values_pos, $p_swap_value_pos);
        $func($node->children_pos, $s_child_pos);

        $this->update_node_str($node);

        return true;
    }

    /**
     * @param BNode $node
     * @param int $key
     * @return bool
     */
    function delete_from_internal($node, $key)
    {
        $pos = $node->get_cell_pos($key);

        $left_leaf = $this->descend_to_leaf($node, $key - 1);

        $l_leaf_keys_num = count($left_leaf->keys);
        $donor = $left_leaf;
        $donor_key_pos = $l_leaf_keys_num - 1;

        /* if left leaf should get deficient after key deletion,
          check if right leaf should; if no, take it as donor */
        if ($l_leaf_keys_num < $this->min_node_len() + 1 &&
            $right_leaf = $this->descend_to_leaf($node, $key + 1)) {

            $r_leaf_keys_num = count($right_leaf->keys);
            if ($r_leaf_keys_num >= $this->min_node_len() + 1) {
                $donor = $right_leaf;
                $donor_key_pos = 0;
            }
        }

        $donor_key = $donor->keys[$donor_key_pos];

        $node->keys[$pos] = $donor_key;
        $node->values_pos[$pos] = $donor->values_pos[$donor_key_pos];
        $this->update_node_str($node); // todo optimize

        return $this->delete_from_node($donor, $donor_key);
    }

//    ----

    function find_node($key)
    {
        $root = $this->get_node($this->root_pos, false);
        return $this->find_node_step($root, $key);
    }

    /**
     * @param BNode $node
     * @param int $del_key
     * @return mixed
     */
    function find_node_step($node, $del_key)
    {
        $counter = 0;
        foreach ($node->keys as $key) {
            if ($key > $del_key) {
                break;
            } else if ($key == $del_key) { // found
                return $node;
            }

            $counter++;
        }

        if ($node->is_leaf()) {
            return false;
        }

        $child = $this->get_node($node->children_pos[$counter], false);

        return $this->find_node_step($child, $del_key);
    }

//    ---

    function get_value($key)
    {
        /**
         * @var BNode $location
         */
        $location = $this->find_node($key);

        if (!$location) return false;

        $pos = $location->get_cell_pos($key);
        $line_pos = $location->values_pos[$pos];
        $entry = $this->data_editor->get_line($line_pos);

        return $this->formatter->get_entry_value($entry);
    }

    function edit_data_by_key($key, $new_data)
    {
        /**
         * @var BNode location
         */
        $location = $this->find_node($key);

        if (!$location) return false;

        $pos = $location->get_cell_pos($key);
        $location->values[$pos] = $new_data;

        $entry = $this->formatter->new_entry_str($new_data);
        $this->data_editor->write($entry, $location->values_pos[$pos]);

        return true;
    }

//    --

    /**
     * @param BNode $node
     */
    function update_node_str($node)
    {
        $str = $this->formatter->new_node_str('', $node->parent_pos, $node->keys, $node->values_pos, $node->children_pos);
        $this->nodes_editor->write($str, $node->pos);

        if ($node->children_pos) {
            foreach ($node->children_pos as $child_pos) {
                $this->update_node_parent_pos($child_pos, $node->pos);
            }
        }
    }

    function update_node_parent_pos($node_pos, $new_pos)
    {
        $new_pos_str = $this->formatter->get_pos_str($new_pos);

        $parent_start = $this->formatter->get_parent_pos_start();
        $this->nodes_editor->write($new_pos_str, $node_pos, $parent_start);
    }

    /**
     * @param BNode $node
     * @param int $key
     * @param int $value_pos
     */
    function add_data_cell($node, $key, $value_pos)
    {
        if (count($node->keys) > $this->t * 2 - 1) return;

        // updating node key and data pos
        $str = $this->nodes_editor->get_line($node->pos);
        $this->formatter->add_node_data_cell($str, $key, $value_pos);
        $this->nodes_editor->write($str, $node->pos, false);
    }

    /**
     * Func writing new data entry to database
     * @param $data
     * @param $is_first_record
     */
    function add_data_entry($data, $is_first_record)
    {
        $new_entry = $this->formatter->new_entry_str($data);

        if ($is_first_record) {
            $this->last_data_pos = -1;
        }

        $this->last_data_pos++;
        $this->data_editor->write($new_entry, $this->last_data_pos);
    }

//    ---

    /**
     * @param BNode $node
     * @param int $key
     * @param null $child_pos
     */
    function delete_data_cell($node, $key, $child_pos = null)
    {
        $key_pos = $node->get_cell_pos($key);
        $shift_back_keys = array_slice($node->keys, $key_pos + 1);
        $shift_back_values_pos = array_slice($node->values_pos, $key_pos + 1);

        if (!is_null($child_pos)) {
            echo $child_pos;
        }

        $shift_back_children_pos = (is_null($child_pos) || $child_pos == 0)
            ? null : array_slice($node->children_pos, $child_pos + 1);

        $str = $this->nodes_editor->get_line($node->pos);
        $this->formatter->delete_data_cell($str, $key_pos, $shift_back_keys, $shift_back_values_pos, $child_pos,
            $shift_back_children_pos);
        $this->nodes_editor->write($str, $node->pos);

        $node->delete_data_cell($key, $child_pos); // todo why???
    }

    function erase_node($pos)
    {
        $empty_str = str_repeat(Database::$empty_char, $this->formatter->node_length() - 1);
        $this->nodes_editor->write($empty_str . PHP_EOL, $pos);
    }
}
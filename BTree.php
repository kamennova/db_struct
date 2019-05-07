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
        var_dump($root);

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

    function get_node($line_pos, $get_values)
    {
        $str = $this->nodes_editor->get_line($line_pos);

        return $this->formatter->get_node($str, $line_pos, $get_values);
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

            if(count($parent->keys) > $this->max_node_len()){
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
     * @param BNode $del_location
     * @param int $key
     * @return boolean
     */
    function delete_from_node($del_location, $key)
    {
        if ($del_location->is_leaf()) {
            if (count($del_location->keys) >= $this->min_node_len() + 1) {
                $del_location->delete_data_cell($key);
                return true;
            } else {
                return $this->del_case2($del_location, $key);
            }
        } else {
            return $this->del_case3($del_location, $key);
        }
    }

    /**
     * @param BNode $del_location
     * @param int $key
     * @return bool
     */
    function del_case2($del_location, $key)
    {
        $is_left = true;

        /**
         * @var BNode $sibling
         */
        $sibling = $del_location->get_sibling($is_left);

        if ($sibling) {

            /**
             * @var BNode $parent
             */
            $parent = $del_location->parent;

            // getting key to swap in parent
            $swap_pos = $del_location->get_parent_child_pos();

            if ($swap_pos === count($parent->keys)) { // todo use is_left
                $swap_pos--;
            }

            $p_swap_key = $parent->keys[$swap_pos];
            $p_swap_val = $parent->keys[$p_swap_key];

            if (count($sibling->keys) >= $this->min_node_len() + 1) {

                // getting data row before swapping
                $swap_key = $sibling->get_extreme_key($is_left);
                $swap_val = $sibling->keys[$swap_key];

                $sibling->delete_data_cell($swap_key);

                unset($parent->keys[$p_swap_key]);

                $parent->keys[$swap_key] = $swap_val;
                ksort($parent->keys);

                $del_location->delete_data_cell($key);
                $del_location->keys[$p_swap_key] = $p_swap_val;
                ksort($del_location->keys);

                return true;
            } else {
                // deleting
                unset($parent->keys[$p_swap_key]);
                unset($parent->children[$swap_pos]);
                array_splice($parent->children, 0, 0);

                $del_location->keys[$p_swap_key] = $p_swap_val;
                ksort($del_location->keys);

                $sibling->merge_leaves($del_location, $is_left); // todo merge sibling in

                $sibling->delete_data_cell($key);
                return true;
            }
        } else {
            echo "OOOOOOOOOOOOOOOOOPS\n";
            return false;
        }
    }

    /**
     * @param BNode $del_location
     * @param int $key
     * @return bool
     */
    function del_case3($del_location, $key)
    {
        $pos = $del_location->get_cell_pos($key);

        /**
         * @var BNode $child
         */
        if ($child = $del_location->children[$pos]) {

            $c_keys_num = count($child->keys);

            if ($c_keys_num >= $this->min_node_len() + 1) {

                $c_key_pos = $c_keys_num - 1;
                $c_key = $child->keys[$c_key_pos];

                unset($del_location->keys[$key]);
                $del_location->keys[$c_key] = $child->keys[$c_key];
                ksort($del_location->keys);

                return $this->delete_from_node($child, $c_key);

            } else if (
                /**
                 * @var BNode $next_child
                 */
            $next_child = $del_location->children[$pos + 1]) {

                if (count($del_location->children[$pos + 1]->keys) >= $this->min_node_len() + 1) {

                    $c_key = $next_child->keys[0];

                    unset($del_location->keys[$key]);
                    $del_location->keys[$c_key] = $next_child->keys[$c_key];
                    ksort($del_location->keys);

                    return $this->delete_from_node($next_child, $c_key);
                } else {

                    // moving $key=>val data cell to prev child
                    $child->keys[$key] = $del_location->keys[$key];
                    // deleting data cell from del_location
                    $del_location->delete_data_cell($key);
                    // merging the two children
                    $next_child->merge_sibling_in($child, true);

                    if ($del_location->is_root() && count($del_location->keys) == 0) {
                        $this->root = $next_child;
                        $next_child->parent = null;
                        unset($del_location);
                    }

                    // recursive deletion of $key
                    return $this->delete_from_node($next_child, $key);
                }
            } else {
                echo "OOOPS\n";
                return false;
            }
        } else {
            echo "OOOPS\n";
            return false;
        }
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

    function update_node_child_pos($node_pos, $new_pos)
    {

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
}
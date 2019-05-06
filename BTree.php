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
 * @property string $filename
 * @property int $last_line_pos;
 * @property int $root_pos
 *
 */
class BTree
{
    public $t;
    public $root;

//    --- db modifications ---

    public $filename;
    public $last_line_pos;
    public $root_pos;

    public $editor;
    public $formatter;

    /**
     * BTree constructor.
     * @param $t
     * @param $filename
     * @param FileEditor $editor
     * @param bool $is_empty
     * @param int $last_line_pos
     */
    function __construct($t, $filename, $editor, $is_empty = false, $last_line_pos = 1)
    {
        $this->t = $t;
        $this->filename = $filename;
        $this->last_line_pos = $last_line_pos;

        $this->formatter = new DBLineFormatter($this->t);
        $this->editor = $editor;

        if ($is_empty) {
            // inserting empty root node line
            $this->insert_empty_node();
        }

        $this->root_pos = 1;

//        $this->test();
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

    function test()
    {
        $str = $this->formatter->new_node_str('w', 1900, [2, 4, 700, 1000, 10000], [4, 324, 27, 99999, 14], [1, 2, 3, 599, 56, 78]);
        $entry_str = $this->formatter->new_entry_str(120);
        var_dump($entry_str);
    }

    function build_up($data_arr)
    {
        foreach ($data_arr as $key => $data) {
            $this->insert($key, $data);
        }
    }

    function insert($key, $data)
    {
        $root = $this->get_node($this->root_pos, false);
        $accepting_node = $this->descend_to_leaf($root, $key);
        $accepting_node->keys[$key] = $data;
        ksort($accepting_node->keys);

        $this->repair_node($accepting_node);
        $this->add_data_cell($accepting_node, $key, $data);
    }

    function insert_empty_node()
    {
        $str = $this->formatter->new_node_str();
        $this->editor->write($str, $this->last_line_pos);
        $this->last_line_pos++;
    }

    function get_node_line($line_pos)
    {
        $file = new SplFileObject($this->filename, 'r+');
        $file->seek($line_pos);

        $str = $file->current();
        $file = null;
        return $str;
    }

    function get_node($line_pos, $get_values)
    {
        $str = $this->editor->get_line($line_pos);

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
            $curr = $node->get_nth_key($i);

            if ($key < $curr) break;
        }

        $child = $this->get_node($node->children_pos[$i], false);
        var_dump($child);
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
        $median_key = $node->get_nth_key($l_node_len);
        $median_data_pos = $node->get_nth_value_pos($l_node_len);

        // creating right node
        $r_node_keys = array_slice($node->keys, $l_node_len + 1);
        $r_node_children_pos = $node->children_pos ? array_slice($node->children_pos, $l_node_len + 1) : null;
        $r_node = $this->new_node($this->last_line_pos + 1, $r_node_keys, $r_node_children_pos, $node->parent_pos);

        // deleting right node properties from old node
        $l_node_keys = array_slice($node->keys, 0, $l_node_len, true);
        $l_node_children = $node->children ? array_slice($node->children, 0, $l_node_len + 1) : null;

        $node->keys = $l_node_keys;
        $node->children = $l_node_children;

        if ($node->is_root()) {
            // create new root node
            $new_root_pos = $this->new_node($median_key, [$node, $r_node], null);
//                new BNode([$median_key => $median_data], [$node, $r_node]); // todo
            $this->root_pos = $new_root_pos;

            $this->update_node_parent_pos($node->pos, $new_root_pos);
            $this->update_node_parent_pos($r_node->pos, $new_root_pos);
//            $node->parent = $this->root; // update parent val
//            $r_node->parent = $this->root;
        } else {
            $node->parent->keys[$median_key] = $parent_data; // todo
            ksort($node->parent->keys);

            $node->parent->insert_child($r_node);
            ksort($node->keys);
            $this->repair_node($node->parent);
        }
    }

    function new_node($keys, $children_pos, $parent_pos, $values_pos)
    {
        $str = $this->formatter->new_node_str('', $parent_pos, $keys, $values_pos, $children_pos);

        // update children parent
        foreach ($children_pos as $pos) {
            $str = $this->formatter->get_pos_str($this->last_line_pos + 1);

            $child_parent_pos = $this->editor->line_pos($pos) + $this->formatter->get_start_pos('parent_pos');
            $this->editor->write($str, $child_parent_pos, true);
        }

        $this->editor->write($str, $this->last_line_pos + 1);

        return new BNode($this->last_line_pos + 1, $keys, $parent_pos, $children_pos);
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

            $p_swap_key = $parent->get_nth_key($swap_pos);
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
                $c_key = $child->get_nth_key($c_key_pos);

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

                    $c_key = $next_child->get_nth_key(0);

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
        $entry = $this->editor->get_line($line_pos);

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
        $this->editor->write($entry, $location->values_pos[$pos]);

        return true;
    }

//    --

    function update_node_parent_pos($node_pos, $new_pos)
    {
        $str = $this->get_node_line($node_pos);
        $new_pos_str = $this->formatter->get_pos_str($new_pos);
        $write_pos = $this->editor->current_pos() + $this->formatter->get_parent_pos_start();

        $this->editor->write($new_pos_str, $write_pos, true);
    }

    function add_data_cell($node, $key, $data)
    {
        // writing new data entry to database
        $new_entry = $this->formatter->new_entry_str($data);
        $this->editor->write($new_entry, $this->last_line_pos + 1);
        $this->last_line_pos++;

        $node->values_pos [] = $this->last_line_pos - 1;

        // updating node key and data pos
        $str = $this->editor->get_line($node->pos);
        $this->formatter->add_node_data_cell($str, $key, $this->last_line_pos - 1);
        $this->editor->write($str, $node->pos - 1);
    }
}
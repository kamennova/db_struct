<?php

require_once "BNode.php";

/**
 * Class DBLineFormatter
 */
class DBLineFormatter
{
    public $pos_len;

    public $is_delimited = false;
    public $is_inner_delimited = false;
    public $delimiter;
    public $d_len = 0;
    public $i_d_len = 0;

    public $n_format;
    public $t;

    public $empty_str;

    function __construct($t)
    {
        $this->t = $t;
        $this->n_format = Database::$format['node'];

        $this->pos_len = Database::$position_length;
        $this->empty_str = str_repeat(Database::$empty_char, $this->pos_len);
    }

    function get_property_arr($name, $str)
    {
        $arr = [];
        $start = $this->get_start_pos($name);

        $num = 0;
        switch ($name) {
            case ('keys' || 'values_pos'):
                $num = 2 * $this->t - 1;
                break;
            case 'children_pos':
                $num = 2 * $this->t;
                break;
        }

        $data_len = $this->pos_len + $this->i_d_len;

        for ($i = 0; $i < $num; $i++) {
            $elem = substr($str, $start, $data_len);
            $elem = str_replace(Database::$empty_char, '', $elem);

            if ($elem === '') {
                break;
            }

            $arr [] = intval($elem);

            $start += $data_len;
        }

        return $arr;
    }

//    ---

    /**
     * Function returns node string ready for insertion into database.
     * If parameters are null or '', returns empty string (possibly delimited)
     *
     * @param array $keys
     * @param array $values_pos
     * @param int $parent_pos
     * @param string $flag
     * @param array $children_pos
     * @return string
     */
    function new_node_str($flag = '', $parent_pos = null, $keys = null, $values_pos = null, $children_pos = null)
    {
        $empty_str = str_repeat(Database::$empty_char, $this->pos_len);

        $flag_str = $flag ? $flag : str_repeat(Database::$empty_char, $this->n_format['flag']['length']);

        $parent_pos_str = $parent_pos ? str_pad($parent_pos, $this->pos_len, Database::$empty_char, STR_PAD_LEFT)
            : $empty_str;

        $keys_str = $this->get_property_pos_str($keys, $this->t * 2 - 1);
        $values_pos_str = $this->get_property_pos_str($values_pos, $this->t * 2 - 1);
        $children_pos_str = $this->get_property_pos_str($children_pos, $this->t * 2);

        if ($this->is_delimited) {
            $d = $this->delimiter;
            return $flag_str . $d . $parent_pos_str . $d . $keys_str . $d . $values_pos_str . $d . $children_pos_str . "\n";
        } else {
            return $flag_str . $parent_pos_str . $keys_str . $values_pos_str . $children_pos_str . "\n";
        }
    }

    function new_entry_str($data)
    {
        $str = '';

        if (is_array($data)) {
            foreach ($data as $val) {
                $str .= $this->new_value_str($val);

                if ($this->is_delimited) {
                    $str .= $this->delimiter;
                }
            }

            // removing last delimiter
            if ($this->is_delimited) {
                $str = substr($str, 0, strlen($str) - 1);
            }
        } else {
            $str = $this->new_value_str($data);
        }

        return $str . "\n";
    }

    function new_value_str($val)
    {
        $val_len = strlen($val);
        $max = Database::$format['entry']['data']['length'];
        $len_max = strlen($max);

        $str = str_pad($val_len, $len_max, Database::$empty_char, STR_PAD_LEFT);

        if ($this->is_delimited) {
            $str .= $this->delimiter;
        }

        $str .= str_pad($val, $max, Database::$empty_char, STR_PAD_LEFT);

        return $str;
    }

    /**
     * filling children nodes position
     *
     * @param $children_pos
     * @param $fill_num
     * @return string
     */
    function get_property_pos_str($children_pos, $fill_num)
    {
        $str = '';
        $d_empty_str = $this->empty_str . ($this->is_inner_delimited ? $this->delimiter : null);

        if ($children_pos) {
            foreach ($children_pos as $pos) {
                $str .= str_pad($pos, $this->pos_len, Database::$empty_char, STR_PAD_LEFT);
                if ($this->is_inner_delimited) {
                    $str .= $this->delimiter;
                }
            }

            $c_num = count($children_pos);
            if ($c_num < $fill_num) {
                for ($i = 0, $diff = $fill_num - $c_num; $i < $diff; $i++) {
                    $str .= $d_empty_str;
                }
            }

        } else {
            $str = str_repeat($d_empty_str, $fill_num);
        }

        return $str;
    }


//    ---

    /**
     * Function builds BNode from string given.
     * If get_values = true, fills node data with key=>values
     *
     * @param string $str
     * @param int $pos
     * @param boolean $get_values
     * @return BNode
     */
    function get_node($str, $pos, $get_values)
    {
        $d_len = $this->is_delimited ? 1 : 0;
        $i_d_len = $this->is_inner_delimited ? 1 : 0;

        $start = 0;
        $len = $this->n_format['flag']['length'] . $d_len;

        $flag = $this->get_substr($str, $start, $len);
        $parent_pos = $this->get_substr($str, $start, $this->pos_len + $d_len);

        $keys_str = $this->get_substr($str, $start, ($this->pos_len + $i_d_len) * $this->t * 2 - 1);
        $keys = $this->get_property_arr('keys', $keys_str);

        return new BNode($pos, $keys);
    }

    function get_substr($str, &$start, $len)
    {
        $res = substr($str, $start, $start + $len);
        $start = $start + $len;

        return $res;
    }

//    ---

    function get_pos_str($pos)
    {
        return str_pad($pos, $this->pos_len, Database::$empty_char, STR_PAD_LEFT);
    }

//    ---

    function get_start_pos($param)
    {
        switch ($param) {
            case 'flag' :
                return $this->n_format['flag']['length'];
            case 'parent_pos':
                return $this->get_parent_pos_start();
            case 'keys' :
                return $this->get_keys_start();
            case 'children_pos':
                return $this->get_children_pos_start();
            case'values_pos':
                return $this->get_values_pos_start();
            default:
                echo 'fdfd';
                return 0;
        }
    }

//    ---

    function add_node_data_cell(&$str, $key, $value_pos)
    {
        $keys = $this->get_property_arr('keys', $str);
        $values_pos = $this->get_property_arr('values_pos', $str);

        $pos = $this->get_elem_pos($keys, $key);

        $this->replace_properties($str, $keys, $key, $pos, 'keys');
        $this->replace_properties($str, $values_pos, $value_pos, $pos, 'values_pos');
    }

    function replace_properties(&$str, $arr, $prop, $pos, $name)
    {
        $this->replace_property($str, $prop, $pos, $name);

        if ($pos < count($arr)) {
            $shifted_elems = array_slice($arr, $pos);

            foreach ($shifted_elems as $shifted) {
                $this->replace_property($str, $shifted, ++$pos, $name);
            }
        }
    }

    function replace_property(&$str, $prop, $pos, $name = 'keys')
    {
        $prop_str = $this->get_pos_str($prop);
        $start = $this->get_start_pos($name) + $pos * ($this->pos_len + $this->d_len);

        $str = substr($str, 0, $start) . $prop_str . substr($str, $start + strlen($prop_str));
    }

//    ---

    function get_elem_pos($arr, $elem)
    {
        $counter = 0;

        foreach ($arr as $num) {
            if ($num > $elem) break;
            $counter++;
        }

        return $counter;
    }

//    ---

    function get_parent_pos_start()
    {
        $d = ($this->is_delimited) ? 1 : 0;
        return $this->n_format['flag']['length'] + $d;
    }

    function get_keys_start()
    {
        return $this->get_parent_pos_start() + $this->pos_len + $this->d_len;
    }

    function get_values_pos_start()
    {
        return $this->get_keys_start() + ($this->pos_len + $this->i_d_len) * ($this->t * 2 - 1) + $this->d_len;
    }

    function get_children_pos_start()
    {
        return $this->get_values_pos_start() + ($this->pos_len + $this->i_d_len) * ($this->t * 2) + $this->d_len;
    }
}
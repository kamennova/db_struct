<?php

require_once "BTree.php";
require_once "FileEditor.php";

/**
 * Class Database
 *
 * Commands examples:
 *
 *  edit 12 'edited row', 14 => 'new edited row'
 *  delete 12
 *  insert 1 'new record', 23 'another record'
 *
 */
class Database
{
    public $t;
    public $tree;
    public $filename;

    /**
     * @var array $format sets node and entry string structure = [
     *      'node' => [
     *          'flag' - ' '/e/d
     *          'keys' - primary keys
     *          [
     *
     * Example of node {
     *          parent_pos: 56,
     *          children_pos: [10, 345, 23, ],
     *          keys: [1=>'data', 5=>'row',],
     *          data_pos: ['100, 123, ]}
     *
     * node string: " .00056.00001.00005.     .00100.00123.     .00010.00345.00023.     .
     *            d/e |parent| keys           | data pos        | children position     |
     * entry: "010.{96 symbols}data"
     *    data len| data
     *
     */
    static public $format = [
        'node' => [
            'flag' => [
                'length' => 1,
            ],
            'parent_pos' => [
                'length' => 5
            ],
            'keys' => [
                'quantity' => 0,
                'length' => 5,
            ],
            'children_pos' => [
                'quantity' => 0,
                'length' => 5,
            ],
            'data' => [
                'quantity' => 0,
                'length' => '14',
            ]
        ],
        'entry' => [
            'data' => [
                'length' => 100,
            ]
        ]
    ];

    static public $position_length = 5;
    static public $empty_char = '|';

    public $editor;

    function __construct($filename, $t = 200, $data = null)
    {
        $this->filename = $filename;
        $this->t = $t;
        $this->editor = new FileEditor($this->filename);

//        if ($this->editor->size() === 0) {
        $this->add_header();
//        }

        $this->tree = new BTree($this->t, $filename, $this->editor, true);
        if ($data) {
            $this->tree->build_up($data);
        }

        return;

        echo "Commands format: {command_name} {key} (=>{value}";
        readline('Enter a command: ');
    }

    function add_header()
    {
        $this->editor->first_write($this->t . PHP_EOL);
    }

    function insert($key, $data)
    {
        $this->tree->insert($key, $data);
    }
}
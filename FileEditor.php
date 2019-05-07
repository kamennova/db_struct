<?php

class FileEditor
{
    public $filename;
    public $spl;
    public $info;

    public $line_length;

    function __construct($filename)
    {
        $this->filename = $filename;
        $this->spl = new SplFileObject($this->filename, 'w+');

        $this->spl->fseek(0);
        $this->spl->ftruncate(0); // todo temporary
    }

    /**
     * @param string $str
     * @param $line_pos
     * @param int $offset
     */
    function write($str, $line_pos, $offset = 0)
    {
        $this->spl->fseek($line_pos * $this->line_length + $offset);
        $this->spl->fwrite($str);
    }

    function first_write($str)
    {
        $this->spl->fwrite($str);
    }

    function get_line($pos)
    {
        $this->spl->fseek($pos * $this->line_length);
        return $this->spl->current();
    }

    function current_pos()
    {
        return $this->spl->ftell();
    }

    function line_pos($pos)
    {
        $this->spl->seek($pos - 1);
        $prev = $this->spl->ftell();
        $this->spl->seek($pos);
        $curr = $this->spl->ftell();

        return $prev - ($curr - $prev);
    }

    function size()
    {
        $this->info = new SplFileInfo($this->filename);
        return $this->info->getSize();
    }
}
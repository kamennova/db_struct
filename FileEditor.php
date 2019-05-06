<?php

class FileEditor
{
    public $filename;
    public $spl;
    public $info;

    function __construct($filename)
    {
        $this->filename = $filename;
        $this->spl = new SplFileObject($this->filename, 'r+');
        $this->info = new SplFileInfo($this->filename);

        $this->spl->ftruncate(0); // todo temporary
    }

    function write($str, $pos, $is_binary = false)
    {
        $func = $is_binary ? "fseek" : "seek";
        var_dump($this->spl->{$func}($pos));

        var_dump($this->spl->current());

        $this->spl->fwrite($str);
    }

    function first_write($str)
    {
        $this->spl->fwrite($str);
    }

    function get_line($pos)
    {
        $this->spl->seek($pos);
        return $this->spl->current();
    }

    function current_pos()
    {
        return $this->spl->ftell();
    }

    function size(){
        return $this->info->getSize();
    }
}
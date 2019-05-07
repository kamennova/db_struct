<?php

class FileEditor
{
    public $filename;
    public $spl;
    public $info;

    function __construct($filename)
    {
        $this->filename = $filename;
        $this->spl = new SplFileObject($this->filename, 'w+');

        $this->spl->fseek(0);
        $this->spl->ftruncate(0); // todo temporary
    }

    /**
     * @param string $str
     * @param int $pos
     * @param bool $is_binary
     * @param bool $editing
     */
    function write($str, $pos, $is_binary = false, $editing = false)
    {
        $func = $is_binary ? "fseek" : "seek";
        if ($editing) {
            $pos--;
        }
        $this->spl->{$func}($pos);
        ($this->spl->current());

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
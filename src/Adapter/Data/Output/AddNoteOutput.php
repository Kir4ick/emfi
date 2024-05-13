<?php

namespace App\Adapter\Data\Output;

class AddNoteOutput
{
    public function __construct(private bool $result)
    {}

    public function isResult(): bool
    {
        return $this->result;
    }

}

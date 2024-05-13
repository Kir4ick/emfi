<?php

namespace App\Adapter\Data\Input;

class AddNoteInput
{
    public function __construct(
        private string $entityType,
        private int    $entityID,
        private string $noteText
    )
    {
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityID(): int
    {
        return $this->entityID;
    }

    public function getNoteText(): string
    {
        return $this->noteText;
    }

}

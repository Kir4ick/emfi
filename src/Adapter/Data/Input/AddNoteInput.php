<?php

namespace App\Adapter\Data\Input;

class AddNoteInput
{

    public function __construct(
        private readonly string $entityType,
        private readonly int    $entityID,
        private readonly string $noteText,
        private readonly int    $creatorID,
        private readonly int    $creatorAccountID,
    ) {
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

    public function getCreatorID(): int
    {
        return $this->creatorID;
    }

    public function getCreatorAccountID(): int
    {
        return $this->creatorAccountID;
    }

}

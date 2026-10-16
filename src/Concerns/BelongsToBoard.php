<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use EduardoRibeiroDev\FilamentKanban\Board;
use Exception;

trait BelongsToBoard
{
    protected ?Board $board = null;

    public function board(Board $board): static
    {
        $this->board = $board;

        return $this;
    }

    public function getBoard(): Board
    {
        return $this->board ?? throw new Exception(
            "A coluna [{$this->getId()}] ainda não está vinculada a um Board."
        );
    }
}

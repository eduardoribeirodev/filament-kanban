<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use EduardoRibeiroDev\FilamentKanban\Contracts\HasBoard;

trait BelongsToLivewire
{
    protected HasBoard $livewire;

    public function livewire(HasBoard $livewire): static
    {
        $this->livewire = $livewire;

        return $this;
    }

    public function getLivewire(): HasBoard
    {
        return $this->livewire;
    }
}

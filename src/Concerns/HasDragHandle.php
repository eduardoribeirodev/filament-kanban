<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use Closure;

trait HasDragHandle
{
    protected bool|Closure $hasDragHandle = false;

    public function dragHandle(bool|Closure $condition = true): static
    {
        $this->hasDragHandle = $condition;

        return $this;
    }

    public function hasDragHandle(): bool
    {
        return (bool) $this->evaluate($this->hasDragHandle);
    }
}

<?php

namespace EduardoRibeiroDev\FilamentKanban\Enums;

use Filament\Support\Contracts\HasLabel;

enum CardView: string implements HasLabel
{
    case Default = 'filament-kanban::card';
    case Compact = 'filament-kanban::cards.compact';
    case Flat = 'filament-kanban::cards.flat';
    case Accent = 'filament-kanban::cards.accent';

    public function getLabel(): string
    {
        return __('filament-kanban::card-views.'.match ($this) {
            self::Default => 'default',
            self::Compact => 'compact',
            self::Flat => 'flat',
            self::Accent => 'accent',
        });
    }
}

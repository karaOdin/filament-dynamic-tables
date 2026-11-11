<?php

namespace KaraOdin\FilamentDynamicTables\Enums;

enum FavoritesBarTheme: string
{
    case Github = 'github';
    case Filament = 'filament';
    case Links = 'links';
    case LinksSimple = 'links-simple';
    case Tabs = 'tabs';
    case TabsSimple = 'tabs-simple';

    public function showsIcons(): bool
    {
        return ! in_array($this, [self::LinksSimple, self::TabsSimple], true);
    }
}

<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('enums are enums')
    ->expect('KaraOdin\FilamentDynamicTables\Enums')
    ->toBeEnums();

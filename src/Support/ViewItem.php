<?php

namespace KaraOdin\FilamentDynamicTables\Support;

use BackedEnum;
use Illuminate\Contracts\Support\Htmlable;
use KaraOdin\FilamentDynamicTables\Components\PresetView;
use KaraOdin\FilamentDynamicTables\Enums\ViewStatus;
use KaraOdin\FilamentDynamicTables\Enums\ViewType;
use KaraOdin\FilamentDynamicTables\Models\UserView;

/**
 * A normalised, render-ready description of any view (default, preset or
 * user view) as shown in the favorites bar and the view manager.
 */
class ViewItem
{
    public function __construct(
        public string $key,
        public ViewType $type,
        public string | Htmlable $label,
        public string | BackedEnum | null $icon = null,
        public string | BackedEnum | null $activeIcon = null,
        public string | array | null $color = null,
        public ?string $tooltip = null,
        public mixed $badge = null,
        public string | array | null $badgeColor = null,
        public bool $isFavorite = false,
        public bool $isDefault = false,
        public bool $isActive = false,
        public bool $isModified = false,
        public bool $isOwn = false,
        public bool $isPublic = false,
        public bool $isGlobalFavorite = false,
        public ?ViewStatus $status = null,
        public ?string $ownerName = null,
        public bool $canEdit = false,
        public bool $canDelete = false,
        public ?int $sortOrder = null,
        public int $naturalOrder = 0,
        public ?PresetView $presetView = null,
        public ?UserView $userView = null,
        public bool $isManageable = true,
    ) {}

    /**
     * Favorites are shown preset views first, then global favorites that
     * users can't move, then everything else.
     */
    public function getFavoritesGroup(): int
    {
        return match (true) {
            $this->isPreset() => 0,
            $this->isGlobalFavorite && (! $this->isManageable) => 1,
            default => 2,
        };
    }

    public function isPreset(): bool
    {
        return $this->type === ViewType::Preset;
    }

    public function isUserView(): bool
    {
        return $this->type === ViewType::User;
    }

    public function isDefaultView(): bool
    {
        return $this->type === ViewType::Default;
    }

    public function isPending(): bool
    {
        return $this->status === ViewStatus::Pending;
    }

    public function isRejected(): bool
    {
        return $this->status === ViewStatus::Rejected;
    }

    public function getSearchText(): string
    {
        return mb_strtolower(strip_tags((string) $this->label) . ' ' . ($this->ownerName ?? ''));
    }
}

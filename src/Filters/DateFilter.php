<?php

namespace KaraOdin\FilamentDynamicTables\Filters;

use Carbon\CarbonImmutable;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use KaraOdin\FilamentDynamicTables\Filters\Operators\DateOperator;
use Throwable;

/**
 * Date column filter: today, yesterday, in the last / next N units,
 * this / last / next unit, before, after, between, is empty ...
 */
class DateFilter extends ColumnFilter
{
    protected string | Closure | null $timezone = null;

    protected const RELATIVE = [
        DateOperator::IN_THE_LAST, DateOperator::NOT_IN_THE_LAST, DateOperator::IN_THE_NEXT,
        DateOperator::NOT_IN_THE_NEXT, DateOperator::EXACTLY_AGO, DateOperator::EXACTLY_FROM_NOW,
    ];

    protected const PERIOD = [DateOperator::THIS, DateOperator::LAST, DateOperator::NEXT];

    protected const SINGLE_DATE = [DateOperator::IS_DATE, DateOperator::BEFORE, DateOperator::AFTER];

    protected const RANGE = [DateOperator::BETWEEN, DateOperator::NOT_BETWEEN];

    public function timezone(string | Closure | null $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->evaluate($this->timezone) ?? config('app.timezone', 'UTC');
    }

    public function getAllOperators(): array
    {
        return DateOperator::all();
    }

    public function getDefaultOperatorFallback(): string
    {
        return DateOperator::IN_THE_LAST;
    }

    protected function getOperatorsWithoutValue(): array
    {
        return [DateOperator::TODAY, DateOperator::YESTERDAY, DateOperator::TOMORROW, DateOperator::IS_EMPTY, DateOperator::IS_NOT_EMPTY];
    }

    /**
     * @return array<string, string>
     */
    protected function getUnitOptions(): array
    {
        return collect(DateOperator::units())
            ->mapWithKeys(fn (string $unit): array => [$unit => __("filament-dynamic-tables::dynamic-tables.filter_builder.units.{$unit}")])
            ->all();
    }

    protected function getValueSchema(): array
    {
        $unit = fn (): Select => Select::make('unit')
            ->hiddenLabel()
            ->options($this->getUnitOptions())
            ->default(DateOperator::UNIT_DAYS)
            ->selectablePlaceholder(false)
            ->native(false);

        return [
            Grid::make(2)
                ->schema([
                    TextInput::make('value')
                        ->hiddenLabel()
                        ->integer()
                        ->minValue(0)
                        ->placeholder('7'),
                    $unit(),
                ])
                ->visible($this->visibleFor(static::RELATIVE)),
            $unit()->visible($this->visibleFor(static::PERIOD)),
            DatePicker::make('date')
                ->hiddenLabel()
                ->native(false)
                ->visible($this->visibleFor(static::SINGLE_DATE)),
            Grid::make(2)
                ->schema([
                    DatePicker::make('date_start')->hiddenLabel()->native(false)->placeholder(__('filament-dynamic-tables::dynamic-tables.filter_builder.form.from')),
                    DatePicker::make('date_end')->hiddenLabel()->native(false)->placeholder(__('filament-dynamic-tables::dynamic-tables.filter_builder.form.to')),
                ])
                ->visible($this->visibleFor(static::RANGE)),
        ];
    }

    protected function hasValue(string $operator, array $data): bool
    {
        return match (true) {
            in_array($operator, static::RELATIVE, true) => is_numeric($data['value'] ?? null),
            in_array($operator, static::PERIOD, true) => true,
            in_array($operator, static::SINGLE_DATE, true) => $this->parseDate($data['date'] ?? ($data['value'] ?? null)) !== null,
            in_array($operator, static::RANGE, true) => ($this->parseDate($data['date_start'] ?? null) !== null) || ($this->parseDate($data['date_end'] ?? null) !== null),
            default => false,
        };
    }

    protected function getUnit(array $data): string
    {
        $unit = $data['unit'] ?? DateOperator::UNIT_DAYS;

        return in_array($unit, DateOperator::units(), true) ? $unit : DateOperator::UNIT_DAYS;
    }

    protected function parseDate(mixed $value): ?CarbonImmutable
    {
        if (blank($value) || (! is_scalar($value))) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value, $this->getTimezone());
        } catch (Throwable) {
            return null;
        }
    }

    protected function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->getTimezone());
    }

    protected function shift(CarbonImmutable $date, string $unit, int $amount): CarbonImmutable
    {
        return match ($unit) {
            DateOperator::UNIT_WEEKS => $date->addWeeks($amount),
            DateOperator::UNIT_MONTHS => $date->addMonthsNoOverflow($amount),
            DateOperator::UNIT_QUARTERS => $date->addQuartersNoOverflow($amount),
            DateOperator::UNIT_YEARS => $date->addYearsNoOverflow($amount),
            default => $date->addDays($amount),
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function period(CarbonImmutable $date, string $unit): array
    {
        return match ($unit) {
            DateOperator::UNIT_WEEKS => [$date->startOfWeek(), $date->endOfWeek()],
            DateOperator::UNIT_MONTHS => [$date->startOfMonth(), $date->endOfMonth()],
            DateOperator::UNIT_QUARTERS => [$date->startOfQuarter(), $date->endOfQuarter()],
            DateOperator::UNIT_YEARS => [$date->startOfYear(), $date->endOfYear()],
            default => [$date->startOfDay(), $date->endOfDay()],
        };
    }

    /**
     * The [from, to] range an operator covers, or null for open-ended ones.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public function getRange(string $operator, array $data): array
    {
        $now = $this->now();
        $unit = $this->getUnit($data);
        $amount = (int) ($data['value'] ?? 0);

        return match ($operator) {
            DateOperator::TODAY => [$now->startOfDay(), $now->endOfDay()],
            DateOperator::YESTERDAY => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            DateOperator::TOMORROW => [$now->addDay()->startOfDay(), $now->addDay()->endOfDay()],
            DateOperator::IN_THE_LAST, DateOperator::NOT_IN_THE_LAST => [$this->shift($now, $unit, -$amount), $now],
            DateOperator::IN_THE_NEXT, DateOperator::NOT_IN_THE_NEXT => [$now, $this->shift($now, $unit, $amount)],
            DateOperator::EXACTLY_AGO => [$this->shift($now, $unit, -$amount)->startOfDay(), $this->shift($now, $unit, -$amount)->endOfDay()],
            DateOperator::EXACTLY_FROM_NOW => [$this->shift($now, $unit, $amount)->startOfDay(), $this->shift($now, $unit, $amount)->endOfDay()],
            DateOperator::THIS => $this->period($now, $unit),
            DateOperator::LAST => $this->period($this->shift($now, $unit, -1), $unit),
            DateOperator::NEXT => $this->period($this->shift($now, $unit, 1), $unit),
            DateOperator::IS_DATE => [$this->parseDate($data['date'] ?? ($data['value'] ?? null))?->startOfDay(), $this->parseDate($data['date'] ?? ($data['value'] ?? null))?->endOfDay()],
            DateOperator::BEFORE => [null, $this->parseDate($data['date'] ?? ($data['value'] ?? null))?->startOfDay()],
            DateOperator::AFTER => [$this->parseDate($data['date'] ?? ($data['value'] ?? null))?->endOfDay(), null],
            DateOperator::BETWEEN, DateOperator::NOT_BETWEEN => [$this->parseDate($data['date_start'] ?? null)?->startOfDay(), $this->parseDate($data['date_end'] ?? null)?->endOfDay()],
            default => [null, null],
        };
    }

    protected function applyOperator(Builder $query, string $operator, array $data): void
    {
        if (in_array($operator, [DateOperator::IS_EMPTY, DateOperator::IS_NOT_EMPTY], true)) {
            $this->whereEmpty($query, $operator === DateOperator::IS_NOT_EMPTY);

            return;
        }

        [$from, $to] = $this->getRange($operator, $data);
        $appTimezone = config('app.timezone', 'UTC');
        $format = fn (CarbonImmutable $date): string => $date->setTimezone($appTimezone)->format('Y-m-d H:i:s');

        $negate = in_array($operator, [DateOperator::NOT_IN_THE_LAST, DateOperator::NOT_IN_THE_NEXT, DateOperator::NOT_BETWEEN], true);

        $this->where($query, function (Builder $query, string $column) use ($from, $to, $operator, $format): void {
            if ($from !== null) {
                $query->where($column, $operator === DateOperator::AFTER ? '>' : '>=', $format($from));
            }

            if ($to !== null) {
                $query->where($column, $operator === DateOperator::BEFORE ? '<' : '<=', $format($to));
            }
        }, $negate);
    }

    protected function getValueSummary(string $operator, array $data): ?string
    {
        $date = fn (mixed $value): string => $this->parseDate($value)?->translatedFormat('M j, Y') ?? '…';
        $unit = __("filament-dynamic-tables::dynamic-tables.filter_builder.units.{$this->getUnit($data)}");

        return match (true) {
            in_array($operator, static::RELATIVE, true) => ((int) ($data['value'] ?? 0)) . ' ' . str($unit)->lower(),
            in_array($operator, static::PERIOD, true) => (string) str(__("filament-dynamic-tables::dynamic-tables.filter_builder.units_singular.{$this->getUnit($data)}"))->lower(),
            in_array($operator, static::SINGLE_DATE, true) => $date($data['date'] ?? ($data['value'] ?? null)),
            in_array($operator, static::RANGE, true) => $date($data['date_start'] ?? null) . ' ' . __('filament-dynamic-tables::dynamic-tables.filter_builder.indicator.and') . ' ' . $date($data['date_end'] ?? null),
            default => null,
        };
    }
}

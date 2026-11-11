# Changelog

All notable changes to `karaodin/filament-dynamic-tables` will be documented in this file.

## Unreleased

- Initial release.
- User views: save filters, search, column searches, multi-sort, grouping, toggled and reordered columns, records per page and advanced search settings.
- Preset views defined in code, with query modification, default filters, sort, multi-sort, grouping, search, visible columns, column order, badges, tooltips, colors, visibility and preserve options.
- Favorites bar with six themes (GitHub, Filament, Links, Links simple, Tabs, Tabs simple), sizes, icon position, divider, badges and loading indicators.
- Quick save, view manager (search, favorites, drag to reorder, edit, replace, make public or private, delete) and managed default views.
- Public views, global favorites and an approval workflow with a User Views resource.
- Multi-column sorting, quick filters with pinning, favorite filters, label limits and per-indicator chips.
- Advanced search with eight constraints (`-` `=` `^` `$` shortcuts), column and database column selection, AND / OR groups, `&` / `|` boolean syntax, word limits, keybindings, date and number equality, URL binding and `$constraint` for custom search queries.
- Advanced filter builder with Text, Numeric, Date, Select and Boolean column filters, custom and Filament filters, OR groups, a searchable filter picker, colored per-filter indicators and an "Expand view" slide-over.
- Advanced Tables compatible plugin method names, per-table static switches, approval statuses (`initialStatus()`, `minimumStatusForDisplay()`), manageable global and preset views, a customizable Default view (preset keyed `default`), save form summary, icon aliases, every Heroicon in the icon picker.
- View manager as a dropdown or slide-over with five sections, per-view action menu, section reordering, save and reset links, type icons or badges.
- User Views resource with panel scoping, an Open action, clickable public / global toggles and lazy user loading.
- Standalone Table Builder base classes (`Livewire\Page`, `PanelPage`, `CustomPage`), a `<x-filament-dynamic-tables::favorites-bar />` component, a `ListUserViews` component and widget traits.
- Loading skeleton overlay, multi-tenancy (Filament, simple, Spatie, Stancl) with a configurable tenant column, policies, English, French, Spanish and Arabic translations.
- Supports Filament 4.16+ and 5.9+ on Laravel 12 and 13.

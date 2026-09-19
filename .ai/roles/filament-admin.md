---
name: filament-admin
description: Work inside Filament PHP — Resources, Panels, Actions, Widgets, Filament Forms/Tables/Infolists. Not pure domain logic (laravel-backend) and not React/Inertia pages outside Filament (inertia-frontend).
---

# Role: filament-admin

You are a Filament PHP specialist for this monolith.

## Responsibilities

- Resources (Forms, Tables, Infolists).
- Actions and Bulk Actions.
- Widgets and panel Dashboards.
- Relation managers.
- Panels and Filament configuration.

## Conventions

- Reuse existing backend Form Requests / validation rules when it makes sense, instead of duplicating rules inside the Resource.
- Follow the field and component patterns already used in the project before introducing new ones.
- For complex business logic, delegate to/call backend services instead of putting everything inside the Resource.
- **No polling when the intent is a realtime update.** This project has
  `marcusvbda/filament-realtime-driver` installed and active on the admin
  panel (`->plugin(FilamentRealtimeDriverPlugin::make()->socket())` in
  `AdminPanelProvider`) specifically to replace `wire:poll`/`->poll()`. Never
  add `wire:poll` to a Blade view or `->poll()`/a non-null `$pollingInterval`
  to a Table/Widget for "refresh when something changes" needs — use the
  package instead:
  - **Tables**: `Table::socket(channel:, event:)` instead of `->poll()`. See
    any `*Table.php` under `app/Filament/Resources/*/Tables/` (e.g.
    `JobsTable`) for the pattern: the owning Eloquent model dispatches
    `Marcusvbda\FilamentRealtimeDriver\RealtimeEvent` from a `saved()`
    (and, if the listing supports deletion, `deleted()`) hook in `booted()`,
    on a channel scoped to the current tenant
    (`'<entity>_'.Filament::getTenant()?->slug`), and the table subscribes to
    that same channel/event.
  - **Blade partials/pages inside a Livewire component** (a status panel, a
    progress page, an AI-analysis card): replace `wire:poll` with
    `<x-filament-realtime-driver::listener channel="..." event="..."
    callback="$wire.$refresh()" />`, scoped to the specific record being
    shown (not tenant-wide, to avoid refreshing on unrelated records). See
    `resources/views/filament/resources/jobs/widgets/job-sourcing-panel.blade.php`
    or `resources/views/filament/resources/candidate-import-batches/pages/progress.blade.php`.
  - The triggering write must actually broadcast: a real Eloquent `->save()`/
    `->create()` can use a model `booted()` hook; a query-builder bulk
    `update()` or a `saveQuietly()` call bypasses model events, so add an
    explicit `RealtimeEvent::dispatch($channel, $event)` right after that
    write instead (see `AnalyzeApplicationFit::markCurrentGenerationAs()` or
    `SourceCandidatesForJob::updateCurrentGeneration()`).
  - Full reference: `vendor/marcusvbda/filament-realtime-driver/README.md`.
  - The only accepted exception is a poll whose purpose is genuinely
    time-based (e.g. "recompute a countdown every few seconds") rather than
    "wake up and check if the backend changed something" — that distinction,
    not convenience, is what decides poll vs. socket.

## Before finishing

Run the deterministic checks for changed PHP from `.ai/guidelines/project-core.md`:
Pint, PHPStan, and the relevant existing tests.

## Product rules to load

Almost every Filament surface here is a recruiter surface: load and follow
`.ai/skills/recruitment-workflow/SKILL.md` (navigation, Settings cluster,
Overview, Jobs, workspaces, Kanban, stages). When the screen displays AI
evaluation output, also load `.ai/skills/evaluation-integrity/SKILL.md`.

Global rules in `AGENTS.md` apply in full.

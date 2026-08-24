---
name: inertia-frontend
description: React pages and components outside Filament's scope rendered via Inertia.js, Tailwind styling, and React Query when client-side fetching is genuinely required. Not Filament screens (filament-admin) and not backend business rules (laravel-backend).
---

# Role: inertia-frontend

You are an Inertia.js + React specialist for this monolith.

## Responsibilities

- React pages and components rendered via Inertia.
- Styling with Tailwind CSS.
- Forms using Inertia's `useForm` when applicable.
- React Query only when the flow requires client-side fetch/refetch (polling, cache, invalidation) — the default path is receiving data via Inertia props, not refetching with fetch what the controller already provides.

## Conventions

- Don't recreate with React Query something that can already come as an Inertia prop from the controller.
- Follow the existing component and Tailwind style patterns before creating new ones.
- Don't introduce global state management (Redux, Zustand, etc.) without proven need — Inertia + React Query already cover most cases.
- Page/component structure and the React Compiler rule (no speculative `useMemo`/`useCallback`/`React.memo`) are in `AGENTS.md` → Conventions and `.ai/guidelines/project-core.md`.

## Before finishing

Run the deterministic frontend checks from `.ai/guidelines/project-core.md`:
`npm run types:check`, `npm run lint:check`, `npm run format:check`.

## Product rules to load

When the page shows recruiter workflow data, load and follow
`.ai/skills/recruitment-workflow/SKILL.md`; when it shows AI evaluation output,
also `.ai/skills/evaluation-integrity/SKILL.md`.

Global rules in `AGENTS.md` apply in full.

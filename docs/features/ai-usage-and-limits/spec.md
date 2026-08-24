---
status: implemented
type: as-built
---

# AI usage and limits

## Problem

AI-assisted recruiting consumes an external model provider and therefore has
cost, availability, and credential implications. A workspace needs to understand
its usage and know whether it is using Recruiter Labs' platform AI allowance or
its own provider credentials.

The product must also block platform-funded analysis cleanly when allowance is
exhausted without turning that operational limit into a negative candidate
signal.

## Objective

Track AI executions and their resource usage, enforce workspace plan allowance
for platform-funded AI, support plan-gated bring-your-own OpenAI credentials, and
make provider/usage state visible to the workspace.

## User behaviour

In AI settings, a workspace can see:

- the effective AI provider and model;
- current AI-analysis usage/remaining allowance;
- recent AI execution history;
- token usage and estimated cost when available;
- whether executions used platform AI or the workspace's own key;
- credential validation state for an own key.

When the workspace plan supports it, the user can:

- configure an OpenAI API key;
- choose the supported model;
- test the configured credentials;
- remove the own key;
- switch back to platform AI.

Applications whose platform-funded evaluation cannot run because allowance is
exhausted enter a visible blocked/waiting state rather than receiving a fake
evaluation.

## Business rules

The evaluation rules in `.ai/skills/evaluation-integrity/SKILL.md` apply to every
AI execution that produces candidate-evaluation data.

- Platform AI analysis is subject to the workspace's configured plan allowance.
- A platform allowance limit blocks the AI operation; it does not change
  candidate fit or create negative evidence.
- Use of a workspace-owned AI key is available only when the current plan enables
  that feature and valid credentials are configured.
- When an eligible workspace uses its own key, platform-funded AI allowance is
  not the reason to block that provider call.
- If own-key configuration is unavailable or unusable, the effective
  configuration falls back to the platform behaviour defined by the product.
- Credential testing and provider selection are explicit workspace actions.
- AI executions record enough usage information to distinguish operation,
  provider/model, execution status, token consumption, estimated cost when
  available, and whether the workspace's own key was used.
- AI response caching does not bypass product-integrity validation. A cache hit is
  still subject to the same criteria revision and persistence rules as a fresh
  candidate evaluation.
- Cache identity includes the relevant model/provider contract and request
  context so an incompatible cached response is not silently reused.
- Real provider usage that occurred is tracked honestly even when a later
  concurrency check decides the response is stale and must not become the current
  evaluation.
- AI never receives authority over plan changes, allowance, billing, permissions,
  database writes, or recruitment side effects.
- There is no product promise of unlimited provider-funded AI.

## User flow

### Platform AI

1. The workspace uses the platform AI configuration.
2. A supported AI operation is requested.
3. The product checks whether the relevant platform allowance is available.
4. If available, the operation runs and usage is recorded.
5. If exhausted, the operation is blocked in a visible operational state without
   producing candidate-evaluation output.

### Own key

1. A workspace on an eligible plan chooses to configure its own OpenAI key.
2. The user enters the key and supported model and explicitly saves/tests it.
3. A valid eligible own-key configuration becomes the effective provider for AI
   operations that support it.
4. Executions are tracked as own-key usage.
5. The user can test or remove the credentials and can switch back to platform
   AI.

### Usage visibility

1. AI operations create usage history.
2. AI settings summarize the current allowance and recent operations.
3. The workspace can distinguish platform and own-key usage and see execution
   status/cost metadata when available.

## Acceptance criteria

- **AC01** — A platform-funded AI analysis can be blocked when the workspace's AI
  analysis allowance is reached.
- **AC02** — Allowance exhaustion does not reduce candidate fit or generate
  candidate evidence.
- **AC03** — An eligible workspace can configure and explicitly test its own
  supported OpenAI credentials.
- **AC04** — A workspace without the required plan feature cannot rely on
  bring-your-own-key execution merely because credentials happen to exist.
- **AC05** — A valid eligible own-key configuration can become the effective AI
  provider for supported operations.
- **AC06** — The user can remove own credentials and return to platform AI
  behaviour.
- **AC07** — AI execution history distinguishes operation, model/provider,
  execution status, token usage, and own-key/platform usage.
- **AC08** — Estimated cost is displayed only when the product has a value to
  report rather than being fabricated.
- **AC09** — Cached candidate-evaluation output still passes the same revision
  integrity checks as fresh provider output.
- **AC10** — A stale AI response is not persisted as the current candidate
  evaluation merely because the provider call consumed tokens.
- **AC11** — AI usage/limits never grant the model authority to alter billing,
  plan, permissions, tenant ownership, pipeline state, or hiring decisions.

## Out of scope

- Real subscription billing and payment collection.
- Unlimited platform-funded AI.
- Arbitrary AI provider/model selection.
- Automatic purchase of additional AI usage.
- Cost optimization that weakens evaluation integrity.
- Final security validation of credential storage or provider-data handling; that
  belongs to release security work.

## Related feature specs

- `../candidate-evaluation/spec.md`
- `../job-evaluation-criteria/spec.md`
- `../recruitment-attention/spec.md`

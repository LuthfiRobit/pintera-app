# Agent Rules & Superpowers Workflow Integration

> **Sync notice:** This file is duplicated at the project root (`/AGENTS.md`) so that
> tools which only auto-discover `AGENTS.md` at the repo root still find these rules.
> Both copies must stay byte-identical. If you edit one, edit the other the same way
> in the same turn.

This project is configured with the **obra/superpowers** engineering methodology framework, cloned into `.agents/skills/`. This file is the **project-specific instruction layer** — per `using-superpowers`'s own priority rule ("user instructions... take precedence over skills, which in turn override default behavior"), everything below **overrides** any conflicting default inside the cloned skill files. Do not silently follow a skill's built-in default path or step order when this file says otherwise.

## Mandatory 7-Stage Workflow

Every task delegated with `/using-superpowers <task description>` MUST go through all 7 stages below, in order, without skipping or merging steps. This applies regardless of which model/agent is executing (Claude, Gemini, GPT-OSS, or any other model running inside Antigravity or another harness).

| # | Stage | Skill to invoke | Output |
|---|-------|------------------|--------|
| 1 | **Brainstorming** | `superpowers:brainstorming` | Problem summary + ≥2 approaches with trade-offs (complexity/performance/maintainability) + recommendation. Ask the user before guessing on any decision that affects architecture. |
| 2 | **Menulis Spec** | (part of `brainstorming`'s own flow — see path override below) | `.agents/specs/<nama-task>.md` — tujuan, scope (in/out), asumsi, acceptance criteria. Must be understandable by a fresh agent with zero prior context. |
| 3 | **Menulis Plan** | `superpowers:writing-plans` | `.agents/plans/<nama-task>.md` — atomic checklist steps, each independently verifiable done/not-done, testing plan included inline (not appended later). |
| 4 | **Eksekusi Plan** | `superpowers:executing-plans` or `superpowers:subagent-driven-development` (prefer the latter if subagent dispatch is available on your platform — see `.agents/skills/using-superpowers/references/`) | Code changes, one plan step at a time. Explore the existing codebase and follow its conventions before writing anything new — do not reinvent patterns that already exist. Update the plan file's checklist (`- [x]`) as each step completes. |
| 5 | **Testing** | `superpowers:test-driven-development` | Every code change ships with a test (automated if a framework exists, manual steps documented in the plan if not). Run the existing suite to confirm no regressions. Log any bug found during testing before fixing it silently. |
| 6 | **Review & Perbaikan** | `superpowers:verification-before-completion`, `superpowers:requesting-code-review` | Self-review the diff as an independent reviewer would: matches spec? edge cases missed? anything simplifiable? Fix findings before marking the task done. |
| 7 | **Serah Terima (Handoff Log)** | *(no upstream skill covers this — see explicit instructions below)* | `.agents/logs/<nama-task>.md` |

**After stage 6**, if the work is ready to merge/PR, invoke `superpowers:finishing-a-development-branch` to handle the git decision (merge locally / PR / keep / discard) as that skill normally would. **Stage 7 happens after that**, regardless of which git option was chosen — the handoff log records what happened either way.

### Stage 7 in detail — Handoff Log (explicit instruction, not delegated to any cloned skill)

None of the cloned skills produce a handoff log — `executing-plans` and `subagent-driven-development` both terminate at `finishing-a-development-branch` (a git decision), not a written summary. This project requires one anyway. Before considering *any* delegated task complete, write `.agents/logs/<nama-task>.md` containing at minimum:

- **Apa yang dikerjakan** — concrete summary of the change, referencing the spec/plan file names.
- **Keputusan penting yang diambil** — any non-obvious choice made along the way (especially ones the spec/plan left ambiguous, or ones you resolved without asking the user because they were low-stakes/reversible).
- **Hal yang masih perlu direview manusia/Claude** — anything you're unsure about, anything you deliberately deferred, any assumption Claude should double-check when it reviews this later, and the current git state (branch name, whether merged, whether pushed).

Never delete or overwrite old files in `.agents/specs/`, `.agents/plans/`, or `.agents/logs/` — they are an audit trail. Each new task gets its own dated/named file.

## Path Override — Do Not Use the Skill Defaults

`.agents/skills/brainstorming/SKILL.md` and `.agents/skills/writing-plans/SKILL.md` both default to saving under `docs/superpowers/specs/` and `docs/superpowers/plans/` respectively. **That default does not apply in this project.** Both of those skill files also explicitly say "(User preferences for spec/plan location override this default)" — this section IS that user preference:

- Specs → `.agents/specs/<nama-task>.md` (not `docs/superpowers/specs/...`)
- Plans → `.agents/plans/<nama-task>.md` (not `docs/superpowers/plans/...`)
- Logs → `.agents/logs/<nama-task>.md` (new convention, not part of the upstream skill at all — see Stage 7 above)

Use a short, kebab-case `<nama-task>` slug consistent across the spec/plan/log filenames for one task (e.g. `tambah-fitur-x.md` in all three folders), so the three files for a given task are trivially findable together.

When announcing that a spec or plan is complete, state the **actual path you just saved to** (`.agents/specs/<nama-task>.md` or `.agents/plans/<nama-task>.md`). Do not copy-paste the literal `docs/superpowers/...` example string from the skill's own announcement template (e.g. `writing-plans/SKILL.md`'s "Plan complete and saved to `docs/superpowers/plans/<filename>.md`" text) — that string is only a template illustration in the upstream skill, not what you actually did here.

## Continuation Rule — Never Start From Zero

Before starting work on any task, check whether it is a continuation of prior work:

1. Look in `.agents/logs/` for a log file whose name or content matches the task description.
2. If found, **read that log file, and the spec/plan files it references, before doing anything else** — including before brainstorming. Do not re-derive decisions that are already recorded there. Do not re-litigate a decision the log says was already made, unless the user is explicitly asking you to revisit it.
3. Also check `git log` / `git status` directly — logs and plans can go stale or be incomplete; the actual commit history is the ground truth for what code state exists right now.
4. If the prior log says something is "for human/Claude review" and unresolved, treat that as an open question to raise with the user before proceeding, not something to silently decide yourself.

This mirrors how Claude itself resumes interrupted work in this project — never trust chat memory alone, always verify against the files on disk and git history.

## Core Engineering Workflows (skill reference)

The 7 stages above are implemented by these skills, located in `.agents/skills/`:

1. **Brainstorming & Planning (`brainstorming`, `writing-plans`)**
   - Explore requirements and outline detailed implementation plans before making extensive changes. (Stages 1–3 above.)

2. **Test-Driven Development (`test-driven-development`)**
   - Write failing tests first to establish clear criteria, then write implementation code to make them pass. (Stage 5 above.)

3. **Systematic Debugging (`systematic-debugging`)**
   - Investigate root causes using logs and tests rather than guessing or making superficial symptom patches. Use this whenever a task is a bug fix rather than new work, before proposing a fix.

4. **Verification & Code Review (`verification-before-completion`, `requesting-code-review`)**
   - Perform concrete runtime verification (running tests and build commands) before marking tasks complete. (Stage 6 above.)

5. **Subagent & Multi-Tasking (`subagent-driven-development`, `dispatching-parallel-agents`)**
   - Break large tasks into focused autonomous sub-steps when appropriate. (Stage 4 above, when subagent dispatch is available on your platform.)

6. **Finishing Up (`finishing-a-development-branch`)**
   - Present merge/PR/keep/discard options and execute the chosen one, after Stage 6 and before Stage 7.

## Platform Notes

If you are not Claude Code, read `.agents/skills/using-superpowers/references/` for your platform's tool mapping (Antigravity, Codex, Pi, Gemini) before starting — it tells you what your harness's real tools correspond to when a skill says "dispatch a subagent" or "create a todo."

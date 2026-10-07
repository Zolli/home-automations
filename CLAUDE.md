# Agents instructions

- If it necessary to store these decisions/history use ADRs or other documents, but try to minimize
- Dont commit anything in git by yourself
- Commit messages: `type(component): few-word summary`, an empty line, then a few `- ` bullets naming the main changes, each only a few words, meaningful; no decisions, reasons or history, nothing else
- PHP runs only in the container; use the `make` targets (`make stan`, `make test`). `make run ONLY=<app> DRY_RUN=1` runs one app without sending service calls.
- `generated/` is written by `make generate`; never edit it by hand.

# Coding instuctions
- Use as little comments in the code as possible
- Limit the length of comment to a very brief summary
- Dont store history, or historical decisions and changes in comments
- Docblocks hold type tags only; a "why" is one plain `//` line of 120 chars or less, no personification or rhetoric. A comment explaining a constant becomes a better constant name.
- Test method names say behavior + condition in 50 chars or less, without leading articles.
- Try minimizing the usage of simple arrays to transfer data, use proper objects to do that.
- Use descriptive variable/class/method names if it makes sense
- Use VOs/DTOs where make sense
- You must act like a staff level software engineer / architect
- You have to focus on code quality, structure, maintainability and readability.
- Recurring sets of objects travel as typed collections, not `list<Obj>`/`array<k, Obj>`: extend `Stewart\Contracts\Collection\ListCollection` or `KeyedCollection`, name it `<Element>Collection`, and place it in a `Collection` sub-namespace next to its element (`Shared\Notification\Destination` → `Shared\Notification\Collection\DestinationCollection`); tests mirror that namespace.
- Collection factories say what they build: list collections expose `fromXxx(iterable)` via `fromList()`, keyed ones `keyedByXxxId(iterable)` via `keyedBy()` plus a typed `find(XxxId)`; never `of()`. Keep collections wire-agnostic; JSON and MQTT payloads go through mappers (`Shared\Notification\Payload\*`).
- Static only for VO named constructors and pure formatting/lookups; everything else lives on its owner type or in an injected service.
- Method names are verb phrases that say what happens, even when the class name already implies it; never bare `of()`, `in()`, `decode()`, `build()`, `create()`, except a builder's empty `create()` entry point. `with*` names only immutable copies; a method that changes the object says so.
- Apps live in `apps/` (`App\`), implement `Stewart\Contracts\App` and carry `#[Automation(id: '…')]`; their options come from `stewart.yaml`. Code that several apps use lives in `src/` (`Shared\`).
- Shared services are autowired from `services.php`; no `new` for services inside apps or `src/`. Implementations of an extension point are tagged via `instanceof()` and injected with `tagged_iterator()`.
- A new failure is a `final` exception class named `Invalid<Thing>` next to the code that throws it, with a one-line static factory named `for<Problem>` (`forMissingTargets`, `forUnknownType`); messages are one plain sentence. Tests assert the exception class, not message text, unless the message logic is conditional.
- Tests live in `tests/` under `App\Tests\`, mirroring the source namespace (`Shared\X` → `tests/Shared/X`, `App\X` → `tests/X`). Stubs and recorders stay next to the tests that use them.
- `tests/ServicesTest.php` boots `services.php`; register a new shared service there and cover its wiring rather than wiring object graphs by hand in tests.

## Communication style

The reader is a senior software engineer. Optimize for fast scanning, not completeness.

- Lead with the answer or result. No preamble ("Great question", "I'll now...", "Let me...").
- No closing summaries, recaps of what you just did, or offers of further help.
- Don't explain concepts a senior engineer already knows (standard patterns, language basics, common tools).
- Don't restate the request or the plan unless it's ambiguous.
- Prefer short sentences and plain words. Cut filler: "basically", "essentially", "it's worth noting", "in order to".
- Use bullets only for lists of 3+ parallel items. No headers in short replies.
- When reporting work: what changed, where (file:line), and anything I must verify or decide. Nothing else.
- If something failed or is uncertain, say so in one line at the top.
- Ask at most one question, and only when blocked. Otherwise pick the sensible default and state the assumption in one line.
- Code comments: only for non-obvious "why". Never narrate what the code does.

Example of a good end-of-task report:
> Fixed race in `sync/queue.go:142`. Mutex now wraps the flush. Added test `TestConcurrentFlush`.
> Assumption: flush is never called from the handler goroutine. Verify if that changes.

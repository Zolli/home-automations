# House mode

`day-night-mode` and `sleep-mode` each own two `ModeTransition`s (`day`/`night`, `activation`/`deactivation`). A
transition fires through named strategies (`ModeTrigger`s); the app then switches its Home Assistant helper and
publishes a mode event. Options and the strategy reference: [README › House modes](../../README.md#house-modes).

| Diagram | Shows |
|---|---|
| [structure.puml](structure.puml) | Classes, including the trigger type registry |
| [trigger-lifecycle.puml](trigger-lifecycle.puml) | `ModeTrigger` attach, arm, disarm and detach |
| [transition-sequence.puml](transition-sequence.puml) | Calls on start, strategy switch, firing and stop |

## Configuration

`ModeTransitionMapper` builds each transition in the app constructor:

- `ModeTriggerFactory` reads each strategy as `TypedOptions`, checks that its `name` fits an entity key and dispatches
  on `type` through `ModeTriggerTypeCollection`.
- Names are unique across `strategies` and `alwaysActive`, since they key the exposed entities.
- `defaultStrategy` must name a strategy; `window` needs different `from` and `until` times.
- An invalid entry fails app construction with `InvalidModeConfig`, naming the transition.

## Runtime

- **Context.** The app builds a `ModeContext`; each transition wraps it in a `TransitionContext`, which exposes
  `<transition>_strategy` and `<transition>_<strategy>_time|threshold` on the app's device.
- **Attach.** Every trigger attaches first. `fixed_time` exposes a time entity and `above`/`below` a number entity,
  seeded from the options only while Home Assistant has no value.
- **Arm.** `alwaysActive` triggers are armed at once; the strategy select arms one more. An unknown restored option
  falls back to `defaultStrategy`, and selecting an unconfigured one is rejected.
- **Retune.** Changing an exposed time or threshold re-arms the trigger with the new value.
- **Fire.** A firing outside `window` is logged and dropped; otherwise the app gets the strategy name as the cause.
- **Switch.** `DayNightModeApp` always selects the option; `SleepModeApp` skips when the switch is already in that
  state. Both publish `{event, data: {cause}, meta: {producer}}` to their topic.

## Extension point

A strategy type implements `Shared\HouseMode\Trigger\ModeTriggerType`, returns a `ModeTrigger` and is tagged
`shared.mode_trigger_type` by its interface. A type with constructor arguments, like `ThresholdTriggerType` per
`ThresholdDirection`, is excluded from the bulk load and registered once per variant in `services.php`.

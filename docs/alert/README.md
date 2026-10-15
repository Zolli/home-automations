# Alert

`home-alert` watches Home Assistant states and MQTT topics, raises a fault when a rule holds long enough, runs
mitigations and escalates notifications until someone acknowledges or the fault clears. Options and the rule, signal
and mitigation reference: [README › Alerts](../../README.md#alerts).

| Diagram | Shows |
|---|---|
| [structure.puml](structure.puml) | Runtime classes and their relations |
| [configuration.puml](configuration.puml) | How the app options become an `AlertDefinition` |
| [lifecycle.puml](lifecycle.puml) | `FaultWatcher` and `MonitoredAlert` states |
| [raise-sequence.puml](raise-sequence.puml) | Calls on fault, acknowledgement and recovery |

## Configuration

`HomeAlertApp` maps its options once, in its constructor, through `AlertConfigMapper`:

- `EscalationPolicyMapper` builds the named `policies`. Steps use the notification destination and condition formats;
  signal steps go through `SignalFactory`.
- `AlertRuleFactory`, `MitigationFactory` and `SignalFactory` read each entry as `TypedOptions` and dispatch on its
  `type` through a `NamedTypeCollection` of tagged `*Type` services.
- An invalid entry fails app construction with `InvalidAlertConfig`, naming the alert or policy.

## Runtime

`initialize()` starts one `MonitoredAlert` per definition through `AlertMonitor`; `dispose()` stops them all.

- **Readings.** `ReadingFeed` subscribes to the rule's entities and topics and builds a `ReadingSnapshot` on demand:
  current states for entities, the last payload since start for topics.
- **Evaluation.** `FaultWatcher` evaluates the rule at start, on every reading change and, for a
  `RecheckedAlertRule` such as `silent`, on a timer. A held fault must still be found when `forSeconds` elapses. Once
  raised, only `isRecovered()` clears it, which lets threshold rules apply hysteresis.
- **Re-entrancy.** Mitigations change watched entities while `raiseFault()` runs; those changes are evaluated after it
  returns.
- **Raise.** `MonitoredAlert` renders the title and message (`{area}` comes from `FaultLocator`), runs mitigations,
  appends their results to the body and starts an `Escalation`.
- **Escalation.** Each step runs at its `afterSeconds`: a notification with an Acknowledge button when it has a
  non-spoken destination, or a signal. `StepRepeater` resends it while `repeat` allows.
- **Acknowledge and clear.** Acknowledging stops the escalation and moves the alert to `Acknowledged`; clearing stops
  it and returns to `Idle`. Both send a follow-up to the non-spoken destinations already notified.

## Extension points

| Add | Implement | Tag |
|---|---|---|
| Rule | `Shared\Alert\Rule\AlertRuleType`, returning an `AlertRule` or a `RecheckedAlertRule` for timed checks | `shared.alert_rule_type` |
| Mitigation | `Shared\Alert\Mitigation\MitigationType`, returning a `Mitigation` | `shared.alert_mitigation_type` |
| Signal | `Shared\Alert\Signal\SignalType`, returning a `Signal` | `shared.alert_signal_type` |

Types in `src/` are tagged by their interface. A type with constructor arguments, like `ThresholdRuleType` per
`ThresholdDirection`, is excluded from the bulk load and registered once per variant in `services.php`.

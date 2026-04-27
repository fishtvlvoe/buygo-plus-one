## ADDED Requirements

### Requirement: Assessment Report Structure

The assessment report SHALL document each BuyGo+1 Service class evaluated against FluentCart upstream mechanisms using a standardized structure, so that downstream implementation changes can reference it without re-discovery.

Each evaluated item in the report MUST include all of the following fields:

- **Current State**: file path, line count, core responsibility, and external dependencies
- **Upstream Mechanism**: FluentCart version introducing it, API surface (functions/hooks/endpoints), and whether Pro license is required
- **Gap Analysis**: feature-by-feature comparison table (covered / partially covered / missing)
- **Migration Strategy**: at least one candidate strategy with estimated line reduction and prerequisites
- **Risks**: ranked list with mitigation for each risk
- **ROI Score**: numerical score combining line reduction, migration cost, and risk

#### Scenario: Report covers every evaluated service

- **WHEN** the assessment report is produced
- **THEN** every service listed in proposal Impact section MUST appear in the report with all required fields populated

##### Example: coverage audit across proposal services

| Service in proposal Impact | Current State | Upstream Mechanism | Gap Analysis | Migration Strategy | Risks | ROI Score | Pass |
|---|---|---|---|---|---|---|---|
| class-allocation-service.php | filled | filled | filled | filled | filled | 9.2 | PASS |
| class-shipping-status-service.php | filled | filled | filled | filled | filled | 6.5 | PASS |
| class-order-shipping-manager.php | filled | filled | filled | filled | filled | 6.5 | PASS |
| class-line-flex-templates.php | filled | filled | filled | filled | filled | 3.1 | PASS |
| class-line-product-creator.php | missing | missing | missing | missing | missing | — | FAIL |

- **GIVEN** the audit table above
- **WHEN** any row is `FAIL`
- **THEN** the report MUST be regenerated to fill the missing row before Decision Framework Output can proceed

#### Scenario: Report flags missing prerequisites

- **WHEN** a migration strategy depends on a prerequisite not yet confirmed (e.g., Pro license, upstream version)
- **THEN** the report MUST mark the item as `BLOCKED_BY_PREREQUISITE` and list the specific prerequisite

### Requirement: Open Questions Registry

The assessment report SHALL maintain a numbered Open Questions list, so that each uncertainty blocking a decision is tracked explicitly and verifiable.

Each Open Question MUST include:

- Question text (phrased to allow yes/no or factual answer)
- Blocking impact (which evaluated item becomes invalid if unresolved)
- Proposed verification method (code inspection / live test / upstream docs / license check)

#### Scenario: Unresolved question blocks downstream implementation

- **WHEN** an Open Question remains unanswered
- **THEN** any implementation change referencing the affected item MUST cite the unresolved question and justify why implementation can still proceed, or declare itself BLOCKED

##### Example: downstream gating matrix

| Open Question | Status | Blocks Item | Downstream Change Cites It | Decision |
|---|---|---|---|---|
| Q1: Does deployment hold FluentCart Pro license? | RESOLVED (yes) | D1 AllocationService | migrate-allocation-to-fc-ledger cites Q1 | PROCEED |
| Q2: Pro Inventory API endpoint URL? | UNRESOLVED | D1 AllocationService | migrate-allocation-to-fc-ledger does NOT cite Q2 | BLOCKED |
| Q3: Does $order->packing inherit to split children? | RESOLVED (no) | D2 ShippingStatusService | align-shipping-with-packing cites Q3 and picks fallback strategy B | PROCEED |
| Q5: R2/S3 supports dynamic LINE images? | UNRESOLVED | D4 LINE media | line-media-to-r2 neither cites nor has fallback | BLOCKED |

- **GIVEN** the matrix above
- **WHEN** a row is `UNRESOLVED` and the downstream change neither cites nor provides fallback
- **THEN** the downstream change MUST be marked BLOCKED and cannot enter the apply phase

### Requirement: Decision Framework Output

The assessment report SHALL produce a single ranked table that maps each evaluated item to one of three decisions: `ADOPT`, `DEFER`, or `REJECT`, so that reviewers can approve or reject the overall assessment in one pass.

The decision table MUST sort items by ROI score descending and include the recommended next-action change name (if ADOPT) or next-review trigger (if DEFER).

#### Scenario: Reviewer approves the assessment

- **WHEN** the ranked decision table contains at least one `ADOPT` item with all prerequisites confirmed
- **THEN** the report is ready for reviewer approval and the recommended implementation change name becomes the next proposal to create

##### Example: decision table row format

| Item | Current Lines | Est. Reduction | Decision | Next Action |
|------|---------------|----------------|----------|-------------|
| AllocationService vs Pro Inventory Ledger | 1024 | ~300 | ADOPT | Create change `migrate-allocation-to-fc-ledger` |
| BatchCreateService | N/A | N/A | DEFER | Re-evaluate when FluentCart 1.3.23 releases batch API |

### Requirement: Assessment Scope Discipline

The assessment report SHALL NOT contain executable code, migration scripts, or spec delta proposals, so that the assessment remains a pure decision artifact and cannot be mistaken for an implementation change.

#### Scenario: Assessment tries to include implementation details

- **WHEN** an evaluator adds code snippets longer than 5 lines or pseudo-migration scripts
- **THEN** the content MUST be moved into a follow-up implementation change and replaced with a reference link in the assessment report

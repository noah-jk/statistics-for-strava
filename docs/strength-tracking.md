# Strength Tracking

Strength tracking parses workout descriptions from WeightTraining activities on Strava and
stores per-set data (exercise, sets, reps, weight, estimated 1RM) for display on the
activity detail page and the Strength Stats dashboard widget.

---

## Description Format

Strength data is read from the **description field** of any WeightTraining activity synced
from Strava.

### Syntax

```
{Exercise Name} {sets}x{reps}
{Exercise Name} {sets}x{reps}@{weight}
{Exercise Name} {sets1}x{reps1}@{weight1}, {sets2}x{reps2}@{weight2}
```

- **Exercise name** — one or more words starting with a letter (`A-Za-z`), followed by
  alphanumerics and spaces. Case-sensitive; the name is stored exactly as written.
- **Sets × Reps** — both must be positive integers (no zeros, no leading zeros).
- **Weight** — optional, in **lbs**. Decimals are supported (`225.5`).
- **Bodyweight** — omit the `@weight` part entirely.
- **Multiple set tokens** — comma-separate them on one line after the exercise name.

### Examples

```
Squat 1x1@365
Squat 1x1@365, 2x3@295, 3x5@255
Bench Press 4x8@185
Deadlift 3x3@315
Pull Up 3x10
Overhead Press 3x5@135.5
```

A description can mix strength lines with regular prose — any line that doesn't match
the format is silently ignored:

```
Morning session, felt good.
Squat 3x5@275
notes: knees tracking well
Bench Press 4x8@185
```

Only the Squat and Bench Press lines produce strength set rows.

### Epley 1RM Estimate

For every weighted set, the app stores an estimated one-rep max using the Epley formula:

```
Estimated 1RM = weight × (1 + reps / 30)
```

Bodyweight sets have `estimatedOneRepMax = NULL`.

---

## Automatic Processing

Strength sets are parsed and stored automatically as part of the normal import pipeline
(`app:strava:import-data`). Each WeightTraining activity without existing strength rows
is processed on every import run — there is nothing extra to configure for new activities.

---

## Configuration

Add the following section to `config/app/config-athlete.yaml` to configure which exercises
appear as PR summary boxes on the Strength Stats dashboard widget:

```yaml
strength:
  primaryLifts:
    - 'Squat'
    - 'Bench Press'
    - 'Deadlift'
```

- Lifts are displayed in the order listed.
- If no data exists for a configured lift, the box shows `—`.
- The list can contain any exercise name, not just powerlifting movements.
- If this section is absent, the widget still renders; the PR boxes use an empty list.

> [!NOTE]
> The exercise names here must match exactly what is written in your Strava descriptions.
> `Bench` and `Bench Press` are treated as different exercises.

---

## Dashboard Widget

The **Strength Stats** widget (`strengthStats`) shows:

- **PR summary row** — all-time best estimated 1RM for each configured `primaryLift`.
- **Lifts tab** — line chart of daily best estimated 1RM per exercise over the past year.
- **Total tab** — bar chart of weekly SBD (Squat + Bench Press + Deadlift) totals;
  only weeks where all three lifts have data are plotted.

The widget returns nothing (is hidden) when no strength set rows exist in the database.

To place it in the dashboard layout, add to `config/app/config.yaml`:

```yaml
appearance:
  dashboard:
    layout:
      - widget: strengthStats
        width: 50
        enabled: true
```

---

## Backfill Command

Run this command to parse strength sets for WeightTraining activities that existed before
the feature was introduced, or that were skipped during a previous import run:

```bash
make console arg="app:strava:backfill-strength-sets"
```

The command is **idempotent** — activities that already have strength rows are skipped.
Activities whose descriptions contain no parseable strength lines are also skipped (they
will be re-checked on the next run, cheaply).

Typical use cases:
- First-time setup: you have historical WeightTraining activities with descriptions.
- After fixing a description typo that previously prevented parsing.

---

## Manual Data Entry

To insert a historical PR directly (e.g. a meet result not logged in Strava), use the
SQLite shell on the VM:

```bash
sqlite3 /path/to/storage/database/strava.db
```

Activity IDs in the database are **prefixed** with `activity-`:

```sql
INSERT INTO ActivityStrengthSet
    (activityId, position, exerciseName, numberOfSets, numberOfReps, weightLbs, estimatedOneRepMax)
VALUES
    ('activity-12345678901', 1, 'Squat',      1, 1, 405.0, 405.0 * (1 + 1/30.0)),
    ('activity-12345678901', 2, 'Bench Press', 1, 1, 242.0, 242.0 * (1 + 1/30.0)),
    ('activity-12345678901', 3, 'Deadlift',   1, 1, 500.0, 500.0 * (1 + 1/30.0));
```

Replace `12345678901` with the actual Strava activity ID. `position` must be unique per
activity and determines display order. After inserting, rebuild the static output:

```bash
make console arg="app:strava:build-app"
make app-build-all
```

### Table Schema

```sql
CREATE TABLE ActivityStrengthSet (
    activityId         VARCHAR(255)     NOT NULL,
    position           INTEGER          NOT NULL,
    exerciseName       VARCHAR(255)     NOT NULL,
    numberOfSets       INTEGER          NOT NULL,
    numberOfReps       INTEGER          NOT NULL,
    weightLbs          DOUBLE PRECISION DEFAULT NULL,
    estimatedOneRepMax DOUBLE PRECISION DEFAULT NULL,
    PRIMARY KEY (activityId, position)
);
```

---

## Supported Exercises

Any exercise name the parser can extract from a description is stored verbatim. There is
no fixed list — exercises are whatever you write in your Strava descriptions.

To see every unique exercise name currently in the database:

```bash
sqlite3 /path/to/storage/database/strava.db \
    "SELECT DISTINCT exerciseName, COUNT(*) AS sets
     FROM ActivityStrengthSet
     GROUP BY exerciseName
     ORDER BY sets DESC;"
```

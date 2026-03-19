# B Team Structure — Full Implementation Plan

## Overview

This plan adds a complete B team (reserve team / filial) system to VirtuaFC. B teams are non-competing squads attached to their parent A team. They hold real players seeded from Transfermarkt data, develop autonomously via the existing `PlayerDevelopmentService`, and receive synthetic youth intake each season — mirroring how real Spanish reserve teams function as the bridge between the academy and the first team.

The plan is split into two parts:

- **Part 1: B Team Infrastructure** — Seeding real B team rosters, autonomous development, and seasonal youth intake
- **Part 2: Call-Up System** — Temporary matchday call-ups from B team to A team

---

## Part 1: B Team Infrastructure

### Current State

The codebase already has partial reserve team support:

| What exists | Where |
|-------------|-------|
| `Team.parent_team_id` column + `isReserveTeam()`, `parentTeam()`, `reserveTeam()` methods | `app/Models/Team.php` |
| `linkReserveTeams()` in seeder — reads config and sets `parent_team_id` | `app/Console/Commands/SeedReferenceData.php` |
| `config/countries.php` `reserve_teams` mapping (only Real Sociedad B → Real Sociedad) | `config/countries.php` |
| `ReserveTeamFilter` — prevents promotion when parent is in same division | `app/Modules/Competition/Services/ReserveTeamFilter.php` |
| ESP2 data includes Real Sociedad B with 24 real players | `data/2025/ESP2/teams.json` |

**What's missing:**

1. B teams are seeded as regular competition teams — their players enter the game as ESP2 squad members, not as reserve squads attached to the parent club
2. No mechanism to create B team `GamePlayer` records for the user's team specifically
3. No autonomous development or youth intake for B teams
4. Only 1 reserve team mapped in config (Real Sociedad B); other Spanish clubs with real filiales are not mapped
5. No UI to view or manage B team players

### Design Decisions

**B teams do NOT compete in any league.** They exist purely as player development pools. Their players:
- Are seeded from real Transfermarkt data (where available)
- Develop each season via `PlayerDevelopmentService`
- Receive synthetic youth intake each season (scaled by academy investment tier)
- Can be called up to the A team for matchdays (Part 2)
- Can be promoted permanently to the A team

**Only the user's team gets a B team.** AI teams already get youth intake via `SquadReplenishmentProcessor` — giving them B teams would add complexity with no gameplay value.

---

### Step 1.1: Expand Reserve Team Config

**File:** `config/countries.php`

Expand the `reserve_teams` mapping with all Spanish clubs that have real B teams in the data. For clubs whose B team is NOT in ESP2 data (most of them), we'll create B team data files.

```php
'reserve_teams' => [
    9899 => 681,   // Real Sociedad B → Real Sociedad
    // Add more as B team data files are created
],
```

Additionally, add a new `b_team` config section per country that controls B team behavior:

```php
'b_team' => [
    'enabled' => true,
    'initial_squad_size' => 20,      // target squad size at game start
    'min_squad_size' => 15,          // minimum maintained by youth intake
    'max_squad_size' => 25,          // cap before intake skips
    'youth_intake_min' => 2,         // per season
    'youth_intake_max' => 4,         // per season (scales with academy tier)
    'ability_factor_min' => 0.45,    // relative to A team average
    'ability_factor_max' => 0.65,
    'age_range' => [17, 22],         // B team player ages
],
```

---

### Step 1.2: Create B Team Data Files

**Directory:** `data/2025/ESPB/` (new)

For clubs that have real B teams in Transfermarkt but whose filiales are NOT already in ESP2 data, create individual JSON files following the existing team pool format:

```
data/2025/ESPB/
├── 418.json    # Real Madrid Castilla
├── 131.json    # FC Barcelona Atlètic
├── 621.json    # Athletic Club B (Bilbao Athletic)
├── 150.json    # Real Betis Deportivo
├── 1050.json   # Villarreal CF B
├── ...etc
```

Each file follows the existing EUR team pool format:

```json
{
  "id": "10083",
  "parentId": "418",
  "name": "Real Madrid Castilla",
  "image": "https://tmssl.akamaized.net/images/wappen/big/10083.png",
  "country": "ES",
  "players": [
    {
      "id": "12345",
      "name": "Player Name",
      "position": "Centre-Back",
      "number": "4",
      "dateOfBirth": "Jan 1, 2004",
      "nationality": ["Spain"],
      "height": "1,82m",
      "foot": "right",
      "marketValue": "€500k",
      "contract": "Jun 30, 2027"
    }
  ]
}
```

The `parentId` field (new) maps the B team to its parent's transfermarkt ID.

**For Real Sociedad B:** Already in ESP2 data. During seeding, its players will be extracted from ESP2 and treated as B team players rather than league competitors.

**Scraping:** These files need to be populated from Transfermarkt. This is a data task, not a code task.

---

### Step 1.3: Seed B Team Reference Data

**File:** `app/Console/Commands/SeedReferenceData.php`

Add a new method `seedBTeams(string $countryCode)` called during country seeding:

1. Read all JSON files from `data/{season}/ESPB/`
2. For each file:
   - Create `Team` record with `parent_team_id` set (using `parentId` from JSON)
   - Create `Player` records for each player (same logic as `seedPlayersFromTeams()`)
   - Do NOT create `CompetitionTeam` entries — B teams don't compete
3. Also update `linkReserveTeams()` to handle the new data

**Important:** B team players should be added to `game_player_templates` so they're fast-loaded during game creation. Update `GamePlayerTemplateService::generateTemplates()` to process B team data files.

---

### Step 1.4: Initialize B Team Players During Game Setup

**File:** `app/Modules/Season/Jobs/SetupNewGame.php`

Add a new step after game players are initialized:

```php
// Step 2b: Initialize B team players for the user's team
$this->initializeBTeamPlayers($allTeams, $allPlayers, $contractService, $developmentService);
```

**Logic:**

1. Find the user's team: `Team::find($this->teamId)`
2. Check if the team has a reserve team: `$team->reserveTeam`
3. If yes, load B team data from templates or JSON files
4. Create `GamePlayer` records with `team_id` = B team's ID
5. If no real B team data exists, generate a synthetic squad using `PlayerGeneratorService`:
   - Size: `b_team.initial_squad_size` from config (default 20)
   - Ages: 17-22 (weighted younger)
   - Abilities: 45%-65% of A team average
   - Positions: balanced using `YOUTH_POSITION_WEIGHTS`

**Synthetic generation for clubs without data files:**

Not every club has a well-known B team. For these clubs, generate a complete synthetic B team:

```php
private function generateSyntheticBTeam(Game $game, Team $parentTeam, string $season): void
{
    // Create a B team Team record if one doesn't exist
    $bTeam = Team::firstOrCreate(
        ['parent_team_id' => $parentTeam->id, 'type' => 'club'],
        [
            'name' => $parentTeam->name . ' B',
            'country' => $parentTeam->country,
            'stadium_name' => $parentTeam->stadium_name,
            'stadium_seats' => 5000,
            'image' => $parentTeam->image,
        ]
    );

    // Generate players using PlayerGeneratorService
    $aTeamAvg = $this->calculateATeamAverage($game, $parentTeam->id);
    $config = config("countries.{$parentTeam->country}.b_team");

    for ($i = 0; $i < $config['initial_squad_size']; $i++) {
        // ... generate player with age 17-22, ability 45-65% of A team
    }
}
```

---

### Step 1.5: B Team Season Processor — Development & Youth Intake

**File:** `app/Modules/Season/Processors/BTeamDevelopmentProcessor.php` (new)

A new `SeasonProcessor` for the **closing pipeline** (priority ~9, after `SquadReplenishmentProcessor` at 8):

```php
class BTeamDevelopmentProcessor implements SeasonProcessor
{
    public function priority(): int { return 9; }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Only process if user's team has a B team
        $userTeam = Team::find($game->team_id);
        $bTeam = $userTeam->reserveTeam;
        if (!$bTeam) {
            return $data;
        }

        $bTeamPlayers = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $bTeam->id)
            ->get();

        // 1. Develop existing B team players
        foreach ($bTeamPlayers as $player) {
            $development = $this->developmentService->calculateDevelopment($player);
            $player->update($development);
        }

        // 2. Remove aged-out players (23+) — they become free agents
        $this->ageOutPlayers($game, $bTeamPlayers);

        // 3. Youth intake — generate new young players
        $intakeCount = $this->calculateYouthIntake($game);
        $this->generateYouthIntake($game, $bTeam, $intakeCount, $data->newSeason);

        // 4. Maintain minimum squad size
        $this->ensureMinimumSquadSize($game, $bTeam, $data->newSeason);

        return $data;
    }
}
```

**Youth intake scales with academy investment:**

| Academy Tier | Youth Intake | Ability Range (% of A team) |
|-------------|-------------|----------------------------|
| 0 | 0 (no B team) | — |
| 1 | 2 | 40%-55% |
| 2 | 3 | 45%-60% |
| 3 | 3-4 | 50%-65% |
| 4 | 4 | 55%-70% |

**Age-out rule:** B team players who turn 23 during the season are released (set `team_id = null`). The user should promote promising players before they age out. A notification warns the user before this happens.

---

### Step 1.6: B Team View

**File:** `app/Http/Views/ShowBTeam.php` (new)

Prepares B team roster data for the Blade template:

```php
class ShowBTeam
{
    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);
        $userTeam = Team::findOrFail($game->team_id);
        $bTeam = $userTeam->reserveTeam;

        if (!$bTeam) {
            abort(404);
        }

        $players = GamePlayer::with(['player', 'activeCallUp'])
            ->where('game_id', $gameId)
            ->where('team_id', $bTeam->id)
            ->get();

        // Group by position, calculate stats
        $grouped = $this->groupByPosition($players);
        $kpis = $this->calculateKpis($players);

        return view('views.b-team', compact('game', 'bTeam', 'grouped', 'kpis'));
    }
}
```

**Route:**
```php
Route::get('/game/{gameId}/b-team', ShowBTeam::class)->name('game.b-team');
```

---

### Step 1.7: Promote Player to A Team

**File:** `app/Http/Actions/PromoteBTeamPlayer.php` (new)

Permanent move from B team to A team (unlike call-ups which are temporary):

```php
class PromoteBTeamPlayer
{
    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::where('game_id', $gameId)->findOrFail($playerId);

        // Validate player is on user's B team
        $bTeam = Team::find($game->team_id)->reserveTeam;
        if (!$bTeam || $player->team_id !== $bTeam->id) {
            abort(403);
        }

        // Move to A team
        $player->update([
            'team_id' => $game->team_id,
            'number' => GamePlayer::nextAvailableNumber($gameId, $game->team_id),
        ]);

        return redirect()->route('game.b-team', $gameId)
            ->with('success', __('messages.player_promoted', ['player' => $player->player->name]));
    }
}
```

**Route:**
```php
Route::post('/game/{gameId}/b-team/promote/{playerId}', PromoteBTeamPlayer::class)
    ->name('game.b-team.promote');
```

---

### Step 1.8: Send Player Down to B Team

**File:** `app/Http/Actions/SendPlayerToBTeam.php` (new)

Move an A team player down to the B team (reverse of promote):

```php
class SendPlayerToBTeam
{
    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::where('game_id', $gameId)->findOrFail($playerId);

        // Validate player is on user's A team
        if ($player->team_id !== $game->team_id) {
            abort(403);
        }

        $bTeam = Team::find($game->team_id)->reserveTeam;
        if (!$bTeam) {
            abort(404);
        }

        // Move to B team
        $player->update([
            'team_id' => $bTeam->id,
            'number' => GamePlayer::nextAvailableNumber($gameId, $bTeam->id),
        ]);

        return redirect()->route('game.squad', $gameId)
            ->with('success', __('messages.player_sent_to_b_team', ['player' => $player->player->name]));
    }
}
```

---

### Step 1.9: Translations

**Files:** `lang/en/messages.php`, `lang/es/messages.php`, `lang/en/squad.php`, `lang/es/squad.php`

```php
// English messages
'player_promoted' => ':player has been promoted to the first team.',
'player_sent_to_b_team' => ':player has been sent to the B team.',
'player_called_up' => ':player has been called up to the first team.',
'player_returned_to_b_team' => ':player has been returned to the B team.',

// Spanish messages
'player_promoted' => ':player ha sido ascendido al primer equipo.',
'player_sent_to_b_team' => ':player ha sido enviado al equipo B.',
'player_called_up' => ':player ha sido convocado al primer equipo.',
'player_returned_to_b_team' => ':player ha vuelto al equipo B.',

// English squad
'b_team' => 'B Team',
'b_team_roster' => 'B Team Roster',
'promote_to_first_team' => 'Promote',
'send_to_b_team' => 'Send to B Team',
'call_up' => 'Call Up',
'return_to_b_team' => 'Return to B Team',
'ages_out_end_of_season' => 'Ages out at end of season',

// Spanish squad
'b_team' => 'Equipo B',
'b_team_roster' => 'Plantilla del Equipo B',
'promote_to_first_team' => 'Ascender',
'send_to_b_team' => 'Enviar al equipo B',
'call_up' => 'Convocar',
'return_to_b_team' => 'Devolver al equipo B',
'ages_out_end_of_season' => 'Sale al final de la temporada',
```

---

### Step 1.10: Navigation

Add "B Team" link to the game navigation (both desktop and mobile drawer) in `game-header.blade.php`. Only show if the user's team has a reserve team.

---

## Part 2: Call-Up System

Call-ups allow temporary matchday-level player movement from B team to A team. They reuse the existing `Loan` mechanism.

### Step 2.1: Migration — Add `type` column to `loans`

**File:** `database/migrations/2026_03_19_000001_add_type_to_loans_table.php`

```php
Schema::table('loans', function (Blueprint $table) {
    $table->string('type')->default('loan')->after('status');
});
```

Values: `'loan'` (existing behavior) or `'call_up'`.

---

### Step 2.2: Update `Loan` Model

**File:** `app/Models/Loan.php`

- Constants: `TYPE_LOAN = 'loan'`, `TYPE_CALL_UP = 'call_up'`
- Add `'type'` to `$fillable`
- Method: `isCallUp(): bool`
- Scope: `scopeCallUps($query)` — `where('type', self::TYPE_CALL_UP)`
- Scope: `scopeLoans($query)` — `where('type', self::TYPE_LOAN)`

---

### Step 2.3: `GamePlayer::activeCallUp()` Relationship

**File:** `app/Models/GamePlayer.php`

```php
public function activeCallUp(): HasOne
{
    return $this->hasOne(Loan::class, 'game_player_id')
        ->where('status', Loan::STATUS_ACTIVE)
        ->where('type', Loan::TYPE_CALL_UP);
}

public function isOnCallUp(): bool
{
    return $this->activeCallUp()->exists();
}
```

---

### Step 2.4: `LoanService` — Call-Up Methods

**File:** `app/Modules/Transfer/Services/LoanService.php`

#### `createCallUp(Game $game, GamePlayer $player, Team $aTeam): Loan`

1. Validate: player is on A team's B team, not injured, no active loan/call-up
2. Create `Loan` with `type = TYPE_CALL_UP`, `parent_team_id = B team`, `loan_team_id = A team`
3. Update `GamePlayer.team_id` to A team
4. Assign squad number via `GamePlayer::nextAvailableNumber()`

#### `returnCallUps(Game $game): void`

1. Find active call-ups: `Loan::where('game_id', ...)->active()->callUps()->get()`
2. For each: reset `team_id` to `parent_team_id`, mark as `completed`
3. Reassign squad number at B team

#### `getActiveCallUps(Game $game): Collection`

Returns active call-ups for UI display.

Update `returnAllLoans()` and `getActiveLoans()` to scope with `->loans()` so they exclude call-ups.

---

### Step 2.5: Auto-Return After Matchday

**File:** `app/Modules/Match/Services/MatchdayOrchestrator.php`

Inject `LoanService` into the constructor. In `advance()`, after the `DB::transaction` block:

```php
$result = DB::transaction(function () use ($game) {
    // ... existing matchday processing ...
});

// Return matchday call-ups after the batch completes
$this->loanService->returnCallUps($game);

// Dispatch career actions ...
```

---

### Step 2.6: Action Classes & Routes

**New files:**

| File | Purpose |
|------|---------|
| `app/Http/Actions/CallUpBTeamPlayer.php` | POST handler for call-up |
| `app/Http/Actions/ReturnCalledUpPlayer.php` | POST handler for manual return |

**Routes:**
```php
Route::post('/game/{gameId}/b-team/call-up/{playerId}', CallUpBTeamPlayer::class)
    ->name('game.b-team.call-up');
Route::post('/game/{gameId}/b-team/return-call-up/{playerId}', ReturnCalledUpPlayer::class)
    ->name('game.b-team.return-call-up');
```

---

### Step 2.7: UI Integration

**B Team View:** Each player row shows a "Call Up" button (disabled if injured or already called up).

**A Team Squad View (`ShowSquad`):** Called-up players display a "B" badge and a "Return" button. Eager-load `activeCallUp` relationship.

---

## Why Zero Changes to the Match Pipeline

Both call-ups and permanent promotions simply change `GamePlayer.team_id`. The entire match simulation chain — `LineupService`, `MatchdayOrchestrator::processBatch()`, `SubstitutionService`, `MatchSimulator` — filters players by `team_id`. No conditional logic is needed anywhere in the simulation path.

---

## Edge Cases

| Scenario | Behavior |
|----------|----------|
| Called-up player gets injured | Injury applied to `GamePlayer`. Auto-returns after matchday. Shows as injured on B team. |
| Called-up player gets red/yellow card | Suspension tracked per-competition. Only affects future call-ups to same competition. |
| B team player ages out (turns 23) at season end | Released as free agent. User should promote before season close. Warning notification sent. |
| User has no reserve team in data | Synthetic B team generated at game start using `PlayerGeneratorService`. |
| Player sent to B team and then called up same matchday | Works fine — they're on B team, call-up moves them to A team temporarily. |
| Season ends with active call-ups | `returnCallUps()` runs after every matchday. `returnAllLoans()` (scoped to `loans()`) is safety net. |
| User promotes all B team players | Youth intake at season end will replenish. Minimum squad size enforced. |
| Academy tier 0 | No B team created. The feature requires at least tier 1 investment. |

---

## File Summary

### Part 1: B Team Infrastructure

| File | Change Type |
|------|-------------|
| `config/countries.php` | Modify (expand `reserve_teams`, add `b_team` config) |
| `data/2025/ESPB/*.json` | **New** (B team player data files) |
| `app/Console/Commands/SeedReferenceData.php` | Modify (add `seedBTeams()` method) |
| `app/Modules/Season/Services/GamePlayerTemplateService.php` | Modify (process B team data) |
| `app/Modules/Season/Jobs/SetupNewGame.php` | Modify (initialize B team players) |
| `app/Modules/Season/Processors/BTeamDevelopmentProcessor.php` | **New** (season closing processor) |
| `app/Http/Views/ShowBTeam.php` | **New** |
| `resources/views/views/b-team.blade.php` | **New** |
| `app/Http/Actions/PromoteBTeamPlayer.php` | **New** |
| `app/Http/Actions/SendPlayerToBTeam.php` | **New** |
| `routes/web.php` | Modify (3 routes) |
| `lang/en/messages.php`, `lang/es/messages.php` | Modify |
| `lang/en/squad.php`, `lang/es/squad.php` | Modify |
| `resources/views/components/game-header.blade.php` | Modify (nav link) |

### Part 2: Call-Up System

| File | Change Type |
|------|-------------|
| `database/migrations/2026_03_19_000001_add_type_to_loans_table.php` | **New** |
| `app/Models/Loan.php` | Modify (constants, scopes) |
| `app/Models/GamePlayer.php` | Modify (relationship) |
| `app/Modules/Transfer/Services/LoanService.php` | Modify (3 new methods + scope existing) |
| `app/Modules/Match/Services/MatchdayOrchestrator.php` | Modify (inject LoanService, add return call) |
| `app/Http/Actions/CallUpBTeamPlayer.php` | **New** |
| `app/Http/Actions/ReturnCalledUpPlayer.php` | **New** |
| `routes/web.php` | Modify (2 routes) |
| `app/Http/Views/ShowSquad.php` | Modify (eager-load call-up flag) |

---

## Implementation Order

1. **Data first:** Create `data/2025/ESPB/` JSON files (scraping task)
2. **Seeding:** Update `SeedReferenceData` + `GamePlayerTemplateService` to process B team data
3. **Game setup:** Update `SetupNewGame` to initialize B team players (real or synthetic)
4. **Season lifecycle:** Create `BTeamDevelopmentProcessor`
5. **Call-up system:** Migration + Loan model + LoanService + MatchdayOrchestrator
6. **Promote/send down:** Action classes + routes
7. **UI:** B team view + squad view badges + navigation
8. **Translations:** All new strings in both languages

Steps 1-4 are the foundation. Steps 5-6 add interactivity. Steps 7-8 are the polish.

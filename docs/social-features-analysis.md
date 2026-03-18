# Social Features Gap Analysis & Prioritization Framework

## Context

VirtuaFC is a **100% single-player** football manager simulation. There are zero social features — no profiles, friends, leaderboards, sharing, or multiplayer. The game produces rich competitive data (standings, match results, player stats, transfers, reputation tiers, season archives) but all of it is siloed per user. This analysis identifies the highest-impact social features to increase engagement, retention, and organic word-of-mouth growth.

---

## Current State

| Area | Status |
|------|--------|
| User identity | `name`, `email`, `locale` only. No username, avatar, bio, or public profile |
| Social graph | None. No friends, followers, or user discovery |
| Competition | Single-player vs AI. No cross-user leaderboards or rankings |
| Content sharing | None. No way to share results, teams, or achievements externally |
| Community | None. No feeds, comments, or group features |
| Viral/growth | `InviteCode` model exists for beta gating but has no social referral incentive |
| Notifications | Rich in-game system (24+ types via `NotificationService`) but all game-scoped |

### Existing Infrastructure Available for Reuse

| Asset | Location | Social Reuse |
|-------|----------|--------------|
| `SeasonArchive` | `app/Models/SeasonArchive.php` | Gold mine for leaderboards, sharing, achievements — stores `final_standings`, `season_awards`, `player_season_stats`, `match_results`, compressed `match_events_archive` |
| `ShowSeasonEnd` | `app/Http/Views/ShowSeasonEnd.php` | Already computes Pichichi (top scorer), Zamora (best GK), biggest victories, manager evaluations — all prime shareable content |
| `GameNotification` + `NotificationService` | `app/Modules/Notification/` | Extensible with new social notification types |
| `InviteCode` | `app/Models/InviteCode.php` | Adaptable for friend referral system |
| `GameStanding`, `GameMatch`, `MatchEvent` | `app/Models/` | Complete competitive data for comparisons & sharing |
| Modular architecture | `app/Modules/` | New `Social` module follows established patterns |
| Queue system | Laravel Horizon | Background processing for leaderboard computation |
| `TeamReputation` | `app/Models/TeamReputation.php` | Dynamic per-game reputation tiers (Local → Elite) |

---

## Gap Analysis

### A. Profile & Identity
| ID | Gap | Description |
|----|-----|-------------|
| A1 | Public manager profile | No public-facing page to showcase managerial career |
| A2 | Career stats summary | No aggregated stats across games (seasons, trophies, win %) |
| A3 | Achievement/trophy case | No achievement system for milestones (first title, unbeaten run, youth development) |
| A4 | Manager avatar/badge | No visual identity beyond name |

### B. Social Graph
| ID | Gap | Description |
|----|-----|-------------|
| B1 | Friend/follow system | No way to connect with other managers |
| B2 | User discovery | No way to find managers playing the same team/league |

### C. Competition & Comparison
| ID | Gap | Description |
|----|-----|-------------|
| C1 | Global leaderboards | No cross-user rankings (most titles, best points, longest streaks) |
| C2 | Season leaderboards | No same-season comparison across managers |
| C3 | Team-specific leaderboards | No comparison among managers who chose the same team |
| C4 | Head-to-head comparison | No side-by-side season comparison tool |
| C5 | Head-to-head challenges | No async friendly matches between users' squads |
| C6 | Asynchronous leagues | No multiplayer leagues with independent play |

### D. Content Sharing
| ID | Gap | Description |
|----|-----|-------------|
| D1 | Season summary cards | No shareable visual "Spotify Wrapped" for completed seasons |
| D2 | Match result sharing | No way to share a dramatic result externally |
| D3 | Squad/lineup showcase | No way to share team composition |
| D4 | Open Graph tags | No rich link previews when sharing VirtuaFC URLs |

### E. Community
| ID | Gap | Description |
|----|-----|-------------|
| E1 | Activity feed | No feed showing other managers' accomplishments |
| E2 | Comments/reactions | No ability to react to shared content |

### F. Viral & Growth
| ID | Gap | Description |
|----|-----|-------------|
| F1 | Referral program | InviteCode exists but has no social incentive — no "invite friends, get rewards" |
| F2 | Share hooks | No one-tap share to Twitter/WhatsApp/clipboard |
| F3 | "Playing since" badge | No tenure/loyalty social proof |

---

## Prioritization Framework

### Scoring Dimensions

| Dimension | 1 | 3 | 5 |
|-----------|---|---|---|
| **Engagement Impact** (×2) | Novelty only | Regular use | Daily driver |
| **Virality Potential** (×2) | No sharing | Occasional shares | Inherently viral |
| **Impl. Complexity** (×1, inverted) | Major new systems | Moderate work | Simple addition |
| **Infra Readiness** (×1) | Greenfield | Partial reuse | Mostly exists |

**Score** = (Engagement × 2) + (Virality × 2) + (6 − Complexity) + Infra Readiness. Max = 30.

### Scored Feature Matrix

| Rank | Feature | Engage | Viral | Complex | Infra | **Score** |
|------|---------|:------:|:-----:|:-------:|:-----:|:---------:|
| 1 | **D1. Season summary cards** | 4 | 5 | 2 | 5 | **27** |
| 2 | **C1. Global leaderboards** | 5 | 4 | 2 | 5 | **27** |
| 3 | **A2. Career stats summary** | 4 | 3 | 1 | 5 | **24** |
| 4 | **D2. Match result sharing** | 3 | 5 | 2 | 5 | **24** |
| 5 | **A3. Achievements/trophies** | 5 | 3 | 3 | 4 | **23** |
| 6 | **C3. Team-specific leaderboards** | 4 | 3 | 2 | 5 | **22** |
| 7 | **D4. Open Graph tags** | 2 | 5 | 1 | 4 | **23** |
| 8 | **F1. Referral with rewards** | 2 | 5 | 2 | 4 | **22** |
| 9 | **C2. Season leaderboards** | 4 | 3 | 2 | 5 | **22** |
| 10 | **A1. Public manager profile** | 4 | 3 | 2 | 3 | **21** |
| 11 | **D3. Squad/lineup showcase** | 3 | 4 | 2 | 5 | **22** |
| 12 | **C4. Head-to-head comparison** | 3 | 3 | 3 | 5 | **19** |
| 13 | **E1. Activity feed** | 4 | 3 | 3 | 3 | **19** |
| 14 | **F3. "Playing since" badge** | 2 | 2 | 1 | 5 | **16** |
| 15 | **C5. Head-to-head challenges** | 4 | 4 | 5 | 1 | **17** |
| 16 | **C6. Asynchronous leagues** | 5 | 4 | 5 | 2 | **19** |
| 17 | **B1. Friend/follow system** | 3 | 2 | 3 | 1 | **14** |
| 18 | **E2. Comments/reactions** | 3 | 2 | 3 | 2 | **15** |

---

## Implementation Tiers

### Tier 1 — Quick Wins (Score 22+, ship in weeks 1-6)

High impact, low complexity, rich infrastructure to reuse.

#### D1. Season Summary Cards
Think "Spotify Wrapped" for football management. Generate a shareable visual card at season end: team crest, final position, Pichichi, key stats, trophies.

- **Data source**: `SeasonArchive` has everything; `ShowSeasonEnd` already computes Pichichi, Zamora, biggest victories
- **Migration**: Add `share_token` (uuid, nullable) to `season_archives`
- **New route**: `GET /share/season/{token}` (public, no auth) → `ShowSharedSeason` view
- **New action**: `POST /game/{gameId}/season/{archiveId}/share` → generates token
- **New template**: `resources/views/shared/season-summary.blade.php` — standalone styled card
- **Add share button** to existing `resources/views/season-end.blade.php`
- **Web Share API** (`navigator.share()`) with clipboard fallback

#### C1. Global Leaderboards
Rank all managers by: most league titles, highest single-season points, best goal difference, most trophies.

- **Data source**: `SeasonArchive.final_standings` + `season_awards` joined through `Game.user_id`
- **Option A** (simple, good for <1000 users): Query `SeasonArchive` directly, cache 30 min
- **Option B** (scalable): New `ManagerStat` model caching aggregated stats per user, updated on season close
- **New route**: `GET /leaderboards` (authenticated) → `ShowLeaderboards` view
- **New template**: `resources/views/leaderboards.blade.php` with tabs for different ranking types
- **Add link** to dashboard navigation
- **Indices**: Add index on `season_archives(game_id, season)`

#### A2. Career Stats Summary
"Manager Career" section on dashboard: total seasons, win %, trophies, favorite team, longest tenure.

- **No new models** — derive from `SeasonArchive` + `Game` records
- **Modify**: `app/Http/Views/Dashboard.php` to compute career aggregates
- **New partial**: `resources/views/partials/career-stats.blade.php`
- **Share**: Same `ManagerStat` cache as leaderboards if implemented

#### D2. Match Result Sharing
After a notable match, share a styled card with scoreline, scorers, competition badge.

- **New polymorphic table**: `shared_content` (`id`, `shareable_type`, `shareable_id`, `token`, `created_at`) — supports seasons, matches, and future content types
- **New route**: `GET /share/{token}` (public) → `ShowSharedContent` view
- **New template**: `resources/views/shared/match-card.blade.php`
- **Add share button** to live-match end screen and `ShowMatchResults`
- **Data source**: `GameMatch` scores + `MatchEvent` goals/cards

#### D4. Open Graph Tags
Rich link previews when sharing VirtuaFC URLs on Twitter/WhatsApp/Discord.

- **No new models** — purely view/layout concern
- **Create**: `resources/views/layouts/public.blade.php` with `<meta property="og:*">` tags
- **Each share route** populates OG title, description, image dynamically
- **Fallback image**: VirtuaFC logo for pages without specific images

#### C2/C3. Season & Team-Specific Leaderboards
Filter extensions on the global leaderboard.

- **Same infrastructure as C1** — add query params: `GET /leaderboards?season=2025&team={teamId}`
- **Team filter**: Dropdown using `Team` model + `team-crest` component
- **Season filter**: Dropdown from available `SeasonArchive.season` values

#### F1. Enhanced Referral Program
Users generate personal invite links. Reward both parties with an in-game bonus (e.g., +5% transfer budget).

- **Migration**: Add `owner_user_id` (nullable FK) and `referred_by` to `users` table
- **Extend**: `InviteCode` model with user-generated codes
- **New action**: `app/Http/Actions/GenerateReferralCode.php`
- **Hook into**: `GameCreationService::create()` to apply referral bonus
- **Display**: Referral link on dashboard + profile

### Tier 2 — Strategic Bets (Score 19-21, weeks 6-12)

| Feature | Key Effort |
|---------|-----------|
| **A3. Achievements/trophies** | New `Achievement` + `UserAchievement` models. ~20 initial achievements. Hook into `MatchFinalized` events and season closing pipeline. Display on profile + season-end screen. |
| **A1. Public manager profiles** | Add `username`, `avatar_key`, `bio`, `is_profile_public` to `users`. New view + template at `GET /managers/{username}`. Aggregate career data from `SeasonArchive`. |
| **D3. Squad/lineup showcase** | Visual card of current formation + player abilities. Similar to match card. |
| **C4. Head-to-head comparison** | Side-by-side comparison of two managers' seasons with same team. Query `SeasonArchive` by `team_id`. |
| **E1. Activity feed** | New `Activity` model logging key events. Feed view showing recent community milestones. |

### Tier 3 — Major Features (weeks 12+)

| Feature | Key Challenge |
|---------|--------------|
| **C5. Head-to-head challenges** | Async match between two users' squads. Needs new match mode, challenge queue, result tracking. Reuses `MatchSimulator`. |
| **C6. Asynchronous leagues** | Shared competition where friends' independent game results feed a meta-league. Complex aggregation + fair comparison logic. |
| **B1. Friend/follow system** | New `Friendship` model. Request/accept flow. Prerequisite for deeper social features. |

### Tier 4 — Nice-to-Haves

| Feature | Reason for deferral |
|---------|-------------------|
| **E2. Comments/reactions** | High moderation burden, low ROI for small user base |
| **In-app messaging** | Requires moderation infrastructure |

---

## Cross-Cutting Concerns

### Privacy & Visibility
- Sharing is **opt-in**: user clicks "Share" to generate a public token (UUID, unguessable)
- Leaderboards use `User.name` — add optional `display_name` field or allow opt-out via `is_profile_public`
- Public share pages expose no user IDs, emails, or game internals beyond the shared content
- No user enumeration via public routes

### Moderation
- **Tier 1 needs minimal moderation**: All shared content is game-generated data, not user text
- Only user-written field is `name`/`bio` — add basic profanity filter (simple blocklist)
- Defer chat/comments until moderation tooling exists

### Performance
- Leaderboard queries can be expensive at scale → use `ManagerStat` cache table updated on season close
- Cache leaderboard pages with 30-60 min TTL (data only changes when someone completes a season)
- Share pages are immutable → cache aggressively
- Paginate all leaderboard views

### Mobile Responsiveness
- Share cards: Design at 375px width first, 1200×630 for OG image compatibility
- Leaderboard tables: `overflow-x-auto`, sticky first column, `hidden md:table-cell` for non-essential columns
- All share/action buttons: `min-h-[44px]` touch targets
- Use Tailwind `text-*` utilities only (respects 14px mobile / 20px desktop root scaling)

### Internationalization
- New translation file: `lang/{locale}/social.php` for leaderboard labels, share text, career stats
- New translation file: `lang/{locale}/achievements.php` for achievement titles/descriptions
- Both `lang/es/` and `lang/en/` must be updated simultaneously

---

## Recommended Implementation Sequence

| Phase | Weeks | Features | Value Delivered |
|-------|-------|----------|----------------|
| **1. Foundation** | 1-2 | Career stats summary (A2), shared content migration | Internal value — users see their career for the first time |
| **2. Shareable content** | 3-4 | Season summary cards (D1), match result sharing (D2), OG tags (D4) | External value — first viral touchpoints, shareable on social media |
| **3. Competition** | 5-6 | Global leaderboards (C1), team + season filters (C2/C3) | Community value — managers can compare and compete |
| **4. Growth** | 7-8 | Referral program (F1), achievements (A3) | Growth mechanics — reward loops and retention |
| **5. Deeper social** | 9-12 | Public profiles (A1), activity feed (E1), head-to-head comparison (C4) | Social depth — managers have identity and community |

---

## Key Files to Modify

| File | Changes |
|------|---------|
| `app/Models/SeasonArchive.php` | Add `share_token` column |
| `app/Models/User.php` | Add `username`, `avatar_key`, `bio`, `is_profile_public`, `referred_by` |
| `app/Models/InviteCode.php` | Add `owner_user_id` for user-generated referral codes |
| `app/Http/Views/ShowSeasonEnd.php` | Reuse data assembly logic for season cards |
| `app/Http/Views/Dashboard.php` | Add career stats computation |
| `resources/views/season-end.blade.php` | Add share button |
| `resources/views/live-match.blade.php` | Add share button for match results |
| `resources/views/dashboard.blade.php` | Add career stats section + leaderboard link |
| `routes/web.php` | Add public share routes, leaderboard routes |
| `lang/es/` + `lang/en/` | New `social.php` and `achievements.php` translation files |

## Verification

- **Career stats**: Create a test game, play 2+ seasons, verify dashboard shows aggregated career data
- **Season cards**: Complete a season, click share, verify public URL renders correctly and OG tags work
- **Match sharing**: Play a match, share result, verify public card shows score + scorers
- **Leaderboards**: With 2+ users who have completed seasons, verify ranking accuracy and filtering
- **Referral**: Generate a referral code, register a new user with it, verify both get credited
- **Mobile**: Test all new pages at 375px width
- **i18n**: Switch locale between `es` and `en`, verify all new strings render correctly
- **Run tests**: `php artisan test` — ensure no regressions

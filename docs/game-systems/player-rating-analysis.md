# Player Average Rating: Static-Only vs. Composite Model

## Context

The player **overall score** is currently calculated as:

```
Overall = Technical × 0.35 + Physical × 0.35 + Fitness × 0.15 + Morale × 0.15
```

- **Static qualities** (70%): Technical Ability, Physical Ability — change slowly over seasons via development/aging.
- **Dynamic qualities** (30%): Fitness (40–100), Morale (50–100) — fluctuate between matches.

Some players have raised the question: should fitness and morale be excluded from the displayed average, and only factor in during match simulation?

This document analyzes both approaches.

---

## Current Model: Composite Average (Static + Dynamic)

### How it works

The number the player sees on squad screens, lineup views, and team averages blends all four attributes. A player with 85 technical / 85 physical / 60 fitness / 55 morale shows as **73**, not 85.

### Pros

1. **Transparency.** The number you see is closer to what you get in the simulation. If fitness drags a player down, the UI tells you before the match, not after. There are no hidden penalties.

2. **Tactical decision-making.** Managers must weigh whether to start a fatigued star (visible 73) or a fresh bench player (visible 76). The rating becomes a decision tool, not just a label.

3. **Squad management pressure.** Rotation, rest, and morale management feel consequential because their effects are visible everywhere. It reinforces the game loop: you *see* why rotation matters.

4. **Realistic feel.** In real football, a world-class player at 40% fitness is not world-class that day. The composite number reflects "match readiness," which is arguably more useful than raw talent.

5. **Simplicity.** One number, one meaning. No need to explain that "the number you see isn't quite the number the engine uses."

### Cons

1. **Perceived unfairness.** A player rated 85 showing as 73 feels like a lie, especially when the user just bought them. "I signed an 85, why does he say 73?"

2. **Volatility creates noise.** The number moves between every matchday. Users trying to evaluate long-term squad quality see a moving target. "Is my team actually getting better, or did everyone just rest well?"

3. **Morale is partly outside player control.** Team losses tank morale for everyone, including players who performed well. Seeing their rating drop feels punitive and arbitrary.

4. **Comparison difficulty.** Scouting and transfer decisions become harder when the number you see on your player includes transient state but the number you see on a transfer target may not (or reflects *their* current fitness context).

5. **Double-counting risk.** If the simulation *also* applies fitness/morale penalties (which it does — fitness=5%, morale=5%, plus energy drain), the player effectively gets penalized twice: once in the displayed rating affecting lineup AI recommendations, and again in the engine.

---

## Alternative Model: Static Average (Display) + Dynamic Simulation

### How it works

The displayed overall becomes:

```
Displayed Overall = Technical × 0.50 + Physical × 0.50
```

Fitness and morale are shown separately (bars, icons, indicators) and only affect outcomes inside the match engine, where they already have their own weight (5% + 5% in team strength, plus energy curves and performance modifiers).

### Pros

1. **Stable identity.** An 85-rated player is always 85 on the squad screen. Users can assess squad quality, compare players, and track development without noise from transient state.

2. **Cleaner mental model.** "Rating = how good the player *is*. Fitness/morale = how ready they are *today*." Two distinct concepts, two distinct UI elements.

3. **Better transfer/scouting UX.** When evaluating signings, users compare stable numbers. No confusion from comparing a rested target to a fatigued squad player.

4. **No double-counting.** The simulation applies dynamic penalties exactly once, in its own controlled way.

5. **Industry standard.** FIFA/FC, Football Manager, eFootball, and most football management games display a static overall rating with condition shown separately. Users arrive with this expectation.

### Cons

1. **Hidden consequences.** If fitness and morale don't visually affect the number, users may underestimate their impact. "My 85-rated player lost to a 70-rated player — the game is broken." The connection between condition management and results becomes less obvious.

2. **Weaker rotation incentive.** When the squad screen shows everyone at their peak number, the urgency to rotate is less visceral. Users need to actively check fitness/morale bars rather than seeing it reflected in the headline number.

3. **Rating inflation.** Without dynamic drag, average team ratings will be higher and more compressed. An 82-rated team vs. an 80-rated team looks closer than it plays, because one team might be exhausted.

4. **Two numbers to learn.** New users must understand that the big number is potential and the small bars are today's reality. That's a UX education cost.

---

## Comparison Matrix

| Criterion | Composite (current) | Static + separate indicators |
|---|---|---|
| Accuracy of "what you see = what you get" | High (rating ≈ match input) | Medium (rating = ceiling, not today's output) |
| Stability for squad evaluation | Low (moves every matchday) | High (moves only with development) |
| Rotation/management incentive | Strong (visible in the number) | Moderate (must check bars) |
| Transfer comparison clarity | Muddy | Clean |
| New user comprehension | Simple (one number) | Requires learning two layers |
| Risk of "the game is broken" complaints | Lower (visible explanation) | Higher (hidden penalties) |
| Industry convention | Uncommon | Standard (FM, FC, etc.) |
| Double-counting risk | Present | None |

---

## Recommendation

**Switch to the static display model**, with strong supporting UI for fitness and morale.

### Rationale

1. **The double-counting problem is real.** The match engine already weighs fitness at 5% and morale at 5% in team strength, applies energy curves that drain physical effectiveness over 90 minutes, and uses performance modifiers (0.70–1.30) influenced by condition. Adding another 30% penalty on the *displayed* number means the user is penalized more than the simulation intends. Removing dynamic qualities from the display actually *aligns* the visible number with how the engine treats raw ability.

2. **Squad building is the core loop.** Players spend more time on squad/lineup screens than watching simulations. The number they stare at most should represent the thing they're trying to optimize long-term (player quality), not the thing that fluctuates between every matchday.

3. **Fitness and morale can be communicated better visually than numerically.** A fitness bar dropping to orange/red, or a morale emoji going from happy to frustrated, communicates urgency faster than "your 85 is now a 73." Visual indicators can be *more* prominent than a number change, not less.

4. **Player expectations.** The community already knows football games from FM and FC. When they see "85 overall," they expect that to be a stable measure of quality. Deviating from this creates friction that doesn't add strategic depth — it just adds confusion.

### Implementation guidance

If this change is adopted:

- **Overall score** = `technical × 0.50 + physical × 0.50` (displayed everywhere).
- **Fitness and morale** shown as dedicated visual indicators on squad/lineup screens (colored bars, status icons, or both).
- **Lineup screen** should surface warnings when starting players with low fitness or morale (amber/red indicators, maybe a "risk" badge).
- **Match simulation** continues using its own weighting internally (55% technical, 35% physical, 5% fitness, 5% morale + energy curves). No simulation changes needed.
- **AI lineup selection** should continue to factor in fitness/morale when choosing starters, independent of the displayed rating.
- **Team average** displayed in competitions/standings uses the static overall, giving users a stable benchmark.

This gives users a clean, stable number for the strategic layer while preserving all the tactical depth of fitness/morale management through dedicated, purpose-built UI.

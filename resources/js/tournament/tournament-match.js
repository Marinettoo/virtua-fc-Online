/**
 * Alpine.js component for tournament match play.
 *
 * Handles the full match lifecycle:
 * - Load match/team/player data from IndexedDB
 * - Lineup selection with auto-lineup
 * - Match simulation via stateless server API
 * - Animated event reveal
 * - Extra time + penalties for knockout matches
 * - Store results and navigate back to hub
 */

import { TournamentDB } from './db.js';
import {
    simulateMatch,
    simulateExtraTime,
    simulatePenalties,
    simulateBatch,
    getAutoLineup,
    checkGroupStageTransition,
} from './state.js';

const FORMATIONS = ['4-4-2', '4-3-3', '4-2-3-1', '3-5-2', '3-4-3', '5-3-2', '5-4-1', '4-1-4-1'];
const MENTALITIES = ['defensive', 'balanced', 'attacking'];

const POSITION_ORDER = {
    'Goalkeeper': 0,
    'Centre-Back': 1, 'Left-Back': 2, 'Right-Back': 2,
    'Defensive Midfield': 3, 'Central Midfield': 4,
    'Left Midfield': 4, 'Right Midfield': 4,
    'Attacking Midfield': 5,
    'Left Winger': 6, 'Right Winger': 6,
    'Second Striker': 7, 'Centre-Forward': 8,
};

export default function tournamentMatch(config) {
    return {
        tournamentId: config.tournamentId,
        matchId: config.matchId,
        translations: config.translations || {},

        // Data loaded from IndexedDB
        tournament: null,
        match: null,
        homeTeam: null,
        awayTeam: null,
        homePlayers: [],
        awayPlayers: [],

        // Lineup state
        homeLineup: [],
        homeBench: [],
        awayLineup: [],
        awayBench: [],
        formation: '4-4-2',
        mentality: 'balanced',
        formations: FORMATIONS,
        mentalities: MENTALITIES,

        // Match state
        phase: 'pre_match', // pre_match, simulating, playing, extra_time, penalties, full_time
        loading: true,
        error: null,
        simulating: false,

        // Result state
        homeScore: 0,
        awayScore: 0,
        events: [],
        revealedEvents: [],
        revealTimer: null,
        homePossession: 50,
        awayPossession: 50,
        mvpPlayerId: null,
        performances: {},

        // Extra time
        isKnockout: false,
        etHomeScore: 0,
        etAwayScore: 0,
        etEvents: [],
        needsExtraTime: false,
        needsPenalties: false,

        // Penalties
        penaltyKicks: [],
        revealedPenaltyKicks: [],
        penaltyHomeScore: 0,
        penaltyAwayScore: 0,
        penaltyWinner: null,
        penaltyRevealTimer: null,

        // Batch simulation (other matches in the round)
        batchSimulating: false,
        batchProgress: '',

        async init() {
            try {
                this.tournament = await TournamentDB.getTournament(this.tournamentId);
                this.match = await TournamentDB.getMatch(this.matchId);

                if (!this.match) {
                    this.error = this.translations.matchNotFound || 'Match not found';
                    this.loading = false;
                    return;
                }

                if (this.match.played) {
                    // Already played — show results
                    this.homeScore = this.match.homeScore;
                    this.awayScore = this.match.awayScore;
                    this.homePossession = this.match.homePossession || 50;
                    this.awayPossession = this.match.awayPossession || 50;
                    const matchEvents = await TournamentDB.getMatchEvents(this.matchId);
                    this.events = matchEvents.sort((a, b) => a.minute - b.minute);
                    this.revealedEvents = [...this.events];
                    this.phase = 'full_time';
                }

                this.homeTeam = await TournamentDB.getTeam(this.match.homeTeamId);
                this.awayTeam = await TournamentDB.getTeam(this.match.awayTeamId);
                this.homePlayers = await TournamentDB.getPlayersForTeam(this.match.homeTeamId);
                this.awayPlayers = await TournamentDB.getPlayersForTeam(this.match.awayTeamId);

                this.isKnockout = !!this.match.cupTieId;

                // Sort players by position
                const sortByPos = (a, b) => (POSITION_ORDER[a.position] ?? 5) - (POSITION_ORDER[b.position] ?? 5);
                this.homePlayers.sort(sortByPos);
                this.awayPlayers.sort(sortByPos);

                // Auto-generate lineups if not played
                if (!this.match.played) {
                    await this.generateLineups();
                }

                this.loading = false;
            } catch (err) {
                this.error = err.message;
                this.loading = false;
            }
        },

        // --- Lineup helpers ---

        get isUserHome() {
            return this.tournament?.userTeamId === this.match?.homeTeamId;
        },

        get userTeamId() {
            return this.tournament?.userTeamId;
        },

        get isUserMatch() {
            return this.match?.homeTeamId === this.userTeamId || this.match?.awayTeamId === this.userTeamId;
        },

        get userLineup() {
            return this.isUserHome ? this.homeLineup : this.awayLineup;
        },

        get userBench() {
            return this.isUserHome ? this.homeBench : this.awayBench;
        },

        get opponentTeam() {
            return this.isUserHome ? this.awayTeam : this.homeTeam;
        },

        get userTeam() {
            return this.isUserHome ? this.homeTeam : this.awayTeam;
        },

        async generateLineups() {
            // Generate for both teams via server
            try {
                const homeResult = await getAutoLineup(this.homePlayers, this.formation);
                this.homeLineup = homeResult.lineup;
                this.homeBench = homeResult.bench;

                const awayResult = await getAutoLineup(this.awayPlayers, '4-4-2');
                this.awayLineup = awayResult.lineup;
                this.awayBench = awayResult.bench;
            } catch (e) {
                // Fallback: first 11 as lineup, rest as bench
                this.homeLineup = this.homePlayers.slice(0, 11);
                this.homeBench = this.homePlayers.slice(11);
                this.awayLineup = this.awayPlayers.slice(0, 11);
                this.awayBench = this.awayPlayers.slice(11);
            }
        },

        async changeFormation(f) {
            this.formation = f;
            // Re-generate user lineup for new formation
            const players = this.isUserHome ? this.homePlayers : this.awayPlayers;
            try {
                const result = await getAutoLineup(players, f);
                if (this.isUserHome) {
                    this.homeLineup = result.lineup;
                    this.homeBench = result.bench;
                } else {
                    this.awayLineup = result.lineup;
                    this.awayBench = result.bench;
                }
            } catch (e) {
                // Keep current lineup
            }
        },

        swapPlayer(benchIdx) {
            // Swap a bench player with the worst-rated starter at the same position group
            const bench = this.isUserHome ? this.homeBench : this.awayBench;
            const lineup = this.isUserHome ? this.homeLineup : this.awayLineup;
            const benchPlayer = bench[benchIdx];
            if (!benchPlayer) return;

            // Find worst starter in same position group
            const group = this.getPositionGroup(benchPlayer.position);
            let worstIdx = -1;
            let worstScore = Infinity;
            lineup.forEach((p, i) => {
                if (this.getPositionGroup(p.position) === group && p.overallScore < worstScore) {
                    worstScore = p.overallScore;
                    worstIdx = i;
                }
            });

            if (worstIdx === -1) {
                // No same-group player, swap with lowest rated outfield
                lineup.forEach((p, i) => {
                    if (p.position !== 'Goalkeeper' && p.overallScore < worstScore) {
                        worstScore = p.overallScore;
                        worstIdx = i;
                    }
                });
            }

            if (worstIdx >= 0) {
                const removed = lineup[worstIdx];
                lineup[worstIdx] = benchPlayer;
                bench[benchIdx] = removed;

                if (this.isUserHome) {
                    this.homeLineup = [...lineup];
                    this.homeBench = [...bench];
                } else {
                    this.awayLineup = [...lineup];
                    this.awayBench = [...bench];
                }
            }
        },

        getPositionGroup(pos) {
            const map = {
                'Goalkeeper': 'GK',
                'Centre-Back': 'DEF', 'Left-Back': 'DEF', 'Right-Back': 'DEF',
                'Defensive Midfield': 'MID', 'Central Midfield': 'MID',
                'Attacking Midfield': 'MID', 'Left Midfield': 'MID', 'Right Midfield': 'MID',
                'Left Winger': 'FWD', 'Right Winger': 'FWD',
                'Second Striker': 'FWD', 'Centre-Forward': 'FWD',
            };
            return map[pos] || 'MID';
        },

        getPositionAbbr(pos) {
            const map = {
                'Goalkeeper': 'GK',
                'Centre-Back': 'CB', 'Left-Back': 'LB', 'Right-Back': 'RB',
                'Defensive Midfield': 'DM', 'Central Midfield': 'CM',
                'Attacking Midfield': 'AM', 'Left Midfield': 'LM', 'Right Midfield': 'RM',
                'Left Winger': 'LW', 'Right Winger': 'RW',
                'Second Striker': 'SS', 'Centre-Forward': 'CF',
            };
            return map[pos] || pos?.substring(0, 2) || '??';
        },

        getPositionColor(pos) {
            const group = this.getPositionGroup(pos);
            return {
                'GK': 'bg-accent-gold/20 text-accent-gold',
                'DEF': 'bg-accent-blue/20 text-accent-blue',
                'MID': 'bg-accent-green/20 text-accent-green',
                'FWD': 'bg-accent-red/20 text-accent-red',
            }[group] || 'bg-surface-600 text-text-secondary';
        },

        getOvrColor(score) {
            if (score >= 80) return 'text-accent-green';
            if (score >= 70) return 'text-lime-400';
            if (score >= 60) return 'text-accent-gold';
            return 'text-accent-orange';
        },

        // --- Simulation ---

        async kickOff() {
            if (this.simulating || this.phase !== 'pre_match') return;
            this.simulating = true;
            this.phase = 'simulating';

            try {
                const payload = {
                    homeTeam: {
                        id: this.homeTeam.id,
                        name: this.homeTeam.name,
                        lineup: this.homeLineup,
                    },
                    awayTeam: {
                        id: this.awayTeam.id,
                        name: this.awayTeam.name,
                        lineup: this.awayLineup,
                    },
                    homeFormation: this.isUserHome ? this.formation : '4-4-2',
                    awayFormation: this.isUserHome ? '4-4-2' : this.formation,
                    homeMentality: this.isUserHome ? this.mentality : 'balanced',
                    awayMentality: this.isUserHome ? 'balanced' : this.mentality,
                };

                const { match, events, result } = await simulateMatch(
                    this.tournamentId, this.matchId, payload
                );

                this.homeScore = result.homeScore;
                this.awayScore = result.awayScore;
                this.homePossession = result.homePossession;
                this.awayPossession = result.awayPossession;
                this.mvpPlayerId = result.mvpPlayerId;
                this.performances = result.performances || {};
                this.events = (result.events || []).sort((a, b) => a.minute - b.minute);
                this.match = match;

                // Start event reveal animation
                this.phase = 'playing';
                this.simulating = false;
                this.revealEvents();

            } catch (err) {
                this.error = err.message;
                this.simulating = false;
                this.phase = 'pre_match';
            }
        },

        revealEvents() {
            let idx = 0;
            const reveal = () => {
                if (idx >= this.events.length) {
                    // All events revealed — check if knockout needs ET
                    this.checkPostMatch();
                    return;
                }
                this.revealedEvents.push(this.events[idx]);
                idx++;
                // Speed: faster for non-goal events
                const lastEvent = this.revealedEvents[this.revealedEvents.length - 1];
                const isGoal = lastEvent?.event_type === 'goal' || lastEvent?.event_type === 'own_goal';
                this.revealTimer = setTimeout(reveal, isGoal ? 800 : 200);
            };
            this.revealTimer = setTimeout(reveal, 500);
        },

        skipReveal() {
            if (this.revealTimer) clearTimeout(this.revealTimer);
            this.revealedEvents = [...this.events];
            this.checkPostMatch();
        },

        checkPostMatch() {
            if (this.isKnockout && this.homeScore === this.awayScore) {
                this.needsExtraTime = true;
                this.phase = 'extra_time';
            } else {
                this.phase = 'full_time';
            }
        },

        // --- Extra Time ---

        async playExtraTime() {
            if (this.simulating) return;
            this.simulating = true;

            try {
                const payload = {
                    homeTeam: {
                        id: this.homeTeam.id,
                        name: this.homeTeam.name,
                        lineup: this.homeLineup,
                    },
                    awayTeam: {
                        id: this.awayTeam.id,
                        name: this.awayTeam.name,
                        lineup: this.awayLineup,
                    },
                    homeFormation: this.isUserHome ? this.formation : '4-4-2',
                    awayFormation: this.isUserHome ? '4-4-2' : this.formation,
                    homeMentality: this.isUserHome ? this.mentality : 'balanced',
                    awayMentality: this.isUserHome ? 'balanced' : this.mentality,
                    homeEntryMinutes: {},
                    awayEntryMinutes: {},
                };

                const { match, events, result } = await simulateExtraTime(
                    this.tournamentId, this.matchId, payload
                );

                this.etHomeScore = result.homeScoreET;
                this.etAwayScore = result.awayScoreET;
                this.etEvents = (result.events || []).sort((a, b) => a.minute - b.minute);
                this.needsPenalties = result.needsPenalties;
                this.match = match;

                // Reveal ET events
                this.simulating = false;
                let idx = 0;
                const reveal = () => {
                    if (idx >= this.etEvents.length) {
                        if (this.needsPenalties) {
                            this.phase = 'penalties';
                        } else {
                            this.phase = 'full_time';
                        }
                        return;
                    }
                    this.revealedEvents.push(this.etEvents[idx]);
                    idx++;
                    const lastEvent = this.revealedEvents[this.revealedEvents.length - 1];
                    const isGoal = lastEvent?.event_type === 'goal' || lastEvent?.event_type === 'own_goal';
                    this.revealTimer = setTimeout(reveal, isGoal ? 800 : 200);
                };
                this.revealTimer = setTimeout(reveal, 500);

            } catch (err) {
                this.error = err.message;
                this.simulating = false;
            }
        },

        // --- Penalties ---

        async playPenalties() {
            if (this.simulating) return;
            this.simulating = true;

            try {
                // Select top 5 kickers by technical ability (excluding GK)
                const selectKickers = (lineup) => {
                    return [...lineup]
                        .filter(p => p.position !== 'Goalkeeper')
                        .sort((a, b) => (b.technicalAbility || 70) - (a.technicalAbility || 70))
                        .slice(0, 5);
                };

                const payload = {
                    homeTeam: {
                        id: this.homeTeam.id,
                        name: this.homeTeam.name,
                        lineup: this.homeLineup,
                    },
                    awayTeam: {
                        id: this.awayTeam.id,
                        name: this.awayTeam.name,
                        lineup: this.awayLineup,
                    },
                    homeKickers: selectKickers(this.homeLineup),
                    awayKickers: selectKickers(this.awayLineup),
                };

                const { match, result } = await simulatePenalties(
                    this.tournamentId, this.matchId, payload
                );

                this.penaltyKicks = result.kicks || [];
                this.penaltyHomeScore = result.homeScorePenalties;
                this.penaltyAwayScore = result.awayScorePenalties;
                this.penaltyWinner = result.winner;
                this.match = match;
                this.simulating = false;

                // Reveal penalty kicks one by one
                this.revealPenaltyKicks();

            } catch (err) {
                this.error = err.message;
                this.simulating = false;
            }
        },

        revealPenaltyKicks() {
            let idx = 0;
            const reveal = () => {
                if (idx >= this.penaltyKicks.length) {
                    this.phase = 'full_time';
                    return;
                }
                this.revealedPenaltyKicks.push(this.penaltyKicks[idx]);
                idx++;
                this.penaltyRevealTimer = setTimeout(reveal, 1000);
            };
            this.penaltyRevealTimer = setTimeout(reveal, 800);
        },

        skipPenaltyReveal() {
            if (this.penaltyRevealTimer) clearTimeout(this.penaltyRevealTimer);
            this.revealedPenaltyKicks = [...this.penaltyKicks];
            this.phase = 'full_time';
        },

        // --- Post-match ---

        get totalHomeScore() {
            return this.homeScore + this.etHomeScore;
        },

        get totalAwayScore() {
            return this.awayScore + this.etAwayScore;
        },

        get resultLabel() {
            const userIsHome = this.isUserHome;
            let userScore, oppScore;

            if (this.penaltyWinner) {
                const userWon = (this.penaltyWinner === 'home') === userIsHome;
                return userWon ? (this.translations.win || 'WIN') : (this.translations.loss || 'LOSS');
            }

            userScore = userIsHome ? this.totalHomeScore : this.totalAwayScore;
            oppScore = userIsHome ? this.totalAwayScore : this.totalHomeScore;

            if (userScore > oppScore) return this.translations.win || 'WIN';
            if (userScore < oppScore) return this.translations.loss || 'LOSS';
            return this.translations.draw || 'DRAW';
        },

        get resultColorClass() {
            const label = this.resultLabel;
            if (label === (this.translations.win || 'WIN')) return 'text-accent-green';
            if (label === (this.translations.loss || 'LOSS')) return 'text-accent-red';
            return 'text-accent-gold';
        },

        get mvpPlayer() {
            if (!this.mvpPlayerId) return null;
            return [...this.homePlayers, ...this.awayPlayers].find(p => p.id === this.mvpPlayerId);
        },

        // Event display helpers
        getEventIcon(type) {
            const icons = {
                'goal': '\u26BD', 'own_goal': '\uD83D\uDFE5', 'yellow_card': '\uD83D\uDFE8',
                'red_card': '\uD83D\uDFE5', 'injury': '\uD83C\uDFE5', 'substitution': '\uD83D\uDD04',
            };
            return icons[type] || '\u2022';
        },

        getEventSide(event) {
            const teamId = event.team_id;
            if (teamId === this.homeTeam?.id) return 'home';
            if (teamId === this.awayTeam?.id) return 'away';
            return 'home';
        },

        isGoalEvent(event) {
            return event.event_type === 'goal' || event.event_type === 'own_goal';
        },

        // --- Batch simulation ---

        async simulateOtherMatches() {
            this.batchSimulating = true;
            this.batchProgress = this.translations.simulatingOthers || 'Simulating other matches...';

            try {
                // Find unplayed matches in the same round/group phase
                const allMatches = await TournamentDB.getMatchesForTournament(this.tournamentId);
                const unplayed = allMatches.filter(m => !m.played && m.id !== this.matchId);

                // For group stage: simulate matches in the same round
                let toSimulate;
                if (this.match.groupLetter) {
                    toSimulate = unplayed.filter(m => m.groupLetter && m.roundNumber === this.match.roundNumber);
                } else {
                    // Knockout: simulate matches in the same knockout round
                    toSimulate = unplayed.filter(m => m.cupTieId && m.roundNumber === this.match.roundNumber);
                }

                if (toSimulate.length === 0) {
                    this.batchSimulating = false;
                    return;
                }

                // Build payloads for batch simulation
                const payloads = [];
                for (const m of toSimulate) {
                    const homePlayers = await TournamentDB.getPlayersForTeam(m.homeTeamId);
                    const awayPlayers = await TournamentDB.getPlayersForTeam(m.awayTeamId);
                    const homeTeam = await TournamentDB.getTeam(m.homeTeamId);
                    const awayTeam = await TournamentDB.getTeam(m.awayTeamId);

                    // Use first 11 sorted by overall score
                    const sortPlayers = (players) => [...players]
                        .sort((a, b) => (b.overallScore || 50) - (a.overallScore || 50))
                        .slice(0, 11);

                    payloads.push({
                        matchId: m.id,
                        homeTeam: { id: homeTeam.id, name: homeTeam.name, lineup: sortPlayers(homePlayers) },
                        awayTeam: { id: awayTeam.id, name: awayTeam.name, lineup: sortPlayers(awayPlayers) },
                        homeFormation: '4-4-2',
                        awayFormation: '4-4-2',
                        homeMentality: 'balanced',
                        awayMentality: 'balanced',
                    });
                }

                // Batch simulate (max 50 at a time)
                for (let i = 0; i < payloads.length; i += 50) {
                    const batch = payloads.slice(i, i + 50);
                    await simulateBatch(this.tournamentId, batch);
                }

                // Handle knockout draws — simulate ET + penalties for drawn knockout matches
                for (const m of toSimulate) {
                    const updated = await TournamentDB.getMatch(m.id);
                    if (updated.cupTieId && updated.homeScore === updated.awayScore) {
                        // Simulate extra time
                        const homePlayers = await TournamentDB.getPlayersForTeam(m.homeTeamId);
                        const awayPlayers = await TournamentDB.getPlayersForTeam(m.awayTeamId);
                        const homeTeam = await TournamentDB.getTeam(m.homeTeamId);
                        const awayTeam = await TournamentDB.getTeam(m.awayTeamId);
                        const sortP = (players) => [...players]
                            .sort((a, b) => (b.overallScore || 50) - (a.overallScore || 50))
                            .slice(0, 11);

                        const etPayload = {
                            homeTeam: { id: homeTeam.id, name: homeTeam.name, lineup: sortP(homePlayers) },
                            awayTeam: { id: awayTeam.id, name: awayTeam.name, lineup: sortP(awayPlayers) },
                            homeFormation: '4-4-2',
                            awayFormation: '4-4-2',
                            homeMentality: 'balanced',
                            awayMentality: 'balanced',
                            homeEntryMinutes: {},
                            awayEntryMinutes: {},
                        };

                        const etResult = await simulateExtraTime(this.tournamentId, m.id, etPayload);

                        if (etResult.result.needsPenalties) {
                            const lineup = sortP(homePlayers);
                            const awayLineup = sortP(awayPlayers);
                            const selectKickers = (l) => [...l]
                                .filter(p => p.position !== 'Goalkeeper')
                                .sort((a, b) => (b.technicalAbility || 70) - (a.technicalAbility || 70))
                                .slice(0, 5);

                            await simulatePenalties(this.tournamentId, m.id, {
                                homeTeam: { id: homeTeam.id, name: homeTeam.name, lineup },
                                awayTeam: { id: awayTeam.id, name: awayTeam.name, lineup: awayLineup },
                                homeKickers: selectKickers(lineup),
                                awayKickers: selectKickers(awayLineup),
                            });
                        }
                    }
                }

                // Check group stage transition
                await checkGroupStageTransition(this.tournamentId);

            } catch (err) {
                console.error('Batch simulation error:', err);
            }

            this.batchSimulating = false;
        },

        async continueToHub() {
            // Simulate remaining matches in this round, then go to hub
            await this.simulateOtherMatches();
            window.location.href = `/tournament/${this.tournamentId}`;
        },

        destroy() {
            if (this.revealTimer) clearTimeout(this.revealTimer);
            if (this.penaltyRevealTimer) clearTimeout(this.penaltyRevealTimer);
        },
    };
}

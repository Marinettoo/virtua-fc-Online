/**
 * Alpine.js component for the tournament hub view.
 *
 * Loads tournament state from IndexedDB and provides
 * reactive data for the bracket, groups, match list, and stats.
 */

import { loadTournamentState, checkGroupStageTransition } from './state.js';
import { TournamentDB } from './db.js';

export default function tournamentHub(config) {
    return {
        tournamentId: config.tournamentId,
        loading: true,
        error: null,

        // Tournament data
        tournament: null,
        teams: [],
        teamMap: {},
        groupsByLetter: {},
        groupLetters: [],
        allMatches: [],
        allTies: [],
        groupMatches: [],
        knockoutMatches: [],

        // UI state
        activeTab: 'groups',
        selectedGroup: 'A',

        // Stats
        topScorers: [],
        topAssists: [],
        statsLoaded: false,

        // Simulate day
        simulatingDay: false,

        async init() {
            try {
                const state = await loadTournamentState(this.tournamentId);

                if (!state) {
                    this.error = 'Tournament not found in local storage.';
                    this.loading = false;
                    return;
                }

                Object.assign(this, state);
                this.loading = false;

                // Auto-switch to knockout tab if group stage is done
                if (this.tournament.currentPhase === 'knockout') {
                    this.activeTab = 'knockout';
                }
                if (this.tournament.status === 'completed') {
                    this.activeTab = 'knockout';
                }

                // Find user's group for initial selection
                const userGroup = this.allMatches.find(m =>
                    m.groupLetter && (m.homeTeamId === this.userTeamId || m.awayTeamId === this.userTeamId)
                );
                if (userGroup) {
                    this.selectedGroup = userGroup.groupLetter;
                }
            } catch (err) {
                this.error = err.message;
                this.loading = false;
            }
        },

        // --- Computed helpers ---

        get userTeamId() {
            return this.tournament?.userTeamId;
        },

        get currentPhase() {
            return this.tournament?.currentPhase || 'group_stage';
        },

        get isCompleted() {
            return this.tournament?.status === 'completed';
        },

        teamName(teamId) {
            return this.teamMap[teamId]?.name || 'TBD';
        },

        teamCode(teamId) {
            return this.teamMap[teamId]?.fifaCode || '???';
        },

        teamColors(teamId) {
            return this.teamMap[teamId]?.colors || {};
        },

        isUserTeam(teamId) {
            return teamId === this.userTeamId;
        },

        // --- Group stage helpers ---

        get selectedGroupStandings() {
            return this.groupsByLetter[this.selectedGroup] || [];
        },

        get selectedGroupMatches() {
            return this.groupMatches
                .filter(m => m.groupLetter === this.selectedGroup)
                .sort((a, b) => a.roundNumber - b.roundNumber);
        },

        get currentGroupRound() {
            // Current round = first unplayed round in user's group
            const userMatches = this.groupMatches.filter(m =>
                m.homeTeamId === this.userTeamId || m.awayTeamId === this.userTeamId
            );
            const unplayed = userMatches.find(m => !m.played);
            return unplayed?.roundNumber || 3;
        },

        // --- Knockout helpers ---

        get knockoutRounds() {
            const ROUND_NAMES = {
                1: 'Round of 32',
                2: 'Round of 16',
                3: 'Quarter-Finals',
                4: 'Semi-Finals',
                5: 'Third Place',
                6: 'Final',
            };

            const rounds = {};
            for (const tie of this.allTies) {
                if (!rounds[tie.roundNumber]) {
                    rounds[tie.roundNumber] = {
                        round: tie.roundNumber,
                        name: ROUND_NAMES[tie.roundNumber] || `Round ${tie.roundNumber}`,
                        ties: [],
                    };
                }
                rounds[tie.roundNumber].ties.push(tie);
            }

            return Object.values(rounds).sort((a, b) => a.round - b.round);
        },

        getTieMatch(tie) {
            return this.knockoutMatches.find(m => m.cupTieId === tie.id);
        },

        getTieScore(tie) {
            const match = this.getTieMatch(tie);
            if (!match || !match.played) return null;
            let score = `${match.homeScore} - ${match.awayScore}`;
            if (match.isExtraTime && (match.homeScoreEt > 0 || match.awayScoreEt > 0)) {
                score += ` (ET: ${match.homeScoreEt}-${match.awayScoreEt})`;
            }
            if (match.homeScorePenalties !== null && match.homeScorePenalties !== undefined) {
                score += ` (PEN: ${match.homeScorePenalties}-${match.awayScorePenalties})`;
            }
            return score;
        },

        // --- Navigation ---

        getMatchUrl(matchId) {
            return `/tournament/${this.tournamentId}/match/${matchId}`;
        },

        getNextUserMatch() {
            return this.allMatches.find(m =>
                !m.played &&
                (m.homeTeamId === this.userTeamId || m.awayTeamId === this.userTeamId)
            );
        },

        get nextUserMatch() {
            return this.getNextUserMatch();
        },

        // --- Stats ---

        async loadStats() {
            if (this.statsLoaded) return;
            try {
                const stats = await TournamentDB.getStatsForTournament(this.tournamentId);
                const players = await TournamentDB.getPlayersForTournament(this.tournamentId);
                const playerMap = {};
                players.forEach(p => { playerMap[p.id] = p; });

                // Enrich stats with player info
                const enriched = stats
                    .filter(s => s.appearances > 0)
                    .map(s => ({
                        ...s,
                        playerName: playerMap[s.playerId]?.name || 'Unknown',
                        teamName: this.teamMap[s.teamId]?.name || 'Unknown',
                        teamCode: this.teamMap[s.teamId]?.fifaCode || '???',
                    }));

                this.topScorers = [...enriched]
                    .filter(s => s.goals > 0)
                    .sort((a, b) => b.goals - a.goals || a.appearances - b.appearances)
                    .slice(0, 20);

                this.topAssists = [...enriched]
                    .filter(s => s.assists > 0)
                    .sort((a, b) => b.assists - a.assists || a.appearances - b.appearances)
                    .slice(0, 20);

                this.statsLoaded = true;
            } catch (e) {
                console.error('Failed to load stats:', e);
            }
        },

        // --- Actions ---

        async refresh() {
            this.loading = true;
            this.statsLoaded = false;
            const state = await loadTournamentState(this.tournamentId);
            if (state) Object.assign(this, state);
            this.loading = false;
        },

        async checkAdvancement() {
            const transitioned = await checkGroupStageTransition(this.tournamentId);
            if (transitioned) {
                await this.refresh();
                this.activeTab = 'knockout';
            }
        },

        async deleteTournament() {
            if (!confirm('Delete this tournament? This cannot be undone.')) return;
            await TournamentDB.deleteTournament(this.tournamentId);
            window.location.href = '/tournament';
        },
    };
}

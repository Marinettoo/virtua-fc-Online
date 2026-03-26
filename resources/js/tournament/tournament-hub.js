/**
 * Alpine.js component for the tournament hub view.
 *
 * Loads tournament state from IndexedDB and provides
 * reactive data for the bracket, groups, and match list.
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

        // --- Knockout helpers ---

        get knockoutRounds() {
            const ROUND_NAMES = {
                1: 'cup.round_of_32',
                2: 'cup.round_of_16',
                3: 'cup.quarter_finals',
                4: 'cup.semi_finals',
                5: 'cup.third_place',
                6: 'cup.final',
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

        // --- Actions ---

        async refresh() {
            this.loading = true;
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
    };
}

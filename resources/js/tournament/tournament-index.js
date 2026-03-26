/**
 * Alpine.js component for the tournament index/landing page.
 *
 * Lists existing tournaments from IndexedDB and allows
 * creating a new one via the server API.
 */

import { TournamentDB } from './db.js';
import { createTournament } from './state.js';

export default function tournamentIndex() {
    return {
        selectedTeamId: null,
        creating: false,
        error: null,
        existingTournaments: [],

        async init() {
            try {
                const all = await TournamentDB.listTournaments();
                this.existingTournaments = all
                    .filter(t => t.status !== 'completed')
                    .slice(0, 3);
            } catch (e) {
                // IndexedDB not available
            }
        },

        async startTournament() {
            if (!this.selectedTeamId || this.creating) return;
            this.creating = true;
            this.error = null;

            try {
                const tournamentId = await createTournament(this.selectedTeamId);
                window.location.href = '/tournament/' + tournamentId;
            } catch (err) {
                this.error = err.message;
                this.creating = false;
            }
        },
    };
}

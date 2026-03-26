/**
 * IndexedDB wrapper for client-side tournament storage.
 *
 * Stores all tournament state (bracket, teams, players, matches, events)
 * so the server only needs to bootstrap and receive final results.
 *
 * Database: "virtua-tournament", version 1
 */

const DB_NAME = 'virtua-tournament';
const DB_VERSION = 1;

const STORES = {
    TOURNAMENTS: 'tournaments',
    TEAMS: 'teams',
    PLAYERS: 'players',
    GROUP_STANDINGS: 'groupStandings',
    CUP_TIES: 'cupTies',
    MATCHES: 'matches',
    MATCH_EVENTS: 'matchEvents',
    PLAYER_STATS: 'playerStats',
};

let dbInstance = null;

/**
 * Open (or create) the IndexedDB database.
 * @returns {Promise<IDBDatabase>}
 */
function openDB() {
    if (dbInstance) return Promise.resolve(dbInstance);

    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;

            // Tournaments
            if (!db.objectStoreNames.contains(STORES.TOURNAMENTS)) {
                db.createObjectStore(STORES.TOURNAMENTS, { keyPath: 'id' });
            }

            // Teams
            if (!db.objectStoreNames.contains(STORES.TEAMS)) {
                const store = db.createObjectStore(STORES.TEAMS, { keyPath: 'id' });
                store.createIndex('tournamentId', 'tournamentId', { unique: false });
            }

            // Players
            if (!db.objectStoreNames.contains(STORES.PLAYERS)) {
                const store = db.createObjectStore(STORES.PLAYERS, { keyPath: 'id' });
                store.createIndex('teamId', 'teamId', { unique: false });
                store.createIndex('tournamentId', 'tournamentId', { unique: false });
            }

            // Group Standings
            if (!db.objectStoreNames.contains(STORES.GROUP_STANDINGS)) {
                const store = db.createObjectStore(STORES.GROUP_STANDINGS, { keyPath: ['tournamentId', 'groupLetter', 'teamId'] });
                store.createIndex('tournamentId', 'tournamentId', { unique: false });
                store.createIndex('groupLetter', ['tournamentId', 'groupLetter'], { unique: false });
            }

            // Cup Ties
            if (!db.objectStoreNames.contains(STORES.CUP_TIES)) {
                const store = db.createObjectStore(STORES.CUP_TIES, { keyPath: 'id' });
                store.createIndex('tournamentId', 'tournamentId', { unique: false });
                store.createIndex('roundNumber', ['tournamentId', 'roundNumber'], { unique: false });
            }

            // Matches
            if (!db.objectStoreNames.contains(STORES.MATCHES)) {
                const store = db.createObjectStore(STORES.MATCHES, { keyPath: 'id' });
                store.createIndex('tournamentId', 'tournamentId', { unique: false });
                store.createIndex('cupTieId', 'cupTieId', { unique: false });
                store.createIndex('groupMatch', ['tournamentId', 'groupLetter', 'roundNumber'], { unique: false });
            }

            // Match Events
            if (!db.objectStoreNames.contains(STORES.MATCH_EVENTS)) {
                const store = db.createObjectStore(STORES.MATCH_EVENTS, { keyPath: 'id' });
                store.createIndex('matchId', 'matchId', { unique: false });
            }

            // Player Stats (tournament-level aggregates)
            if (!db.objectStoreNames.contains(STORES.PLAYER_STATS)) {
                const store = db.createObjectStore(STORES.PLAYER_STATS, { keyPath: ['tournamentId', 'playerId'] });
                store.createIndex('tournamentId', 'tournamentId', { unique: false });
                store.createIndex('teamId', ['tournamentId', 'teamId'], { unique: false });
            }
        };

        request.onsuccess = (event) => {
            dbInstance = event.target.result;
            resolve(dbInstance);
        };

        request.onerror = (event) => {
            reject(new Error(`IndexedDB error: ${event.target.error}`));
        };
    });
}

/**
 * Generic helpers for IndexedDB CRUD operations.
 */

async function getRecord(storeName, key) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readonly');
        const store = tx.objectStore(storeName);
        const request = store.get(key);
        request.onsuccess = () => resolve(request.result || null);
        request.onerror = () => reject(request.error);
    });
}

async function putRecord(storeName, record) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readwrite');
        const store = tx.objectStore(storeName);
        const request = store.put(record);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function putRecords(storeName, records) {
    if (!records.length) return;
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readwrite');
        const store = tx.objectStore(storeName);
        records.forEach(r => store.put(r));
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

async function getAllByIndex(storeName, indexName, key) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readonly');
        const store = tx.objectStore(storeName);
        const index = store.index(indexName);
        const request = index.getAll(key);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function getAllRecords(storeName) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readonly');
        const store = tx.objectStore(storeName);
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function deleteByIndex(storeName, indexName, key) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readwrite');
        const store = tx.objectStore(storeName);
        const index = store.index(indexName);
        const request = index.openCursor(key);
        request.onsuccess = (event) => {
            const cursor = event.target.result;
            if (cursor) {
                cursor.delete();
                cursor.continue();
            }
        };
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

/**
 * Tournament-specific API.
 */

export const TournamentDB = {
    STORES,

    // --- Tournament ---

    async getTournament(id) {
        return getRecord(STORES.TOURNAMENTS, id);
    },

    async saveTournament(tournament) {
        return putRecord(STORES.TOURNAMENTS, tournament);
    },

    async listTournaments() {
        return getAllRecords(STORES.TOURNAMENTS);
    },

    // --- Teams ---

    async getTeam(id) {
        return getRecord(STORES.TEAMS, id);
    },

    async getTeamsForTournament(tournamentId) {
        return getAllByIndex(STORES.TEAMS, 'tournamentId', tournamentId);
    },

    async saveTeams(teams) {
        return putRecords(STORES.TEAMS, teams);
    },

    // --- Players ---

    async getPlayer(id) {
        return getRecord(STORES.PLAYERS, id);
    },

    async getPlayersForTeam(teamId) {
        return getAllByIndex(STORES.PLAYERS, 'teamId', teamId);
    },

    async getPlayersForTournament(tournamentId) {
        return getAllByIndex(STORES.PLAYERS, 'tournamentId', tournamentId);
    },

    async savePlayers(players) {
        return putRecords(STORES.PLAYERS, players);
    },

    async updatePlayer(player) {
        return putRecord(STORES.PLAYERS, player);
    },

    // --- Group Standings ---

    async getGroupStandings(tournamentId, groupLetter) {
        return getAllByIndex(STORES.GROUP_STANDINGS, 'groupLetter', [tournamentId, groupLetter]);
    },

    async getAllGroupStandings(tournamentId) {
        return getAllByIndex(STORES.GROUP_STANDINGS, 'tournamentId', tournamentId);
    },

    async saveGroupStandings(standings) {
        return putRecords(STORES.GROUP_STANDINGS, standings);
    },

    async updateGroupStanding(standing) {
        return putRecord(STORES.GROUP_STANDINGS, standing);
    },

    // --- Cup Ties ---

    async getCupTie(id) {
        return getRecord(STORES.CUP_TIES, id);
    },

    async getCupTiesForRound(tournamentId, roundNumber) {
        return getAllByIndex(STORES.CUP_TIES, 'roundNumber', [tournamentId, roundNumber]);
    },

    async getAllCupTies(tournamentId) {
        return getAllByIndex(STORES.CUP_TIES, 'tournamentId', tournamentId);
    },

    async saveCupTies(ties) {
        return putRecords(STORES.CUP_TIES, ties);
    },

    async updateCupTie(tie) {
        return putRecord(STORES.CUP_TIES, tie);
    },

    // --- Matches ---

    async getMatch(id) {
        return getRecord(STORES.MATCHES, id);
    },

    async getMatchesForTournament(tournamentId) {
        return getAllByIndex(STORES.MATCHES, 'tournamentId', tournamentId);
    },

    async getMatchesForCupTie(cupTieId) {
        return getAllByIndex(STORES.MATCHES, 'cupTieId', cupTieId);
    },

    async getGroupMatches(tournamentId, groupLetter, roundNumber) {
        return getAllByIndex(STORES.MATCHES, 'groupMatch', [tournamentId, groupLetter, roundNumber]);
    },

    async saveMatches(matches) {
        return putRecords(STORES.MATCHES, matches);
    },

    async updateMatch(match) {
        return putRecord(STORES.MATCHES, match);
    },

    // --- Match Events ---

    async getMatchEvents(matchId) {
        return getAllByIndex(STORES.MATCH_EVENTS, 'matchId', matchId);
    },

    async saveMatchEvents(events) {
        return putRecords(STORES.MATCH_EVENTS, events);
    },

    // --- Player Stats ---

    async getPlayerStats(tournamentId, playerId) {
        return getRecord(STORES.PLAYER_STATS, [tournamentId, playerId]);
    },

    async getStatsForTournament(tournamentId) {
        return getAllByIndex(STORES.PLAYER_STATS, 'tournamentId', tournamentId);
    },

    async getStatsForTeam(tournamentId, teamId) {
        return getAllByIndex(STORES.PLAYER_STATS, 'teamId', [tournamentId, teamId]);
    },

    async savePlayerStats(stats) {
        return putRecords(STORES.PLAYER_STATS, stats);
    },

    async updatePlayerStats(stat) {
        return putRecord(STORES.PLAYER_STATS, stat);
    },

    // --- Bulk Operations ---

    /**
     * Store an entire tournament payload from the server bootstrap endpoint.
     * Expects the shape returned by POST /tournament/create.
     */
    async storeTournamentPayload(payload) {
        const { tournament, teams, players, matches, groupStandings } = payload;

        await this.saveTournament(tournament);
        await this.saveTeams(teams);
        await this.savePlayers(players);
        await this.saveMatches(matches);
        if (groupStandings && groupStandings.length) {
            await this.saveGroupStandings(groupStandings);
        }

        // Initialize empty player stats for all players
        const stats = players.map(p => ({
            tournamentId: tournament.id,
            playerId: p.id,
            teamId: p.teamId,
            appearances: 0,
            goals: 0,
            assists: 0,
            yellowCards: 0,
            redCards: 0,
            cleanSheets: 0,
            minutesPlayed: 0,
        }));
        await this.savePlayerStats(stats);

        return tournament.id;
    },

    /**
     * Delete all data for a tournament.
     */
    async deleteTournament(tournamentId) {
        const db = await openDB();
        const storeNames = Object.values(STORES);
        const tx = db.transaction(storeNames, 'readwrite');

        // Delete tournament record
        tx.objectStore(STORES.TOURNAMENTS).delete(tournamentId);

        // Delete by index for stores that have tournamentId index
        const indexedStores = [
            STORES.TEAMS, STORES.PLAYERS, STORES.GROUP_STANDINGS,
            STORES.CUP_TIES, STORES.MATCHES, STORES.PLAYER_STATS,
        ];

        for (const storeName of indexedStores) {
            const store = tx.objectStore(storeName);
            const index = store.index('tournamentId');
            const request = index.openCursor(tournamentId);
            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    cursor.delete();
                    cursor.continue();
                }
            };
        }

        // Match events need to be cleaned up via match IDs
        // (they don't have a tournamentId index, only matchId)
        // This is handled by first getting all match IDs, then deleting events
        const matchStore = tx.objectStore(STORES.MATCHES);
        const matchIndex = matchStore.index('tournamentId');
        const matchRequest = matchIndex.getAll(tournamentId);
        matchRequest.onsuccess = () => {
            const matches = matchRequest.result;
            const eventStore = tx.objectStore(STORES.MATCH_EVENTS);
            const eventIndex = eventStore.index('matchId');
            matches.forEach(match => {
                const req = eventIndex.openCursor(match.id);
                req.onsuccess = (e) => {
                    const cursor = e.target.result;
                    if (cursor) {
                        cursor.delete();
                        cursor.continue();
                    }
                };
            });
        };

        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    },

    /**
     * Check if a tournament exists in IndexedDB.
     */
    async hasTournament(tournamentId) {
        const t = await this.getTournament(tournamentId);
        return t !== null;
    },
};

export default TournamentDB;

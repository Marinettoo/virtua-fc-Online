/**
 * Tournament state manager.
 *
 * Bridges IndexedDB storage with Alpine.js components.
 * Provides reactive state loading and API communication.
 */

import { TournamentDB } from './db.js';
import {
    updateGroupStandings,
    isGroupStageComplete,
    getQualifiedTeams,
    generateRoundOf32,
    generateKnockoutRound,
    resolveCupTie,
    updatePlayerStats,
} from './bracket-manager.js';

const CSRF_TOKEN = () => document.querySelector('meta[name="csrf-token"]')?.content;

/**
 * Create a new tournament by calling the server bootstrap endpoint.
 */
export async function createTournament(userTeamId) {
    const response = await fetch('/tournament/create', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN(),
        },
        body: JSON.stringify({ userTeamId }),
    });

    if (!response.ok) {
        throw new Error(`Tournament creation failed: ${response.status}`);
    }

    const payload = await response.json();
    const tournamentId = await TournamentDB.storeTournamentPayload(payload);

    return tournamentId;
}

/**
 * Load tournament state from IndexedDB for the hub view.
 */
export async function loadTournamentState(tournamentId) {
    const tournament = await TournamentDB.getTournament(tournamentId);
    if (!tournament) return null;

    const teams = await TournamentDB.getTeamsForTournament(tournamentId);
    const allStandings = await TournamentDB.getAllGroupStandings(tournamentId);
    const allMatches = await TournamentDB.getMatchesForTournament(tournamentId);
    const allTies = await TournamentDB.getAllCupTies(tournamentId);

    // Group standings by group letter
    const groupsByLetter = {};
    const groupLetters = [...new Set(allStandings.map(s => s.groupLetter))].sort();
    for (const letter of groupLetters) {
        groupsByLetter[letter] = allStandings
            .filter(s => s.groupLetter === letter)
            .sort((a, b) => a.position - b.position);
    }

    // Build team lookup
    const teamMap = {};
    teams.forEach(t => { teamMap[t.id] = t; });

    return {
        tournament,
        teams,
        teamMap,
        groupsByLetter,
        groupLetters,
        allMatches,
        allTies,
        groupMatches: allMatches.filter(m => m.groupLetter && !m.cupTieId),
        knockoutMatches: allMatches.filter(m => m.cupTieId),
    };
}

/**
 * Simulate a match via the server API and store results in IndexedDB.
 */
export async function simulateMatch(tournamentId, matchId, payload) {
    const response = await fetch('/tournament/simulate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN(),
        },
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        throw new Error(`Match simulation failed: ${response.status}`);
    }

    const result = await response.json();

    // Update match in IndexedDB
    const match = await TournamentDB.getMatch(matchId);
    match.homeScore = result.homeScore;
    match.awayScore = result.awayScore;
    match.homePossession = result.homePossession;
    match.awayPossession = result.awayPossession;
    match.mvpPlayerId = result.mvpPlayerId || null;
    match.played = true;
    match.homeLineup = payload.homeTeam.lineup.map(p => p.id);
    match.awayLineup = payload.awayTeam.lineup.map(p => p.id);
    match.homeFormation = payload.homeFormation || '4-4-2';
    match.awayFormation = payload.awayFormation || '4-4-2';
    await TournamentDB.updateMatch(match);

    // Store events
    const events = (result.events || []).map(e => ({
        ...e,
        id: crypto.randomUUID(),
        matchId,
    }));
    await TournamentDB.saveMatchEvents(events);

    // Update standings or cup tie
    if (match.groupLetter) {
        await updateGroupStandings(tournamentId, match);
    }

    if (match.cupTieId) {
        await resolveCupTie(tournamentId, match);
    }

    // Update player stats
    await updatePlayerStats(tournamentId, match, events);

    return { match, events, result };
}

/**
 * Simulate extra time for a knockout match.
 */
export async function simulateExtraTime(tournamentId, matchId, payload) {
    const response = await fetch('/tournament/simulate-extra-time', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN(),
        },
        body: JSON.stringify(payload),
    });

    if (!response.ok) throw new Error(`ET simulation failed: ${response.status}`);

    const result = await response.json();

    const match = await TournamentDB.getMatch(matchId);
    match.isExtraTime = true;
    match.homeScoreEt = result.homeScoreET;
    match.awayScoreEt = result.awayScoreET;
    await TournamentDB.updateMatch(match);

    const events = (result.events || []).map(e => ({
        ...e,
        id: crypto.randomUUID(),
        matchId,
    }));
    await TournamentDB.saveMatchEvents(events);

    return { match, events, result };
}

/**
 * Simulate penalties for a knockout match.
 */
export async function simulatePenalties(tournamentId, matchId, payload) {
    const response = await fetch('/tournament/simulate-penalties', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN(),
        },
        body: JSON.stringify(payload),
    });

    if (!response.ok) throw new Error(`Penalty simulation failed: ${response.status}`);

    const result = await response.json();

    const match = await TournamentDB.getMatch(matchId);
    match.homeScorePenalties = result.homeScorePenalties;
    match.awayScorePenalties = result.awayScorePenalties;
    await TournamentDB.updateMatch(match);

    // Resolve the cup tie with penalty result
    await resolveCupTie(tournamentId, match);

    return { match, result };
}

/**
 * Batch simulate CPU vs CPU matches.
 */
export async function simulateBatch(tournamentId, matchPayloads) {
    const response = await fetch('/tournament/simulate-batch', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN(),
        },
        body: JSON.stringify({ matches: matchPayloads }),
    });

    if (!response.ok) throw new Error(`Batch simulation failed: ${response.status}`);

    const { results } = await response.json();

    // Store all results
    for (const { matchId, result } of results) {
        if (!matchId) continue;

        const match = await TournamentDB.getMatch(matchId);
        if (!match) continue;

        match.homeScore = result.homeScore;
        match.awayScore = result.awayScore;
        match.homePossession = result.homePossession;
        match.awayPossession = result.awayPossession;
        match.mvpPlayerId = result.mvpPlayerId || null;
        match.played = true;
        await TournamentDB.updateMatch(match);

        const events = (result.events || []).map(e => ({
            ...e,
            id: crypto.randomUUID(),
            matchId,
        }));
        await TournamentDB.saveMatchEvents(events);

        if (match.groupLetter) {
            await updateGroupStandings(tournamentId, match);
        }

        if (match.cupTieId) {
            await resolveCupTie(tournamentId, match);
        }

        await updatePlayerStats(tournamentId, match, events);
    }

    return results;
}

/**
 * Get auto-lineup from server.
 */
export async function getAutoLineup(players, formation) {
    const response = await fetch('/tournament/auto-lineup', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN(),
        },
        body: JSON.stringify({ players, formation }),
    });

    if (!response.ok) throw new Error(`Auto-lineup failed: ${response.status}`);

    return response.json();
}

/**
 * Check if the group stage is done and transition to knockout if needed.
 */
export async function checkGroupStageTransition(tournamentId) {
    const complete = await isGroupStageComplete(tournamentId);
    if (!complete) return false;

    const tournament = await TournamentDB.getTournament(tournamentId);
    if (tournament.currentPhase !== 'group_stage') return false;

    await generateRoundOf32(tournamentId);
    return true;
}

/**
 * Complete tournament and sync to server.
 */
export async function completeTournament(tournamentId) {
    const tournament = await TournamentDB.getTournament(tournamentId);
    const teams = await TournamentDB.getTeamsForTournament(tournamentId);
    const allTies = await TournamentDB.getAllCupTies(tournamentId);
    const stats = await TournamentDB.getStatsForTournament(tournamentId);
    const allMatches = await TournamentDB.getMatchesForTournament(tournamentId);

    const teamMap = {};
    teams.forEach(t => { teamMap[t.id] = t; });

    // Find champion (final winner)
    const finalTie = allTies.find(t => t.roundNumber === 6 && t.completed);
    const thirdPlaceTie = allTies.find(t => t.roundNumber === 5 && t.completed);

    const champion = finalTie ? teamMap[finalTie.winnerId] : null;
    const runnerUp = finalTie
        ? teamMap[finalTie.winnerId === finalTie.homeTeamId ? finalTie.awayTeamId : finalTie.homeTeamId]
        : null;
    const thirdPlace = thirdPlaceTie ? teamMap[thirdPlaceTie.winnerId] : null;

    // Top scorer
    const topScorer = stats.sort((a, b) => b.goals - a.goals)[0];
    const topScorerPlayer = topScorer ? await TournamentDB.getPlayer(topScorer.playerId) : null;

    // User team finish
    const userTeamId = tournament.userTeamId;
    const userTeamFinish = determineUserFinish(userTeamId, allTies, allMatches);

    const payload = {
        tournamentId,
        champion: champion ? { teamId: champion.id, teamName: champion.name } : null,
        runnerUp: runnerUp ? { teamId: runnerUp.id, teamName: runnerUp.name } : null,
        thirdPlace: thirdPlace ? { teamId: thirdPlace.id, teamName: thirdPlace.name } : null,
        topScorer: topScorerPlayer ? { name: topScorerPlayer.name, goals: topScorer.goals } : null,
        userTeamId,
        userTeamFinish,
        totalMatches: allMatches.filter(m => m.played).length,
    };

    const response = await fetch('/tournament/complete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN(),
        },
        body: JSON.stringify(payload),
    });

    if (!response.ok) throw new Error(`Tournament completion failed: ${response.status}`);

    tournament.status = 'completed';
    await TournamentDB.saveTournament(tournament);

    return payload;
}

function determineUserFinish(userTeamId, allTies, allMatches) {
    const ROUND_NAMES = {
        6: 'Champion',
        5: 'Third Place',
        4: 'Semi-Finals',
        3: 'Quarter-Finals',
        2: 'Round of 16',
        1: 'Round of 32',
    };

    // Check knockout ties in reverse order
    for (const round of [6, 5, 4, 3, 2, 1]) {
        const tie = allTies.find(t =>
            t.roundNumber === round &&
            (t.homeTeamId === userTeamId || t.awayTeamId === userTeamId)
        );

        if (tie) {
            if (tie.winnerId === userTeamId) {
                if (round === 6) return 'Champion';
                if (round === 5) return 'Third Place';
                continue;
            }
            return ROUND_NAMES[round] || `Round ${round}`;
        }
    }

    // User didn't make knockout
    return 'Group Stage';
}

export default {
    createTournament,
    loadTournamentState,
    simulateMatch,
    simulateExtraTime,
    simulatePenalties,
    simulateBatch,
    getAutoLineup,
    checkGroupStageTransition,
    completeTournament,
};

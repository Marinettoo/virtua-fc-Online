/**
 * Client-side bracket management for World Cup tournament.
 *
 * Handles group standings calculation, third-place qualification,
 * knockout bracket advancement, and round generation — all from IndexedDB.
 */

import { TournamentDB } from './db.js';

/**
 * Update group standings after a match result.
 */
export async function updateGroupStandings(tournamentId, match) {
    if (!match.groupLetter || !match.played) return;

    const standings = await TournamentDB.getGroupStandings(tournamentId, match.groupLetter);

    const homeStanding = standings.find(s => s.teamId === match.homeTeamId);
    const awayStanding = standings.find(s => s.teamId === match.awayTeamId);

    if (!homeStanding || !awayStanding) return;

    homeStanding.played++;
    awayStanding.played++;
    homeStanding.goalsFor += match.homeScore;
    homeStanding.goalsAgainst += match.awayScore;
    awayStanding.goalsFor += match.awayScore;
    awayStanding.goalsAgainst += match.homeScore;

    if (match.homeScore > match.awayScore) {
        homeStanding.won++;
        homeStanding.points += 3;
        awayStanding.lost++;
    } else if (match.homeScore < match.awayScore) {
        awayStanding.won++;
        awayStanding.points += 3;
        homeStanding.lost++;
    } else {
        homeStanding.drawn++;
        awayStanding.drawn++;
        homeStanding.points += 1;
        awayStanding.points += 1;
    }

    homeStanding.goalDifference = homeStanding.goalsFor - homeStanding.goalsAgainst;
    awayStanding.goalDifference = awayStanding.goalsFor - awayStanding.goalsAgainst;

    // Recalculate positions for entire group
    const groupStandings = standings.sort((a, b) => {
        if (b.points !== a.points) return b.points - a.points;
        if (b.goalDifference !== a.goalDifference) return b.goalDifference - a.goalDifference;
        if (b.goalsFor !== a.goalsFor) return b.goalsFor - a.goalsFor;
        return a.teamId.localeCompare(b.teamId);
    });

    groupStandings.forEach((s, i) => { s.position = i + 1; });

    await TournamentDB.saveGroupStandings(groupStandings);
}

/**
 * Check if the group stage is complete (all group matches played).
 */
export async function isGroupStageComplete(tournamentId) {
    const matches = await TournamentDB.getMatchesForTournament(tournamentId);
    const groupMatches = matches.filter(m => m.groupLetter && !m.cupTieId);

    return groupMatches.length > 0 && groupMatches.every(m => m.played);
}

/**
 * Get the 32 teams that qualify from the group stage.
 * Top 2 per group (24 teams) + best 8 third-place teams.
 *
 * @returns {{ top2: Array, thirdPlace: Array, qualifyingGroups: string }}
 */
export async function getQualifiedTeams(tournamentId) {
    const allStandings = await TournamentDB.getAllGroupStandings(tournamentId);

    // Top 2 from each group
    const top2 = allStandings
        .filter(s => s.position <= 2)
        .sort((a, b) => {
            if (a.groupLetter !== b.groupLetter) return a.groupLetter.localeCompare(b.groupLetter);
            return a.position - b.position;
        });

    // All third-place teams, ranked
    const thirdPlace = allStandings
        .filter(s => s.position === 3)
        .sort((a, b) => {
            if (b.points !== a.points) return b.points - a.points;
            if (b.goalDifference !== a.goalDifference) return b.goalDifference - a.goalDifference;
            if (b.goalsFor !== a.goalsFor) return b.goalsFor - a.goalsFor;
            return a.groupLetter.localeCompare(b.groupLetter);
        })
        .slice(0, 8);

    const qualifyingGroups = thirdPlace
        .map(s => s.groupLetter)
        .sort()
        .join('');

    return { top2, thirdPlace, qualifyingGroups };
}

/**
 * Generate Round of 32 matchups from group stage results.
 * Uses the fixed bracket and third-place table from tournament data.
 */
export async function generateRoundOf32(tournamentId) {
    const tournament = await TournamentDB.getTournament(tournamentId);
    if (!tournament) throw new Error('Tournament not found');

    const bracket = tournament.bracket;
    const thirdPlaceTable = tournament.thirdPlaceTable;
    const allStandings = await TournamentDB.getAllGroupStandings(tournamentId);

    // Build position map: "1A" → teamId, "2B" → teamId
    const positionMap = {};
    allStandings.forEach(s => {
        positionMap[`${s.position}${s.groupLetter}`] = s.teamId;
    });

    // Get qualified third-place teams
    const { thirdPlace, qualifyingGroups } = await getQualifiedTeams(tournamentId);

    // Look up the assignment from the FIFA table
    const assignment = thirdPlaceTable[qualifyingGroups];
    if (!assignment) {
        throw new Error(`No third-place assignment for qualifying groups: ${qualifyingGroups}`);
    }

    // Build third-place assignment: match_number → teamId
    const THIRD_PLACE_SLOT_KEYS = ['1A', '1B', '1D', '1E', '1G', '1I', '1K', '1L'];
    const THIRD_PLACE_MATCH_MAP = { '1A': 79, '1B': 85, '1D': 82, '1E': 75, '1G': 81, '1I': 78, '1K': 88, '1L': 80 };

    const teamByGroup = {};
    thirdPlace.forEach(s => { teamByGroup[s.groupLetter] = s.teamId; });

    const thirdPlaceAssignment = {};
    THIRD_PLACE_SLOT_KEYS.forEach((slotKey, index) => {
        const groupLetter = assignment[index];
        const matchNumber = THIRD_PLACE_MATCH_MAP[slotKey];
        thirdPlaceAssignment[matchNumber] = teamByGroup[groupLetter];
    });

    // Generate R32 ties and matches
    const r32Matches = bracket.round_of_32 || [];
    const schedule = tournament.schedule;
    const r32Round = schedule.knockout.find(r => r.round === 1);

    const ties = [];
    const matches = [];

    for (const entry of r32Matches) {
        const homeTeamId = resolveR32Slot(entry.home, positionMap, thirdPlaceAssignment, r32Matches);
        const awayTeamId = resolveR32Slot(entry.away, positionMap, thirdPlaceAssignment, r32Matches);

        if (!homeTeamId || !awayTeamId) continue;

        const tieId = crypto.randomUUID();

        ties.push({
            id: tieId,
            tournamentId,
            roundNumber: 1,
            bracketPosition: entry.match_number,
            homeTeamId,
            awayTeamId,
            winnerId: null,
            completed: false,
            resolution: null,
        });

        matches.push({
            id: crypto.randomUUID(),
            tournamentId,
            cupTieId: tieId,
            groupLetter: null,
            roundNumber: 1,
            homeTeamId,
            awayTeamId,
            homeScore: null,
            awayScore: null,
            isExtraTime: false,
            homeScoreEt: null,
            awayScoreEt: null,
            homeScorePenalties: null,
            awayScorePenalties: null,
            homeLineup: null,
            awayLineup: null,
            homeFormation: '4-4-2',
            awayFormation: '4-4-2',
            homePossession: null,
            awayPossession: null,
            played: false,
            mvpPlayerId: null,
            substitutions: [],
            scheduledDate: entry.date || r32Round?.date,
            matchNumber: entry.match_number,
        });
    }

    await TournamentDB.saveCupTies(ties);
    await TournamentDB.saveMatches(matches);

    // Update tournament phase
    tournament.currentPhase = 'knockout';
    tournament.currentRound = 1;
    await TournamentDB.saveTournament(tournament);

    return { ties, matches };
}

function resolveR32Slot(slot, positionMap, thirdPlaceAssignment, r32Matches) {
    // Simple position + group: "1A", "2B"
    const simpleMatch = slot.match(/^([12])([A-L])$/);
    if (simpleMatch) {
        return positionMap[`${simpleMatch[1]}${simpleMatch[2]}`] || null;
    }

    // Third-place slot: "3ABCDF"
    if (slot.startsWith('3') && slot.length > 2) {
        for (const entry of r32Matches) {
            if (entry.home === slot || entry.away === slot) {
                return thirdPlaceAssignment[entry.match_number] || null;
            }
        }
    }

    return null;
}

/**
 * Generate the next knockout round from completed cup ties.
 *
 * @param {number} nextRound - The round number to generate (2=R16, 3=QF, 4=SF, 5=3rd, 6=Final)
 */
export async function generateKnockoutRound(tournamentId, nextRound) {
    const tournament = await TournamentDB.getTournament(tournamentId);
    if (!tournament) throw new Error('Tournament not found');

    const bracket = tournament.bracket;
    const schedule = tournament.schedule;

    // Special handling for semi-finals completion: generate both 3rd place and final
    if (nextRound === 5) {
        await generateFromSemiFinals(tournamentId, tournament, 5); // 3rd place
        await generateFromSemiFinals(tournamentId, tournament, 6); // Final
        return;
    }

    if (nextRound === 6) {
        // Already generated with semi-finals
        return;
    }

    const ROUND_KEY_MAP = {
        2: 'round_of_16',
        3: 'quarter_finals',
        4: 'semi_finals',
    };

    const roundKey = ROUND_KEY_MAP[nextRound];
    if (!roundKey) throw new Error(`Unknown round: ${nextRound}`);

    const roundMatches = bracket[roundKey] || [];
    const roundConfig = schedule.knockout.find(r => r.round === nextRound);
    const allTies = await TournamentDB.getAllCupTies(tournamentId);

    const ties = [];
    const matches = [];

    for (const entry of roundMatches) {
        const homeTeamId = resolveBracketReference(entry.home, allTies);
        const awayTeamId = resolveBracketReference(entry.away, allTies);

        if (!homeTeamId || !awayTeamId) continue;

        const tieId = crypto.randomUUID();

        ties.push({
            id: tieId,
            tournamentId,
            roundNumber: nextRound,
            bracketPosition: entry.match_number,
            homeTeamId,
            awayTeamId,
            winnerId: null,
            completed: false,
            resolution: null,
        });

        matches.push({
            id: crypto.randomUUID(),
            tournamentId,
            cupTieId: tieId,
            groupLetter: null,
            roundNumber: nextRound,
            homeTeamId,
            awayTeamId,
            homeScore: null,
            awayScore: null,
            isExtraTime: false,
            homeScoreEt: null,
            awayScoreEt: null,
            homeScorePenalties: null,
            awayScorePenalties: null,
            homeLineup: null,
            awayLineup: null,
            homeFormation: '4-4-2',
            awayFormation: '4-4-2',
            homePossession: null,
            awayPossession: null,
            played: false,
            mvpPlayerId: null,
            substitutions: [],
            scheduledDate: entry.date || roundConfig?.date,
            matchNumber: entry.match_number,
        });
    }

    await TournamentDB.saveCupTies(ties);
    await TournamentDB.saveMatches(matches);

    tournament.currentRound = nextRound;
    await TournamentDB.saveTournament(tournament);

    return { ties, matches };
}

async function generateFromSemiFinals(tournamentId, tournament, round) {
    const bracket = tournament.bracket;
    const schedule = tournament.schedule;
    const roundKey = round === 5 ? 'third_place' : 'final';
    const roundMatches = bracket[roundKey] || [];

    if (!roundMatches.length) return;

    const sfTies = (await TournamentDB.getCupTiesForRound(tournamentId, 4))
        .filter(t => t.completed)
        .sort((a, b) => (a.bracketPosition || 0) - (b.bracketPosition || 0));

    if (sfTies.length !== 2) return;

    const entry = roundMatches[0];
    const roundConfig = schedule.knockout.find(r => r.round === round);

    let homeTeamId, awayTeamId;
    if (round === 5) {
        // Third place: SF losers
        homeTeamId = getLoser(sfTies[0]);
        awayTeamId = getLoser(sfTies[1]);
    } else {
        // Final: SF winners
        homeTeamId = sfTies[0].winnerId;
        awayTeamId = sfTies[1].winnerId;
    }

    if (!homeTeamId || !awayTeamId) return;

    const tieId = crypto.randomUUID();

    await TournamentDB.saveCupTies([{
        id: tieId,
        tournamentId,
        roundNumber: round,
        bracketPosition: entry.match_number,
        homeTeamId,
        awayTeamId,
        winnerId: null,
        completed: false,
        resolution: null,
    }]);

    await TournamentDB.saveMatches([{
        id: crypto.randomUUID(),
        tournamentId,
        cupTieId: tieId,
        groupLetter: null,
        roundNumber: round,
        homeTeamId,
        awayTeamId,
        homeScore: null,
        awayScore: null,
        isExtraTime: false,
        homeScoreEt: null,
        awayScoreEt: null,
        homeScorePenalties: null,
        awayScorePenalties: null,
        homeLineup: null,
        awayLineup: null,
        homeFormation: '4-4-2',
        awayFormation: '4-4-2',
        homePossession: null,
        awayPossession: null,
        played: false,
        mvpPlayerId: null,
        substitutions: [],
        scheduledDate: entry.date || roundConfig?.date,
        matchNumber: entry.match_number,
    }]);
}

function getLoser(tie) {
    if (!tie.winnerId) return null;
    return tie.winnerId === tie.homeTeamId ? tie.awayTeamId : tie.homeTeamId;
}

function resolveBracketReference(ref, allTies) {
    // Winner reference: "W73"
    const winnerMatch = ref.match(/^W(\d+)$/);
    if (winnerMatch) {
        const matchNumber = parseInt(winnerMatch[1]);
        const tie = allTies.find(t => t.bracketPosition === matchNumber && t.completed);
        return tie?.winnerId || null;
    }

    // Runner-up / loser reference: "RU101"
    const ruMatch = ref.match(/^RU(\d+)$/);
    if (ruMatch) {
        const matchNumber = parseInt(ruMatch[1]);
        const tie = allTies.find(t => t.bracketPosition === matchNumber && t.completed);
        return tie ? getLoser(tie) : null;
    }

    return null;
}

/**
 * Resolve a completed cup tie: determine winner and update state.
 */
export async function resolveCupTie(tournamentId, matchResult) {
    if (!matchResult.cupTieId) return null;

    const tie = await TournamentDB.getCupTie(matchResult.cupTieId);
    if (!tie || tie.completed) return null;

    let winnerId;
    let resolution;

    if (matchResult.homeScorePenalties !== null && matchResult.awayScorePenalties !== null) {
        winnerId = matchResult.homeScorePenalties > matchResult.awayScorePenalties
            ? tie.homeTeamId : tie.awayTeamId;
        resolution = { type: 'penalties' };
    } else if (matchResult.isExtraTime) {
        const totalHome = (matchResult.homeScore || 0) + (matchResult.homeScoreEt || 0);
        const totalAway = (matchResult.awayScore || 0) + (matchResult.awayScoreEt || 0);
        winnerId = totalHome > totalAway ? tie.homeTeamId : tie.awayTeamId;
        resolution = { type: 'extra_time' };
    } else {
        winnerId = matchResult.homeScore > matchResult.awayScore
            ? tie.homeTeamId : tie.awayTeamId;
        resolution = { type: 'regular' };
    }

    tie.winnerId = winnerId;
    tie.completed = true;
    tie.resolution = resolution;
    await TournamentDB.updateCupTie(tie);

    // Check if the current round is complete and generate next round
    await maybeAdvanceRound(tournamentId, tie.roundNumber);

    return tie;
}

/**
 * Check if all ties in a round are complete and generate the next round.
 */
async function maybeAdvanceRound(tournamentId, currentRound) {
    const ties = await TournamentDB.getCupTiesForRound(tournamentId, currentRound);
    const allComplete = ties.length > 0 && ties.every(t => t.completed);

    if (!allComplete) return;

    const ROUND_NAMES = { 1: 'R32', 2: 'R16', 3: 'QF', 4: 'SF', 5: '3rd', 6: 'Final' };
    const nextRound = currentRound + 1;

    // After semi-finals, generate both 3rd place and final
    if (currentRound === 4) {
        await generateKnockoutRound(tournamentId, 5);
        return;
    }

    // Skip 3rd place → final (they're generated together after SF)
    if (currentRound === 5) return;

    // Final complete = tournament over
    if (currentRound === 6) {
        const tournament = await TournamentDB.getTournament(tournamentId);
        tournament.status = 'completed';
        await TournamentDB.saveTournament(tournament);
        return;
    }

    if (nextRound <= 4) {
        await generateKnockoutRound(tournamentId, nextRound);
    }
}

/**
 * Update player stats after a match.
 */
export async function updatePlayerStats(tournamentId, match, events) {
    const allLineupIds = [
        ...(match.homeLineup || []).map(p => p.id || p),
        ...(match.awayLineup || []).map(p => p.id || p),
    ];

    for (const playerId of allLineupIds) {
        const stat = await TournamentDB.getPlayerStats(tournamentId, playerId);
        if (!stat) continue;

        stat.appearances++;
        stat.minutesPlayed += 90; // simplified

        const playerEvents = events.filter(e => e.game_player_id === playerId || e.playerId === playerId);

        for (const event of playerEvents) {
            const type = event.event_type || event.eventType;
            if (type === 'goal') stat.goals++;
            else if (type === 'assist') stat.assists++;
            else if (type === 'yellow_card') stat.yellowCards++;
            else if (type === 'red_card') stat.redCards++;
        }

        // Clean sheet for goalkeepers
        const teamId = stat.teamId;
        const isHome = match.homeTeamId === teamId;
        const opponentScore = isHome ? match.awayScore : match.homeScore;
        if (opponentScore === 0) {
            const player = await TournamentDB.getPlayer(playerId);
            if (player?.position === 'Goalkeeper') {
                stat.cleanSheets++;
            }
        }

        await TournamentDB.updatePlayerStats(stat);
    }
}

export default {
    updateGroupStandings,
    isGroupStageComplete,
    getQualifiedTeams,
    generateRoundOf32,
    generateKnockoutRound,
    resolveCupTie,
    updatePlayerStats,
};

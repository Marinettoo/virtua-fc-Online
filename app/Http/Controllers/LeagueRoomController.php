<?php

namespace App\Http\Controllers;

use App\Models\LeagueRoom;
use App\Models\LeagueRoomMember;
use App\Models\LeagueRoomMatchday;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeagueRoomController extends Controller
{
    public function create()
    {
        return view('league-room.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'auto_advance_hours' => 'required|integer|min:1|max:72',
        ]);

        $room = LeagueRoom::create([
            'name' => $request->name,
            'code' => LeagueRoom::generateCode(),
            'owner_id' => Auth::id(),
            'auto_advance_hours' => $request->auto_advance_hours,
            'status' => 'waiting',
        ]);

        LeagueRoomMember::create([
            'league_room_id' => $room->id,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('league-room.lobby', $room->id)
            ->with('success', '\u00a1Sala creada! Comparte el c\u00f3digo ' . $room->code . ' con tus amigos.');
    }

    public function join()
    {
        return view('league-room.join');
    }

    public function joinStore(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:8',
        ]);

        $room = LeagueRoom::where('code', strtoupper($request->code))
            ->where('status', 'waiting')
            ->firstOrFail();

        $alreadyMember = LeagueRoomMember::where('league_room_id', $room->id)
            ->where('user_id', Auth::id())
            ->exists();

        if ($alreadyMember) {
            return redirect()->route('league-room.lobby', $room->id);
        }

        LeagueRoomMember::create([
            'league_room_id' => $room->id,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('league-room.lobby', $room->id)
            ->with('success', '\u00a1Te has unido a ' . $room->name . '!');
    }

    public function lobby(LeagueRoom $leagueRoom)
    {
        $leagueRoom->load('members.user', 'members.team');
        $member = $leagueRoom->members->firstWhere('user_id', Auth::id());

        abort_if(!$member, 403, 'No eres miembro de esta sala.');

        // IDs de equipos ya cogidos por otros
        $takenTeamIds = $leagueRoom->members
            ->where('user_id', '!=', Auth::id())
            ->pluck('team_id')
            ->filter()
            ->values();

        // Equipos de clubs reales, ordenados por nombre, excluyendo reservas y placeholders
        $teams = Team::where('type', 'club')
            ->where('is_placeholder', false)
            ->whereNull('parent_team_id')
            ->whereNotIn('id', $takenTeamIds)
            ->orderBy('name')
            ->get(['id', 'name', 'country']);

        return view('league-room.lobby', [
            'room' => $leagueRoom,
            'members' => $leagueRoom->members,
            'myMember' => $member,
            'teams' => $teams,
        ]);
    }

    public function chooseTeam(Request $request, LeagueRoom $leagueRoom)
    {
        $request->validate([
            'team_id' => 'required|string|exists:teams,id',
        ]);

        $member = LeagueRoomMember::where('league_room_id', $leagueRoom->id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $teamTaken = LeagueRoomMember::where('league_room_id', $leagueRoom->id)
            ->where('team_id', $request->team_id)
            ->where('user_id', '!=', Auth::id())
            ->exists();

        if ($teamTaken) {
            return back()->with('error', 'Ese equipo ya est\u00e1 cogido por otro jugador.');
        }

        $member->update(['team_id' => $request->team_id]);

        return back()->with('success', '\u00a1Equipo seleccionado!');
    }

    public function start(LeagueRoom $leagueRoom)
    {
        abort_if($leagueRoom->owner_id !== Auth::id(), 403);
        abort_if($leagueRoom->members()->whereNull('team_id')->exists(), 422, 'Todos los jugadores deben elegir equipo antes de empezar.');

        $leagueRoom->update(['status' => 'active']);

        LeagueRoomMatchday::create([
            'league_room_id' => $leagueRoom->id,
            'matchday_number' => 1,
            'status' => 'pending',
            'auto_advance_at' => now()->addHours($leagueRoom->auto_advance_hours),
        ]);

        return redirect()->route('league-room.dashboard', $leagueRoom->id)
            ->with('success', '\u00a1Liga iniciada!');
    }

    public function dashboard(LeagueRoom $leagueRoom)
    {
        $leagueRoom->load('members.user', 'matchdays');
        $member = $leagueRoom->members->firstWhere('user_id', Auth::id());

        abort_if(!$member, 403);

        $currentMatchday = $leagueRoom->matchdays->where('status', 'pending')->first()
            ?? $leagueRoom->matchdays->last();

        return view('league-room.dashboard', [
            'room' => $leagueRoom,
            'members' => $leagueRoom->members,
            'myMember' => $member,
            'currentMatchday' => $currentMatchday,
        ]);
    }

    public function ready(LeagueRoom $leagueRoom)
    {
        $member = LeagueRoomMember::where('league_room_id', $leagueRoom->id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $member->update(['is_ready' => true]);

        if ($leagueRoom->allMembersReady()) {
            $leagueRoom->members()->update(['is_ready' => false]);

            $currentMatchday = $leagueRoom->matchdays()->where('status', 'pending')->first();
            if ($currentMatchday) {
                $currentMatchday->update(['status' => 'simulated']);

                LeagueRoomMatchday::create([
                    'league_room_id' => $leagueRoom->id,
                    'matchday_number' => $currentMatchday->matchday_number + 1,
                    'status' => 'pending',
                    'auto_advance_at' => now()->addHours($leagueRoom->auto_advance_hours),
                ]);
            }
        }

        return back()->with('success', '\u00a1Listo! Esperando al resto de jugadores...');
    }
}

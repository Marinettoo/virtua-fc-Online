<?php

namespace App\Http\Controllers;

use App\Models\LeagueRoom;
use App\Models\LeagueRoomMember;
use App\Models\LeagueRoomMatchday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeagueRoomController extends Controller
{
    // Mostrar pantalla de crear sala
    public function create()
    {
        return view('league-room.create');
    }

    // Guardar nueva sala
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

        // El creador entra automáticamente como miembro
        LeagueRoomMember::create([
            'league_room_id' => $room->id,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('league-room.lobby', $room->id)
            ->with('success', '¡Sala creada! Comparte el código ' . $room->code . ' con tus amigos.');
    }

    // Pantalla de unirse con código
    public function join()
    {
        return view('league-room.join');
    }

    // Procesar unirse a sala
    public function joinStore(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:8',
        ]);

        $room = LeagueRoom::where('code', strtoupper($request->code))
            ->where('status', 'waiting')
            ->firstOrFail();

        // Comprobar que no está ya dentro
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
            ->with('success', '¡Te has unido a ' . $room->name . '!');
    }

    // Lobby de la sala (elegir equipo y esperar)
    public function lobby(LeagueRoom $leagueRoom)
    {
        $leagueRoom->load('members.user');
        $member = $leagueRoom->members->firstWhere('user_id', Auth::id());

        abort_if(!$member, 403, 'No eres miembro de esta sala.');

        return view('league-room.lobby', [
            'room' => $leagueRoom,
            'members' => $leagueRoom->members,
            'myMember' => $member,
        ]);
    }

    // Elegir equipo dentro del lobby
    public function chooseTeam(Request $request, LeagueRoom $leagueRoom)
    {
        $request->validate([
            'team_id' => 'required|integer',
        ]);

        $member = LeagueRoomMember::where('league_room_id', $leagueRoom->id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Comprobar que el equipo no está cogido por otro
        $teamTaken = LeagueRoomMember::where('league_room_id', $leagueRoom->id)
            ->where('team_id', $request->team_id)
            ->where('user_id', '!=', Auth::id())
            ->exists();

        if ($teamTaken) {
            return back()->with('error', 'Ese equipo ya está cogido por otro jugador.');
        }

        $member->update(['team_id' => $request->team_id]);

        return back()->with('success', '¡Equipo seleccionado!');
    }

    // El creador arranca la liga
    public function start(LeagueRoom $leagueRoom)
    {
        abort_if($leagueRoom->owner_id !== Auth::id(), 403);
        abort_if($leagueRoom->members()->whereNull('team_id')->exists(), 422, 'Todos los jugadores deben elegir equipo antes de empezar.');

        $leagueRoom->update(['status' => 'active']);

        // Crear la primera jornada
        LeagueRoomMatchday::create([
            'league_room_id' => $leagueRoom->id,
            'matchday_number' => 1,
            'status' => 'pending',
            'auto_advance_at' => now()->addHours($leagueRoom->auto_advance_hours),
        ]);

        return redirect()->route('league-room.dashboard', $leagueRoom->id)
            ->with('success', '¡Liga iniciada!');
    }

    // Dashboard de la liga activa
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

    // Pulsar "Jugar Jornada"
    public function ready(LeagueRoom $leagueRoom)
    {
        $member = LeagueRoomMember::where('league_room_id', $leagueRoom->id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $member->update(['is_ready' => true]);

        // Si todos están listos, simular jornada
        if ($leagueRoom->allMembersReady()) {
            // TODO Fase 5: disparar simulación de partidos compartidos
            $leagueRoom->members()->update(['is_ready' => false]);

            $currentMatchday = $leagueRoom->matchdays()->where('status', 'pending')->first();
            if ($currentMatchday) {
                $currentMatchday->update(['status' => 'simulated']);

                // Crear siguiente jornada
                LeagueRoomMatchday::create([
                    'league_room_id' => $leagueRoom->id,
                    'matchday_number' => $currentMatchday->matchday_number + 1,
                    'status' => 'pending',
                    'auto_advance_at' => now()->addHours($leagueRoom->auto_advance_hours),
                ]);
            }
        }

        return back()->with('success', '¡Listo! Esperando al resto de jugadores...');
    }
}

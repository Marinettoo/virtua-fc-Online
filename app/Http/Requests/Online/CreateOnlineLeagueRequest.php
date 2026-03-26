<?php

namespace App\Http\Requests\Online;

use Illuminate\Foundation\Http\FormRequest;

class CreateOnlineLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // middleware 'auth' ya lo gestiona
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'min:3', 'max:60'],
            'competition_id' => ['required', 'string', 'exists:competitions,id'],
            'team_id'        => ['required', 'uuid', 'exists:teams,id'],
            'game_id'        => [
                'required',
                'uuid',
                // La partida debe ser del usuario autenticado y del equipo elegido
                function ($attr, $value, $fail) {
                    $game = \App\Models\Game::where('id', $value)
                        ->where('user_id', $this->user()->id)
                        ->where('team_id', $this->team_id)
                        ->first();
                    if (! $game) {
                        $fail('La partida no pertenece a tu cuenta o no es del equipo seleccionado.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'El nombre de la liga es obligatorio.',
            'competition_id.required' => 'Debes elegir una competición.',
            'competition_id.exists'   => 'La competición no existe.',
            'team_id.required'        => 'Debes elegir un equipo.',
            'game_id.required'        => 'Debes vincular una partida activa.',
        ];
    }
}

<?php

namespace App\Http\Requests\Online;

use Illuminate\Foundation\Http\FormRequest;

class JoinOnlineLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invite_code' => ['required', 'string', 'size:8'],
            'team_id'     => ['required', 'uuid', 'exists:teams,id'],
            'game_id'     => [
                'required',
                'uuid',
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
            'invite_code.required' => 'El código de invitación es obligatorio.',
            'invite_code.size'     => 'El código debe tener exactamente 8 caracteres.',
            'team_id.required'     => 'Debes elegir un equipo.',
            'game_id.required'     => 'Debes vincular una partida activa.',
        ];
    }
}

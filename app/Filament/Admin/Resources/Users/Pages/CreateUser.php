<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Les comptes de l'équipe sont créés ici uniquement (pas d'inscription publique) :
     * adresse considérée comme vérifiée, accès illimité.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $model = static::getModel();

        $user = new $model;
        $user->forceFill([
            ...$data,
            'plan' => 'studio',
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}

<?php

namespace App\Toolbox;

/**
 * Correctif ajout d'un Erreur Inconnu car cas si dessous, on avais du coup une erreur 500
 * cas :
 * [RETOUR] {"statusCode":200,"devices":[{"imei":"350841430249907","statut":"0","message":""}],"statut":"0"}
 */

enum StatutEnrolementEnum: int {
    case DemandeAnnulation = 7;
    case DemandeRetour = 8;
    case DemandeEnrolement = 10;
    case DemandeControleOrdre = 9;
    case CheckStatut = 1;
    case Ok = 2;
    case OkPartiel = 3;
    case Acquitte = 4;
    case ErreurApplicative = 5;
    case ErreurInconnu = 0;

    public function label(): string
    {
        return match($this) {
            static::DemandeAnnulation => "Demande",
            static::DemandeRetour => "Demande",
            static::DemandeEnrolement => "Demande",
            static::DemandeControleOrdre => "Controle terminaux",
            static::CheckStatut => "En cours",
            static::Ok => "Succès",
            static::OkPartiel => "Succès partiel",
            static::Acquitte => "Acquitté",
            static::ErreurApplicative => "Erreur",
            static::ErreurInconnu => "Statut non répertorié",
        };
    }
}




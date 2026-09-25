<?php

namespace App\Toolbox;

enum StatutRequeteEnum: string {
    case Succes = "0" ;
    case Erreur = "1" ;
    case ErreurARejouer = "2";
    case SuccesPartiel = "3";
    case Exception = "4";


    public function label(): string
    {
        return match($this) {
            static::Succes => "Succès",
            static::Erreur => "Erreur",
            static::ErreurARejouer => "Erreur à rejouer",
            static::SuccesPartiel => "Succès partiel",
            static::Exception => "Exception",
        };
    }
}


<?php

namespace App\Toolbox;

enum StatutImeiEnum : int {
    case EnCoursEnrolement = 0;
    case Enrole = 1;
    case Libre = 2;
    case EnCoursDesenrolement = 3;
    case Bloque = 4;

    public function label(): string
    {
        return match($this) {
            static::EnCoursEnrolement => "En cours d'inscription",
            static::Enrole => "Inscrit",
            static::Libre => "Désinscrit",
            static::EnCoursDesenrolement => "En cours de désinscription",
            static::Bloque => "Bloqué",
        };
    }
}
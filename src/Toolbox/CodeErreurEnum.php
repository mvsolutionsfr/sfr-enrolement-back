<?php

namespace App\Toolbox;

enum CodeErreurEnum: int {
    case ok = 0; // Ok
    case nok = 1; // Erreur applicative
    case e2 = 2; // erreur d'authentificiation;
    case e3 = 3; // utilisateur inconnu de l'application d'enrôlement
    case ex = 4; // exceptions
    case unknown = 5; // erreur inconnu
    case session = 6; // exceptions
    case e4 = 7; // utilisateur inconnu de l'application d'enrôlement

    public function label(): string
    {
        return match($this) {
            static::ok => "Ok",
            static::nok => "Ko",
            static::e2 => "Utilisateur inconnu ou mot de passe incorrecte",
            static::e3 => "Utilisateur attaché à aucune enseigne. Veuillez ouvrir un ticket",
            static::e4 => "Compte utilisateur bloqué",
            static::ex => 'Exception',
            static::session => 'Session incorrecte',
            static::unknown => "Erreur inconnue",
        };
    }
}


<?php

namespace App\Toolbox;

class RetourCheckTransaction
{
    public string $statut = ""; ///statut global de la requete (valeur de l'énumération StatutRequeteEnum)
    public string $code = "" ; //code d'exception eventuel
    public string $statusCode="" ; // code de retour de la requete d'appel
    public string $message = ""; //message d'erreur éventuel
}
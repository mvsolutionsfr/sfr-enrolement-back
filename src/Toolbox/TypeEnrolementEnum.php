<?php

namespace App\Toolbox;

enum TypeEnrolementEnum: string {
    case Inscription = "OR" ;
    case Desinscription = "RE" ;
    case Annulation = "VD" ;
    case Remplacement = "OV" ;
    case Synchro = "SOD";

}


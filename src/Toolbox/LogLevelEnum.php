<?php

namespace App\Toolbox;

enum LogLevelEnum : int
{
    // * 1 - CRITIC / 2 - ERROR / 3 - WARNING / 4 - INFO / 5 - DEBUG
    case Critique = 1;
    case Erreur = 2;
    case Warning = 3;
    case Info = 4;
    case Debug = 5;
}

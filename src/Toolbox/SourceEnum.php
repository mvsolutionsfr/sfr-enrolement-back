<?php

namespace App\Toolbox;

enum SourceEnum : int
{
    // (Front / Back controller / Back service / Back datalayer / API Apple / API Google / API Samsung - obligatoire)
    case Front = 0;
    case BackController = 1;
    case BackService = 2;
    case BackDatalayer = 3;
    case ApiApple = 4;
    case ApiSamsung = 5;
    case ApiGoogle = 6;
    case ClientJson = 7;
}

<?php

namespace App\TimedLight;

enum TimedLightCommand: string
{
    case TurnOn = 'turn_on';
    case TurnOff = 'turn_off';
    case ForcedTurnOn = 'force_turn_on';
}
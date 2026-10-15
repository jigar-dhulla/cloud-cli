<?php

namespace App\Enums;

enum EnvironmentColor: string
{
    case BLUE = 'blue';
    case GREEN = 'green';
    case ORANGE = 'orange';
    case PURPLE = 'purple';
    case RED = 'red';
    case YELLOW = 'yellow';
    case CYAN = 'cyan';
    case GRAY = 'gray';

    public function dot(): string
    {
        return "\e[{$this->ansiCode()}m●\e[39m";
    }

    protected function ansiCode(): string
    {
        return match ($this) {
            self::BLUE => '34',
            self::GREEN => '32',
            // Basic ANSI has no orange, and falling back to yellow would collide with YELLOW
            self::ORANGE => '38;5;208',
            self::PURPLE => '35',
            self::RED => '31',
            self::YELLOW => '33',
            self::CYAN => '36',
            self::GRAY => '90',
        };
    }
}

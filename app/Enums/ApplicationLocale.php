<?php

namespace App\Enums;

enum ApplicationLocale: string
{
    case English = 'en';
    case BrazilianPortuguese = 'pt_BR';
    case Spanish = 'es';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::BrazilianPortuguese => 'Português (Brasil)',
            self::Spanish => 'Español',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $locale) {
            $options[$locale->value] = $locale->label();
        }

        return $options;
    }
}

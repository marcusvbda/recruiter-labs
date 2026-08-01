<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum PhoneCountry: string
{
    case Brazil = 'BR';
    case Ireland = 'IE';
    case Portugal = 'PT';
    case Spain = 'ES';
    case UnitedStates = 'US';
    case Canada = 'CA';
    case UnitedKingdom = 'GB';
    case France = 'FR';
    case Germany = 'DE';
    case Italy = 'IT';
    case Argentina = 'AR';
    case Mexico = 'MX';
    case Chile = 'CL';
    case Colombia = 'CO';
    case Uruguay = 'UY';
    case Paraguay = 'PY';
    case Peru = 'PE';
    case Ecuador = 'EC';
    case Bolivia = 'BO';
    case Venezuela = 'VE';
    case Australia = 'AU';
    case NewZealand = 'NZ';
    case Japan = 'JP';
    case India = 'IN';
    case SouthAfrica = 'ZA';
    case Netherlands = 'NL';
    case Belgium = 'BE';
    case Switzerland = 'CH';
    case Austria = 'AT';
    case Sweden = 'SE';
    case Norway = 'NO';
    case Denmark = 'DK';
    case Finland = 'FI';
    case Poland = 'PL';
    case Romania = 'RO';
    case Greece = 'GR';
    case Turkey = 'TR';
    case China = 'CN';
    case Singapore = 'SG';
    case UnitedArabEmirates = 'AE';
    case Israel = 'IL';

    public function callingCode(): string
    {
        return match ($this) {
            self::Brazil => '+55',
            self::Ireland => '+353',
            self::Portugal => '+351',
            self::Spain => '+34',
            self::UnitedStates, self::Canada => '+1',
            self::UnitedKingdom => '+44',
            self::France => '+33',
            self::Germany => '+49',
            self::Italy => '+39',
            self::Argentina => '+54',
            self::Mexico => '+52',
            self::Chile => '+56',
            self::Colombia => '+57',
            self::Uruguay => '+598',
            self::Paraguay => '+595',
            self::Peru => '+51',
            self::Ecuador => '+593',
            self::Bolivia => '+591',
            self::Venezuela => '+58',
            self::Australia => '+61',
            self::NewZealand => '+64',
            self::Japan => '+81',
            self::India => '+91',
            self::SouthAfrica => '+27',
            self::Netherlands => '+31',
            self::Belgium => '+32',
            self::Switzerland => '+41',
            self::Austria => '+43',
            self::Sweden => '+46',
            self::Norway => '+47',
            self::Denmark => '+45',
            self::Finland => '+358',
            self::Poland => '+48',
            self::Romania => '+40',
            self::Greece => '+30',
            self::Turkey => '+90',
            self::China => '+86',
            self::Singapore => '+65',
            self::UnitedArabEmirates => '+971',
            self::Israel => '+972',
        };
    }

    public function mask(): string
    {
        return match ($this) {
            self::Brazil => '(99) 99999-9999',
            self::Ireland => '99 999 9999',
            self::Portugal, self::Spain, self::Australia, self::Peru, self::Poland, self::Romania => '999 999 999',
            self::UnitedStates, self::Canada => '(999) 999-9999',
            self::UnitedKingdom => '9999 999999',
            self::France => '9 99 99 99 99',
            self::Germany, self::Austria => '9999 9999999',
            self::Italy, self::Mexico, self::Colombia => '999 999 9999',
            self::Argentina => '99 9999-9999',
            self::Chile => '9 9999 9999',
            self::Uruguay, self::Paraguay => '99 999 999',
            self::Ecuador, self::SouthAfrica, self::Netherlands, self::Israel, self::UnitedArabEmirates => '99 999 9999',
            self::Bolivia, self::Singapore => '9999 9999',
            self::Venezuela => '999 9999999',
            self::NewZealand, self::Finland => '99 999 9999',
            self::Japan => '99 9999 9999',
            self::India => '99999 99999',
            self::Belgium => '999 99 99 99',
            self::Switzerland, self::Sweden => '99 999 99 99',
            self::Norway => '999 99 999',
            self::Denmark => '99 99 99 99',
            self::Greece, self::Turkey => '999 999 9999',
            self::China => '999 9999 9999',
        };
    }

    public function placeholder(): string
    {
        return Str::replace('9', '0', $this->mask());
    }

    public function optionLabel(): string
    {
        return "{$this->flag()} {$this->countryName()} ({$this->callingCode()})";
    }

    public function toInternational(?string $nationalNumber): ?string
    {
        $digits = self::digits($nationalNumber);

        return $digits === '' ? null : $this->callingCode().$digits;
    }

    public static function formatInternational(?string $phone): ?string
    {
        $phoneParts = self::split($phone);
        $nationalNumber = $phoneParts['national_number'];

        if ($nationalNumber === null) {
            return null;
        }

        return $phoneParts['country']->callingCode().' '.$phoneParts['country']->formatNational($nationalNumber);
    }

    public function formatNational(?string $nationalNumber): ?string
    {
        $digits = self::digits($nationalNumber);

        if ($digits === '') {
            return null;
        }

        $mask = $this === self::Brazil && Str::length($digits) === 10
            ? '(99) 9999-9999'
            : $this->mask();

        if (Str::substrCount($mask, '9') !== Str::length($digits)) {
            return $digits;
        }

        $digitPosition = 0;

        return Str::replaceMatches(
            '/9/',
            function () use ($digits, &$digitPosition): string {
                $digit = Str::substr($digits, $digitPosition, 1);
                $digitPosition++;

                return $digit;
            },
            $mask,
        );
    }

    /**
     * @return array{country: self, national_number: ?string}
     */
    public static function split(?string $phone): array
    {
        $digits = self::digits($phone);

        if ($digits === '') {
            return ['country' => self::Brazil, 'national_number' => null];
        }

        if (! Str::startsWith((string) $phone, '+')) {
            return ['country' => self::Brazil, 'national_number' => $digits];
        }

        $countries = self::cases();
        usort(
            $countries,
            fn (self $first, self $second): int => strlen($second->callingCode()) <=> strlen($first->callingCode()),
        );

        foreach ($countries as $country) {
            $callingCode = self::digits($country->callingCode());

            if (Str::startsWith($digits, $callingCode)) {
                return [
                    'country' => $country,
                    'national_number' => Str::after($digits, $callingCode),
                ];
            }
        }

        return ['country' => self::Brazil, 'national_number' => $digits];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $country) {
            $options[$country->value] = $country->optionLabel();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function masks(): array
    {
        $masks = [];

        foreach (self::cases() as $country) {
            $masks[$country->value] = $country->mask();
        }

        return $masks;
    }

    private function countryName(): string
    {
        return match ($this) {
            self::Brazil => 'Brazil',
            self::Ireland => 'Ireland',
            self::Portugal => 'Portugal',
            self::Spain => 'Spain',
            self::UnitedStates => 'United States',
            self::Canada => 'Canada',
            self::UnitedKingdom => 'United Kingdom',
            self::France => 'France',
            self::Germany => 'Germany',
            self::Italy => 'Italy',
            self::Argentina => 'Argentina',
            self::Mexico => 'Mexico',
            self::Chile => 'Chile',
            self::Colombia => 'Colombia',
            self::Uruguay => 'Uruguay',
            self::Paraguay => 'Paraguay',
            self::Peru => 'Peru',
            self::Ecuador => 'Ecuador',
            self::Bolivia => 'Bolivia',
            self::Venezuela => 'Venezuela',
            self::Australia => 'Australia',
            self::NewZealand => 'New Zealand',
            self::Japan => 'Japan',
            self::India => 'India',
            self::SouthAfrica => 'South Africa',
            self::Netherlands => 'Netherlands',
            self::Belgium => 'Belgium',
            self::Switzerland => 'Switzerland',
            self::Austria => 'Austria',
            self::Sweden => 'Sweden',
            self::Norway => 'Norway',
            self::Denmark => 'Denmark',
            self::Finland => 'Finland',
            self::Poland => 'Poland',
            self::Romania => 'Romania',
            self::Greece => 'Greece',
            self::Turkey => 'Turkey',
            self::China => 'China',
            self::Singapore => 'Singapore',
            self::UnitedArabEmirates => 'United Arab Emirates',
            self::Israel => 'Israel',
        };
    }

    private function flag(): string
    {
        return match ($this) {
            self::Brazil => '🇧🇷',
            self::Ireland => '🇮🇪',
            self::Portugal => '🇵🇹',
            self::Spain => '🇪🇸',
            self::UnitedStates => '🇺🇸',
            self::Canada => '🇨🇦',
            self::UnitedKingdom => '🇬🇧',
            self::France => '🇫🇷',
            self::Germany => '🇩🇪',
            self::Italy => '🇮🇹',
            self::Argentina => '🇦🇷',
            self::Mexico => '🇲🇽',
            self::Chile => '🇨🇱',
            self::Colombia => '🇨🇴',
            self::Uruguay => '🇺🇾',
            self::Paraguay => '🇵🇾',
            self::Peru => '🇵🇪',
            self::Ecuador => '🇪🇨',
            self::Bolivia => '🇧🇴',
            self::Venezuela => '🇻🇪',
            self::Australia => '🇦🇺',
            self::NewZealand => '🇳🇿',
            self::Japan => '🇯🇵',
            self::India => '🇮🇳',
            self::SouthAfrica => '🇿🇦',
            self::Netherlands => '🇳🇱',
            self::Belgium => '🇧🇪',
            self::Switzerland => '🇨🇭',
            self::Austria => '🇦🇹',
            self::Sweden => '🇸🇪',
            self::Norway => '🇳🇴',
            self::Denmark => '🇩🇰',
            self::Finland => '🇫🇮',
            self::Poland => '🇵🇱',
            self::Romania => '🇷🇴',
            self::Greece => '🇬🇷',
            self::Turkey => '🇹🇷',
            self::China => '🇨🇳',
            self::Singapore => '🇸🇬',
            self::UnitedArabEmirates => '🇦🇪',
            self::Israel => '🇮🇱',
        };
    }

    private static function digits(?string $value): string
    {
        return (string) Str::of($value ?? '')->replaceMatches('/\D+/', '');
    }
}

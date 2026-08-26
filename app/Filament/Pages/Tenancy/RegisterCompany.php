<?php

namespace App\Filament\Pages\Tenancy;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Plan;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RegisterCompany extends RegisterTenant
{
    public static function getLabel(): string
    {
        return __('company.register_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('company.fields.name'))
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $state, Set $set, Get $get) {
                        if (blank($get('slug'))) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label(__('company.fields.slug'))
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', trim((string) $state)))
                    ->regex('/^[a-z0-9]+(-[a-z0-9]+)*$/')
                    ->helperText(__('company.fields.slug_helper'))
                    ->unique(Company::class, 'slug'),
            ]);
    }

    protected function handleRegistration(array $data): Company
    {
        $company = Company::create([
            ...$data,
            'plan_id' => Plan::default()->id,
        ]);

        $company->users()->attach(auth()->user(), ['role' => CompanyRole::Owner->value]);

        return $company;
    }
}

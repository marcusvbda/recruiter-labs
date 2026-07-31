<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Company;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditCompanyProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return __('company.edit_label');
    }

    public static function canView(Model $tenant): bool
    {
        return auth()->user()?->companies()->whereKey($tenant)->exists() ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('company.fields.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label(__('company.fields.slug'))
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', trim((string) $state)))
                            ->regex('/^[a-z0-9]+(-[a-z0-9]+)*$/')
                            ->helperText(__('company.fields.slug_helper'))
                            ->unique(Company::class, 'slug', ignoreRecord: true),
                    ]),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            DeleteAction::make()
                ->record($this->tenant)
                ->requiresConfirmation()
                ->after(fn () => $this->redirect(route('filament.admin.tenant'))),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        // The slug can change on save, which changes the tenant's URL segment.
        // Without this, the page keeps referencing the pre-save URL, and any
        // follow-up Livewire request 404s because that slug no longer exists.
        return route('filament.admin.tenant.profile', ['tenant' => $this->tenant]);
    }
}

<?php

namespace App\Filament\Clusters\Recruitment\Pages;

use App\Enums\EmailNotificationType;
use App\Filament\Clusters\Recruitment\RecruitmentCluster;
use App\Models\Company;
use App\Models\CompanyEmailNotificationSetting;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * @property-read Schema $form
 */
class EmailNotificationSettings extends Page
{
    protected static ?string $cluster = RecruitmentCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.clusters.recruitment.pages.email-notification-settings';

    /** @var array<string, bool> */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return 'Email Notifications';
    }

    public function getTitle(): string
    {
        return 'Email Notifications';
    }

    public function getSubheading(): string
    {
        return 'Choose which recruitment emails are sent automatically to candidates.';
    }

    public function mount(): void
    {
        $company = $this->getCompany();
        Gate::forUser($this->getUser())->authorize('update', $company);

        $disabledNotificationTypes = CompanyEmailNotificationSetting::query()
            ->whereBelongsTo($company)
            ->where('enabled', false)
            ->pluck('notification_type')
            ->map(fn (EmailNotificationType $type): string => $type->value)
            ->all();

        $this->form->fill(collect(EmailNotificationType::cases())
            ->mapWithKeys(fn (EmailNotificationType $type): array => [
                $type->value => ! in_array($type->value, $disabledNotificationTypes, strict: true),
            ])
            ->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Candidate email notifications')
                    ->description('Notifications are enabled by default. Turn off any email that should not be sent for this company.')
                    ->schema(collect(EmailNotificationType::cases())
                        ->map(fn (EmailNotificationType $type): Toggle => Toggle::make($type->value)
                            ->label($type->label())
                            ->helperText($type->description())
                            ->default(true)
                            ->inline())
                        ->all()),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $company = $this->getCompany();
        Gate::forUser($this->getUser())->authorize('update', $company);

        $state = $this->form->getState();

        DB::transaction(function () use ($company, $state): void {
            foreach (EmailNotificationType::cases() as $type) {
                if ($state[$type->value] ?? true) {
                    CompanyEmailNotificationSetting::query()
                        ->whereBelongsTo($company)
                        ->where('notification_type', $type)
                        ->delete();

                    continue;
                }

                CompanyEmailNotificationSetting::query()->updateOrCreate(
                    [
                        'company_id' => $company->getKey(),
                        'notification_type' => $type,
                    ],
                    ['enabled' => false],
                );
            }
        });

        Notification::make()
            ->title('Email notification settings updated')
            ->success()
            ->send();
    }

    private function getCompany(): Company
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        return $company;
    }

    private function getUser(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}

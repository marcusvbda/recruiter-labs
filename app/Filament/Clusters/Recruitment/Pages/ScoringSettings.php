<?php

namespace App\Filament\Clusters\Recruitment\Pages;

use App\Actions\UpdateCompanyScoringSettings;
use App\Filament\Clusters\Recruitment\RecruitmentCluster;
use App\Models\Company;
use App\Models\CompanyScoringSetting;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ScoringSettings extends Page
{
    protected static ?string $cluster = RecruitmentCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.clusters.recruitment.pages.scoring-settings';

    /** @var array<string, mixed> */
    public array $scoringSettings = [];

    public static function getNavigationLabel(): string
    {
        return __('scoring.navigation_label');
    }

    public function getTitle(): string
    {
        return __('scoring.title');
    }

    public function getSubheading(): string
    {
        return __('scoring.subtitle');
    }

    public function mount(): void
    {
        $this->refreshScoringState();
    }

    public function updateScoringWeightsAction(): Action
    {
        return Action::make('updateScoringWeights')
            ->modal()
            ->modalHeading(__('scoring.update.heading'))
            ->modalDescription(__('scoring.update.description'))
            ->modalIcon('heroicon-o-scale')
            ->modalSubmitActionLabel(__('scoring.update.save'))
            ->fillForm(fn (): array => [
                'analysis_weight' => $this->scoringSettings['analysis_weight'],
                'referral_weight' => $this->scoringSettings['referral_weight'],
            ])
            ->schema([
                TextInput::make('analysis_weight')
                    ->label(__('scoring.fields.fit_evaluation_weight'))
                    ->helperText(__('scoring.update.sum_helper'))
                    ->numeric()
                    ->integer()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%'),
                TextInput::make('referral_weight')
                    ->label(__('scoring.fields.referral_weight'))
                    ->numeric()
                    ->integer()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%'),
            ])
            ->action(function (array $data, UpdateCompanyScoringSettings $updateScoringSettings): void {
                if (((int) $data['analysis_weight'] + (int) $data['referral_weight']) !== 100) {
                    throw ValidationException::withMessages([
                        'analysis_weight' => __('scoring.validation.weights_must_sum'),
                        'referral_weight' => __('scoring.validation.weights_must_sum'),
                    ]);
                }

                try {
                    $updateScoringSettings->run(
                        $this->getCompany(),
                        $this->getRecord(),
                        (int) $data['analysis_weight'],
                        (int) $data['referral_weight'],
                    );
                } catch (InvalidArgumentException) {
                    throw ValidationException::withMessages([
                        'analysis_weight' => __('scoring.validation.weights_must_sum'),
                        'referral_weight' => __('scoring.validation.weights_must_sum'),
                    ]);
                }

                $this->refreshScoringState();

                Notification::make()
                    ->title(__('scoring.notifications.updated'))
                    ->success()
                    ->send();
            });
    }

    public function getRecord(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function getCompany(): Company
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        return $company;
    }

    private function refreshScoringState(): void
    {
        $company = $this->getCompany();
        $company->refresh()->load('scoringSetting');

        $scoringSetting = $company->scoringSetting ?? new CompanyScoringSetting;

        $this->scoringSettings = [
            'analysis_weight' => $scoringSetting->analysis_weight,
            'referral_weight' => $scoringSetting->referral_weight,
        ];
    }
}

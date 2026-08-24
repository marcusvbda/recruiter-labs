<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Enums\InterviewStatus;
use App\Enums\PhoneCountry;
use App\Enums\SocialNetwork;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Interview;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use LogicException;

/**
 * A person, across every process they take part in. It deliberately does not
 * repeat the application workspace: each row links out to it.
 */
class ViewCandidate extends ViewRecord
{
    protected static string $resource = CandidateResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getCandidate()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $candidate = $this->getCandidate();

        return $schema->components([
            View::make('filament.resources.candidates.components.profile')
                ->viewData(['profile' => $this->profileData($candidate)])
                ->columnSpanFull(),
            View::make('filament.resources.candidates.components.applications')
                ->viewData(['applications' => $this->applicationsData($candidate)])
                ->columnSpanFull(),
        ]);
    }

    /** @return array<string, mixed> */
    private function profileData(Candidate $candidate): array
    {
        return [
            'name' => $candidate->name,
            'email' => $candidate->email,
            'phone' => PhoneCountry::formatInternational($candidate->phone),
            'created_at' => $candidate->created_at?->translatedFormat('M j, Y'),
            'socials' => $this->socialProfiles($candidate),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function applicationsData(Candidate $candidate): array
    {
        $candidate->loadMissing([
            'applications.job',
            'applications.status',
            'applications.interviews',
        ]);

        return $candidate->applications
            ->sortByDesc('created_at')
            ->map(function (Application $application): array {
                $nextInterview = $application->interviews
                    ->filter(fn (Interview $interview): bool => $interview->status !== InterviewStatus::Cancelled
                        && $interview->ends_at->isFuture())
                    ->sortBy('scheduled_at')
                    ->first();

                $daysInStage = $application->daysInCurrentStage();

                return [
                    'job' => $application->job->name,
                    'job_url' => JobResource::getUrl('view', ['record' => $application->job]),
                    'status' => $application->status->name,
                    'status_color' => $application->status->color,
                    'stage_role' => match (true) {
                        $application->status->is_hired => 'hired',
                        $application->status->is_terminal => 'closed',
                        $application->status->is_final_stage => 'final_stage',
                        default => null,
                    },
                    // Only a fit that still measures the job's confirmed criteria
                    // is shown; an evaluation the criteria have moved past is not
                    // this candidate's current match for that process.
                    'score' => $application->hasCurrentEvaluation() && $application->analysis_score !== null
                        ? (int) round((float) $application->analysis_score)
                        : null,
                    'applied_at' => $application->created_at->translatedFormat('M j, Y'),
                    // Where they are is only half the answer; how long they have
                    // been there is what tells the recruiter whether this process
                    // is moving. Terminal outcomes are not "waiting".
                    'stage_age' => $application->status->is_terminal
                        ? null
                        : trans_choice('attention.days', $daysInStage, ['count' => $daysInStage]),
                    'is_overdue' => $application->isOverdueInCurrentStage(),
                    'next_interview' => $nextInterview?->scheduled_at
                        ->setTimezone($nextInterview->timezone)
                        ->translatedFormat('M j, Y · H:i'),
                    'url' => ApplicationResource::getUrl('view', ['record' => $application]),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array{label: string, account: string, url: string|null}> */
    private function socialProfiles(Candidate $candidate): array
    {
        $socials = $candidate->getAttribute('socials');

        if (! is_array($socials)) {
            return [];
        }

        $profiles = [];

        foreach ($socials as $social) {
            if (! is_array($social)) {
                continue;
            }

            $network = is_string($social['network'] ?? null) ? $social['network'] : 'other';
            $account = is_string($social['account'] ?? null) ? $social['account'] : '';

            if ($account === '') {
                continue;
            }

            $profiles[] = [
                'label' => SocialNetwork::tryFrom($network)?->label() ?? Str::headline($network),
                'account' => $account,
                'url' => filter_var($account, FILTER_VALIDATE_URL) ? $account : null,
            ];
        }

        return $profiles;
    }

    private function getCandidate(): Candidate
    {
        $record = $this->getRecord();

        if (! $record instanceof Candidate) {
            throw new LogicException('The candidate view page must be bound to a candidate.');
        }

        return $record;
    }
}

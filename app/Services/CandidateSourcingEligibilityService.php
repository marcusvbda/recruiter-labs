<?php

namespace App\Services;

use App\Ai\Concerns\BuildsCompactAgentContext;
use App\Data\CandidateRecruitmentHistoryEntry;
use App\Data\CandidateSourcingMaterial;
use App\Enums\ApplicationCoverLetterType;
use App\Enums\ApplicationDocumentType;
use App\Enums\ApplicationQuestionType;
use App\Enums\CandidateMaterialPreparationStatus;
use App\Enums\CriterionEvidenceSource;
use App\Models\Application;
use App\Models\ApplicationAnswer;
use App\Models\Candidate;
use App\Models\Job;
use App\Models\Status;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Answers the two questions internal sourcing has to settle before any model is
 * called: *which* workspace candidates may be considered for this job, and *what*
 * the workspace actually knows about each of them.
 *
 * All of it is deterministic Laravel code. Nothing here scores a candidate, and
 * the "is there enough to assess?" decision is an explicit, readable threshold
 * rather than a judgement delegated to a model — a candidate the workspace knows
 * almost nothing about must be reported as insufficiently known, never scored
 * low from the absence of evidence.
 *
 * Three separations are load-bearing:
 *
 * - Candidates are always resolved inside one company. Neither {@see Candidate}
 *   nor {@see Job} carries a tenant global scope, so every query here filters
 *   `company_id` by hand.
 * - Aggregated material is candidate-supplied material only — resume, cover
 *   letter, application answers and independently retained candidate CVs —
 *   because {@see CandidateSourcingMaterial} can represent nothing else.
 *   Structured interview feedback, recruiter notes, import metadata and previous
 *   per-application AI evaluations are not gathered here at all.
 * - Recruitment history is returned as {@see CandidateRecruitmentHistoryEntry},
 *   a shape the sanitizer and the sourcing agent cannot consume. It exists so a
 *   recruiter can read a suggestion in context; it is never agent input, so a
 *   previous workflow outcome cannot leak into a new assessment.
 */
class CandidateSourcingEligibilityService
{
    use BuildsCompactAgentContext;

    /**
     * A single item shorter than this is treated as incidental — "Yes", "N/A", a
     * one-word answer. It is still sent as material; it just cannot on its own
     * make a candidate worth an AI call.
     */
    public const MINIMUM_MEANINGFUL_ITEM_CHARACTERS = 20;

    /**
     * Total meaningful characters a candidate must have across their material.
     * Roughly a couple of substantive sentences: enough that a criterion could
     * plausibly be supported or contradicted, far more than a name, an email and
     * a phone number would ever produce.
     */
    public const MINIMUM_MEANINGFUL_TOTAL_CHARACTERS = 120;

    /** Candidates read per query when iterating a workspace lazily. */
    private const LAZY_CHUNK_SIZE = 250;

    public function __construct(private readonly ResumeTextExtractor $resumeTextExtractor) {}

    /**
     * Candidates in this job's workspace who are not already part of this job's
     * hiring process.
     *
     * Same shape as the manual "add candidate" picker in the job workspace:
     * company-scoped, minus anyone who already has an {@see Application} for this
     * exact job. Sourcing suggests people the recruiter has not placed here yet;
     * someone already in the pipeline is a pipeline question, not a sourcing one.
     *
     * @return Builder<Candidate>
     */
    public function eligibleCandidatesQuery(Job $job): Builder
    {
        return Candidate::query()
            ->where('company_id', $job->company_id)
            ->whereDoesntHave(
                'applications',
                fn (Builder $query): Builder => $query->where('job_id', $job->getKey()),
            );
    }

    /**
     * The same set, streamed. A workspace may hold thousands of candidates and a
     * search visits them one at a time, so the caller never materialises the
     * whole pool in memory.
     *
     * @return LazyCollection<int, Candidate>
     */
    public function eligibleCandidates(Job $job): LazyCollection
    {
        return $this->eligibleCandidatesQuery($job)->lazyById(self::LAZY_CHUNK_SIZE);
    }

    /** How many candidates a search would consider, without loading any of them. */
    public function eligibleCandidateCount(Job $job): int
    {
        return $this->eligibleCandidatesQuery($job)->count();
    }

    /**
     * Everything the candidate themselves supplied — the material they submitted
     * with past applications, plus the independent CVs the workspace retains for
     * them, newest application first.
     *
     * Extraction follows the per-application evaluation conventions exactly —
     * resume text through {@see ResumeTextExtractor}, rich text reduced with
     * `plainText()`, a numeric answer rendered as its number — so the sourcing
     * agent reads the same material the evaluation agent would have read, just
     * gathered across a history instead of a single application.
     *
     * Independent CVs *supplement* application history rather than replacing it,
     * and only when they are genuinely usable: available (neither archived nor
     * deleted) and carrying readable prepared text. A material with no readable
     * text contributes nothing here — it cannot support an assessment on its own,
     * and it must not be padded with its filename or source label to look as if
     * it could. This is what lets a candidate with zero applications and one
     * sufficient readable CV become assessable at all.
     *
     * Identical substantive content is kept once, whatever it was supplied
     * through: repeating a resume verbatim costs tokens, and — more importantly —
     * two copies of one document are not two independent pieces of support. The
     * surviving item absorbs the *earliest* provenance of the copies it replaced,
     * so removing duplication can never relabel an old CV as newly received
     * because a copy was imported today.
     *
     * Recruiter-written and operational metadata is not gathered at all: no
     * filename, no source label, no batch, no importer, no import order. None of
     * it is candidate evidence, and the type these items are returned in has
     * nowhere to put it.
     *
     * @return list<CandidateSourcingMaterial>
     */
    public function materialsFor(Candidate $candidate): array
    {
        $applications = $candidate->applications()
            ->where('company_id', $candidate->company_id)
            ->with(['documents', 'answers'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $gathered = [];

        foreach ($applications as $application) {
            $gathered = [...$gathered, ...$this->materialsForApplication($application)];
        }

        $gathered = [...$gathered, ...$this->independentMaterialsFor($candidate)];

        /** @var array<string, CandidateSourcingMaterial> $byContent */
        $byContent = [];

        foreach ($gathered as $material) {
            $fingerprint = $this->contentFingerprint($material);

            $byContent[$fingerprint] = isset($byContent[$fingerprint])
                ? $byContent[$fingerprint]->mergedWith($material)
                : $material;
        }

        return array_values($byContent);
    }

    /**
     * The candidate's independently retained CVs, newest holding first.
     *
     * Ordered by `added_at` for display consistency only. It is deliberately not
     * treated as an ordering by relevance or recency of the career it describes:
     * the product cannot tell which of several available CVs is the current one,
     * and must not infer it from upload order.
     *
     * @return list<CandidateSourcingMaterial>
     */
    private function independentMaterialsFor(Candidate $candidate): array
    {
        $materials = [];

        $records = $candidate->materials()
            ->where('company_id', $candidate->company_id)
            ->whereNull('archived_at')
            ->where('preparation_status', CandidateMaterialPreparationStatus::Ready)
            ->orderByDesc('added_at')
            ->orderByDesc('id')
            ->get();

        foreach ($records as $record) {
            // Asked of the model rather than reimplemented, so "usable" cannot
            // drift between the profile screen and what the agent is sent.
            if (! $record->hasReadableText()) {
                continue;
            }

            $text = $this->plainText($record->prepared_text);

            if ($text === null) {
                continue;
            }

            $receivedOn = $record->received_on;

            $materials[] = new CandidateSourcingMaterial(
                source: CriterionEvidenceSource::CandidateMaterial,
                text: $text,
                label: null,
                // The declared historical receipt when the recruiter knew it, and
                // nothing at all when they did not. The day the file was added is
                // never promoted into a submission date: the workspace holding a
                // copy since Tuesday says nothing about when the candidate wrote
                // or sent it.
                submittedAt: $receivedOn,
                materialId: (int) $record->getKey(),
                receivedOn: $receivedOn,
                addedAt: $record->added_at,
                receivedDateKnown: $receivedOn !== null,
            );
        }

        return $materials;
    }

    /**
     * What makes two items the same piece of support.
     *
     * The kind of document is deliberately *not* part of it. A CV supplied
     * independently and the same CV attached to an old application are one
     * document the workspace happens to hold twice, and counting them twice would
     * manufacture repeated support out of a duplicate file. An application answer
     * still keeps its question, so two different questions with the same short
     * answer remain two answers.
     */
    private function contentFingerprint(CandidateSourcingMaterial $material): string
    {
        $normalize = fn (string $value): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));

        return $normalize((string) $material->label).'|'.$normalize($material->text);
    }

    /**
     * Whether the workspace knows enough about this candidate to justify asking
     * a model about them.
     *
     * A workspace candidate may be little more than a name, an email address and
     * a phone number. Assessing that person would produce a match built out of
     * identity data, so it is not attempted: they are reported as insufficiently
     * known, which is a statement about the workspace's evidence and not
     * negative evidence about the candidate.
     *
     * The rule is deliberately blunt and inspectable — at least one item with
     * some substance to it, and enough total text overall. It is not a model, not
     * a classifier, and not tuned per workspace.
     *
     * Apply it to the *sanitized* material where possible: an answer that was
     * only the candidate's own name collapses to a placeholder once identifiers
     * are removed, and identity data must never be what makes someone look
     * knowable enough to score.
     *
     * @param  list<CandidateSourcingMaterial>  $materials
     */
    public function hasSufficientInformation(array $materials): bool
    {
        $meaningfulItems = 0;
        $meaningfulCharacters = 0;

        foreach ($materials as $material) {
            $length = mb_strlen(trim($material->text));

            if ($length < self::MINIMUM_MEANINGFUL_ITEM_CHARACTERS) {
                continue;
            }

            $meaningfulItems++;
            $meaningfulCharacters += $length;
        }

        return $meaningfulItems > 0
            && $meaningfulCharacters >= self::MINIMUM_MEANINGFUL_TOTAL_CHARACTERS;
    }

    /**
     * The candidate's previous hiring processes, newest first, as recruiter-facing
     * context beside a suggestion.
     *
     * Only the job, the application date and the workflow stage reached are
     * summarised, and how far that stage sits in the process is read from the
     * stage's own flags rather than pattern-matched from its name. Interview
     * feedback and previous AI evaluation output are not read here and have no
     * field in the returned type: a previous outcome is context for the human,
     * never an input that quietly raises or lowers a new suggestion.
     *
     * @return list<CandidateRecruitmentHistoryEntry>
     */
    public function recruitmentHistoryFor(Candidate $candidate, ?Job $currentJob = null): array
    {
        $applications = $candidate->applications()
            ->where('company_id', $candidate->company_id)
            ->with(['job', 'status'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $entries = [];

        foreach ($applications as $application) {
            $job = $application->job;
            $status = $application->status;

            if (! $job instanceof Job || ! $status instanceof Status) {
                continue;
            }

            $entries[] = new CandidateRecruitmentHistoryEntry(
                jobId: (int) $job->getKey(),
                jobName: (string) $job->name,
                appliedAt: $this->submittedAt($application),
                statusName: (string) $status->name,
                reachedFinalStage: (bool) $status->is_final_stage,
                wasHired: (bool) $status->is_hired,
                isClosed: (bool) $status->is_terminal,
                isCurrentJob: $currentJob !== null && (int) $job->getKey() === (int) $currentJob->getKey(),
            );
        }

        return $entries;
    }

    /**
     * @return list<CandidateSourcingMaterial>
     */
    private function materialsForApplication(Application $application): array
    {
        $submittedAt = $this->submittedAt($application);
        $materials = [];

        foreach ($application->documents as $document) {
            if ($document->type !== ApplicationDocumentType::Cv) {
                continue;
            }

            $resumeText = $this->resumeTextExtractor->extract($document);

            if ($resumeText === null || trim($resumeText) === '') {
                continue;
            }

            $materials[] = new CandidateSourcingMaterial(
                source: CriterionEvidenceSource::Resume,
                text: $resumeText,
                label: null,
                // The upload time is the more precise answer to "how old is this
                // resume" than the application's own submission moment.
                submittedAt: $document->uploaded_at,
            );
        }

        $coverLetter = $application->cover_letter_type === ApplicationCoverLetterType::Text
            ? $this->plainText($application->cover_letter_text)
            : null;

        if ($coverLetter !== null) {
            $materials[] = new CandidateSourcingMaterial(
                source: CriterionEvidenceSource::CoverLetter,
                text: $coverLetter,
                label: null,
                submittedAt: $submittedAt,
            );
        }

        foreach ($application->answers as $answer) {
            $value = $this->answerText($answer);

            if ($value === null) {
                continue;
            }

            $materials[] = new CandidateSourcingMaterial(
                source: CriterionEvidenceSource::ApplicationAnswer,
                text: $value,
                label: $this->plainText($answer->question_snapshot),
                submittedAt: $submittedAt,
            );
        }

        return $materials;
    }

    private function answerText(ApplicationAnswer $answer): ?string
    {
        if ($answer->response_type === ApplicationQuestionType::Number) {
            return $answer->value_number === null ? null : (string) $answer->value_number;
        }

        return $this->plainText($answer->value_text);
    }

    /**
     * When the candidate handed this material over. The application's creation is
     * the submission moment; an application predating timestamps falls back to
     * now rather than claiming a date the database does not have.
     */
    private function submittedAt(Application $application): CarbonImmutable
    {
        return $application->created_at === null
            ? CarbonImmutable::now()
            : CarbonImmutable::instance($application->created_at);
    }
}

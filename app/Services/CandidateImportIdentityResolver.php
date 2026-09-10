<?php

namespace App\Services;

use App\Data\CandidateImportDisclosure;
use App\Data\CandidateImportFields;
use App\Data\CandidateImportIdentityLookup;
use App\Data\CandidateImportIssue;
use App\Enums\CandidateImportIssueCode;
use App\Models\Candidate;
use App\Models\CandidateMaterial;
use Illuminate\Support\Facades\DB;

/**
 * Decides which existing candidate, if any, a row is talking about.
 *
 * One key does that work: the normalized email inside the destination
 * workspace. Names, phone numbers, LinkedIn addresses, CV filenames and CV
 * contents are never identity keys, alone or combined, and no similarity is
 * computed anywhere in this class. Two addresses that differ by a dot or a tag
 * are two people until a human says otherwise, and an existing candidate with
 * no email on file is never claimed by a row that merely resembles them.
 *
 * When more than one stored candidate carries the same normalized email the row
 * is blocked rather than resolved. Picking the first, the newest or the most
 * complete would attach a CV to someone by luck, and repairing the historical
 * duplicate is a decision with consequences well beyond this import.
 *
 * The resolver reads; it writes nothing. No candidate and no material is
 * created, updated or deleted here, which is what makes a preview safe to
 * abandon.
 */
class CandidateImportIdentityResolver
{
    /** Candidates fetched per query, so a thousand-row file does not build one enormous IN clause. */
    private const LOOKUP_CHUNK = 500;

    /**
     * Read the workspace once for every email the file mentions.
     *
     * @param  list<string>  $normalizedEmails
     */
    public function lookup(int $companyId, array $normalizedEmails): CandidateImportIdentityLookup
    {
        $emails = array_values(array_unique(array_filter($normalizedEmails, fn (string $email): bool => $email !== '')));

        if ($emails === []) {
            return new CandidateImportIdentityLookup;
        }

        /** @var array<string, list<Candidate>> $candidates */
        $candidates = [];
        /** @var list<int> $candidateIds */
        $candidateIds = [];

        foreach (array_chunk($emails, self::LOOKUP_CHUNK) as $chunk) {
            /** @var list<Candidate> $matches */
            $matches = Candidate::query()->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereIn('normalized_email', $chunk)
                ->get()
                ->all();

            foreach ($matches as $candidate) {
                $key = (string) $candidate->getAttribute('normalized_email');
                $candidates[$key][] = $candidate;
                $candidateIds[] = (int) $candidate->getKey();
            }
        }

        return new CandidateImportIdentityLookup($candidates, $this->materialCounts($companyId, $candidateIds));
    }

    /**
     * How many retained independent CVs each matched candidate already holds.
     *
     * Archived materials count — they are retained, they occupy the workspace's
     * allowance and they can be restored. Erased ones do not. Application
     * documents are not counted at all: they belong to an application's history,
     * not to the candidate's own pool of CVs, and the limit this feeds is about
     * the latter.
     *
     * @param  list<int>  $candidateIds
     * @return array<int, int>
     */
    private function materialCounts(int $companyId, array $candidateIds): array
    {
        $ids = array_values(array_unique($candidateIds));

        if ($ids === []) {
            return [];
        }

        /** @var array<int, int> $counts */
        $counts = [];

        foreach (array_chunk($ids, self::LOOKUP_CHUNK) as $chunk) {
            /** @var array<int, int> $chunkCounts */
            $chunkCounts = CandidateMaterial::query()
                ->where('company_id', $companyId)
                ->whereIn('candidate_id', $chunk)
                ->whereNull('deleted_at')
                ->groupBy('candidate_id')
                ->select('candidate_id', DB::raw('count(*) as materials_total'))
                ->pluck('materials_total', 'candidate_id')
                ->map(fn ($total): int => (int) $total)
                ->all();

            foreach ($chunkCounts as $candidateId => $total) {
                $counts[(int) $candidateId] = $total;
            }
        }

        return $counts;
    }

    /**
     * The review issues reusing this candidate raises.
     *
     * A name that does not match, ignoring letter case and repeated whitespace,
     * is the one comparison made against an existing record — and only to ask
     * the recruiter whether this really is the same person. It never selects a
     * candidate and never rejects one: a matching email with a different name is
     * either a shared mailbox, a married name or a mistake, and only a human can
     * tell which.
     *
     * Capacity is raised in the same place because it has the same shape: the
     * row is importable, but the recruiter has to say whether the contact alone
     * is worth importing when the CV cannot be retained.
     *
     * @return list<CandidateImportIssue>
     */
    public function reuseIssues(Candidate $candidate, CandidateImportFields $fields, int $materialCount): array
    {
        $issues = [];
        $existingName = (string) $candidate->getAttribute('name');

        if ($fields->name !== null && $this->comparableName($fields->name) !== $this->comparableName($existingName)) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::ExistingNameDiffers, 'name', [
                'existing' => $existingName,
                'supplied' => $fields->name,
            ]);
        }

        if ($fields->cvFilename !== null && $materialCount >= CandidateImportLimits::RETAINED_MATERIALS) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::ExistingMaterialsAtCapacity, null, [
                'limit' => CandidateImportLimits::RETAINED_MATERIALS,
                'found' => $materialCount,
            ]);
        }

        return $issues;
    }

    /**
     * What the file says that the workspace will keep to itself.
     *
     * Reuse never edits the candidate on record. Every stored contact field
     * survives the import untouched, including the ones that are empty today —
     * filling a blank phone number from a spreadsheet is still an edit nobody
     * asked for. The differences are collected so the preview can say "this will
     * not be applied", which is a disclosure, not a pending change.
     *
     * @return list<CandidateImportDisclosure>
     */
    public function disclosures(Candidate $candidate, CandidateImportFields $fields): array
    {
        $disclosures = [];
        $existingPhone = $this->stringOrNull($candidate->getAttribute('phone'));
        $existingLinkedin = $this->existingLinkedin($candidate);
        $existingName = $this->stringOrNull($candidate->getAttribute('name'));

        if ($fields->name !== null && $this->comparableName($fields->name) !== $this->comparableName((string) $existingName)) {
            $disclosures[] = new CandidateImportDisclosure('name', $fields->name, $existingName);
        }

        if ($fields->phone !== null && $fields->phone !== $existingPhone) {
            $disclosures[] = new CandidateImportDisclosure('phone', $fields->phone, $existingPhone);
        }

        if ($fields->linkedinUrl !== null && $fields->linkedinUrl !== $existingLinkedin) {
            $disclosures[] = new CandidateImportDisclosure('linkedin_url', $fields->linkedinUrl, $existingLinkedin);
        }

        return $disclosures;
    }

    /** Letter case and repeated whitespace do not make two names different. */
    public function comparableName(string $name): string
    {
        return mb_strtolower((string) preg_replace('/[\s\x{00A0}]+/u', ' ', trim($name)));
    }

    /** The LinkedIn address already on the candidate's social profiles, if any. */
    private function existingLinkedin(Candidate $candidate): ?string
    {
        $socials = $candidate->getAttribute('socials');

        if (! is_array($socials)) {
            return null;
        }

        foreach ($socials as $social) {
            if (! is_array($social)) {
                continue;
            }
            if (($social['network'] ?? null) !== 'linkedin') {
                continue;
            }
            $account = $this->stringOrNull($social['account'] ?? null);
            if ($account !== null) {
                return $account;
            }
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}

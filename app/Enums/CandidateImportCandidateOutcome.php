<?php

namespace App\Enums;

/**
 * What execution actually did to the person behind one row.
 *
 * This is the contact axis, and it is deliberately separate from what happened
 * to the document: one reused candidate can gain a new CV, and one unchanged
 * candidate can have a duplicate CV skipped. Collapsing both into a single
 * "result" would force the report to choose which half of the truth to tell.
 *
 * Every case here is terminal. A row that is still being worked on carries no
 * outcome at all, which is how a resumed execution tells the two apart.
 */
enum CandidateImportCandidateOutcome: string
{
    /** The workspace did not have this person and now does. */
    case Created = 'created';

    /** The workspace already had this person, and the row added a CV to them. */
    case Reused = 'reused';

    /**
     * The workspace already had this person and the row added nothing: it
     * carried no CV, or the only CV it carried was already retained. Recorded
     * as a deliberate result rather than a failure, because it is one.
     */
    case Unchanged = 'unchanged';

    /** The reviewer left this row out before the import started. */
    case Excluded = 'excluded';

    /**
     * Something the review relied on changed after confirmation, so the row
     * was not applied. It is neither a success nor a defect: a human has to
     * look again.
     */
    case NeedsReview = 'needs_review';

    /** The operation could not complete, and nothing of it was kept. */
    case Failed = 'failed';

    /** Results that count as work the workspace can rely on. */
    public function isCommitted(): bool
    {
        return in_array($this, [self::Created, self::Reused, self::Unchanged], true);
    }
}

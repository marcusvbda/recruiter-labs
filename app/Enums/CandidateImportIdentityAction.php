<?php

namespace App\Enums;

/**
 * What validation proposes to do with a row's candidate identity.
 *
 * A proposal, never a commitment: nothing here creates or reuses anything on
 * its own. The only automatic key is the normalized email inside the
 * destination workspace, so exactly three outcomes exist — the workspace has no
 * candidate with that email, it has one, or it has more than one and the
 * product will not pick.
 */
enum CandidateImportIdentityAction: string
{
    /** No candidate in the workspace carries this email. */
    case Create = 'create';

    /** Exactly one candidate carries this email. */
    case Reuse = 'reuse';

    /** More than one candidate carries this email; the row is blocked. */
    case Ambiguous = 'ambiguous';

    /** The email itself could not be read, so identity was never attempted. */
    case Unresolved = 'unresolved';
}

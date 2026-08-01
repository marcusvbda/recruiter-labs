import type { Job } from '@/types/models/job';
import type { Referral } from '@/types/models/referral';

interface ApplyProps {
    referral?: Referral;
    job: Job;
}

export default function Apply({ referral, job }: ApplyProps) {
    return (
        <div className="flex w-full flex-col gap-2">
            <h1 className="text-white">
                JOB APPLY : [{job.key}] - {job.name}
            </h1>
            {referral?.id && (
                <p className="text-white">Referral ID: {referral.key}</p>
            )}
        </div>
    );
}

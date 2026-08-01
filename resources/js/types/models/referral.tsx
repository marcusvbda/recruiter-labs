import type { Model } from './model';

export interface Referral extends Model {
    company_id: number;
    job_id: number;
    user_id: number;
    key: string;
}

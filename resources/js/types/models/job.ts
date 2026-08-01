import type { Model } from './model';

export interface Job extends Model {
    company_id: number;
    name: string;
    description: string | null;
    starts_at: string | null;
    ends_at: string | null;
    campaign_expectation: string | null;
    key: string;
    published: boolean;
}

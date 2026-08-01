import type { Model } from './model';

export interface JobCompany {
    id: number;
    name: string;
}

export interface JobApplicationQuestion extends Model {
    job_id: number;
    question: string;
    response_type: 'text' | 'number' | 'textarea';
    description: string | null;
    required: boolean;
    sort: number;
}

export interface CvFileType extends Model {
    extension: string;
    sort: number;
}

export interface Job extends Model {
    company_id: number;
    company: JobCompany;
    name: string;
    description: string | null;
    starts_at: string | null;
    ends_at: string | null;
    campaign_expectation: string | null;
    key: string;
    published: boolean;
    application_questions: JobApplicationQuestion[];
    accepted_cv_types: CvFileType[];
}

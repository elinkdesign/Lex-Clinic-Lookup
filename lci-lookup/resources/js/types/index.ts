import type { Config } from 'ziggy-js';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export interface SearchResult {
    'Provider Legacy ID': string;
    'Provider NPI': string;
    'Line Description': string;
}

export interface FormData {
    legacy_id: string;
    npi: string;
    line_description: string;
}

export interface SubmitRecordPayload extends FormData {
    delete_existing_records?: boolean;
}

export interface DuplicateMatches {
    longList: SearchResult[];
    shortList: SearchResult[];
}

export interface ConflictResponse {
    success: false;
    message: string;
    duplicates: DuplicateMatches;
}

export interface Errors {
    [key: string]: string;
}

export interface PageProps {
    auth: {
        user: User;
    };
    ziggy: Config & { location: string };
    errors: Errors;
    [key: string]: unknown;
}

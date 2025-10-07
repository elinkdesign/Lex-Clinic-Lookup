import type { Config } from 'ziggy-js';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export interface SearchResult {
    NID: string;
    LIC: string;
    name: string;
}

export interface FormData {
    NID: string;
    LIC: string;
    name: string;
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